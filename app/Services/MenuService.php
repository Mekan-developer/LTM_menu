<?php

namespace App\Services;

use App\Jobs\ConvertDishPhotoToWebp;
use App\Models\Category;
use App\Models\Dish;
use App\Repositories\MenuRepository;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MenuService
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly ActionLogger $logger,
    ) {}

    public function createCategory(array $data): Category
    {
        return $this->menu->createCategory($data + ['sort' => $this->menu->nextCategorySort()]);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        return $this->menu->updateCategory($category, $data);
    }

    public function deleteCategory(Category $category): void
    {
        DB::transaction(function () use ($category) {
            $category->load('dishes:id,category_id,photo_path');
            $category->dishes->each(fn (Dish $d) => $this->deletePhoto($d->photo_path));
            $category->stopEntry()->delete();
            $category->delete();
        });
    }

    public function createDish(array $data, ?UploadedFile $photo): Dish
    {
        $data['price'] = Money::toMinor($data['price']);
        $data['sort'] = $this->menu->nextDishSort((int) $data['category_id']);

        $dish = $this->menu->createDish($data);
        $this->queuePhoto($dish, $photo);

        return $dish;
    }

    public function updateDish(Dish $dish, array $data, ?UploadedFile $photo, bool $removePhoto): Dish
    {
        $oldPrice = $dish->price;
        $data['price'] = Money::toMinor($data['price']);

        $this->menu->updateDish($dish, $data);

        if ($oldPrice !== $dish->price) {
            $this->logger->log(ActionLogger::PRICE_CHANGED, $dish, ['old' => $oldPrice, 'new' => $dish->price, 'name' => $dish->name_ru]);
        }

        if ($removePhoto && $dish->photo_path) {
            $this->deletePhoto($dish->photo_path);
            $this->menu->updateDish($dish, ['photo_path' => null]);
        }

        $this->queuePhoto($dish, $photo);

        return $dish;
    }

    public function deleteDish(Dish $dish): void
    {
        $this->deletePhoto($dish->photo_path);
        $dish->stopEntry()->delete();
        $dish->delete();
    }

    /** Фото конвертируется в WebP в очереди: исходник кладём во временную папку. */
    private function queuePhoto(Dish $dish, ?UploadedFile $photo): void
    {
        if ($photo === null) {
            return;
        }

        $tmp = $photo->store('dishes/tmp', 'local');
        ConvertDishPhotoToWebp::dispatch($dish->id, $tmp);
    }

    private function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
