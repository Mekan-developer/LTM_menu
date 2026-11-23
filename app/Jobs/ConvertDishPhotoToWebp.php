<?php

namespace App\Jobs;

use App\Models\Dish;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Сжимает фото блюда в WebP (длинная сторона ≤ 1200 px), чтобы меню грузилось быстро. */
class ConvertDishPhotoToWebp implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $dishId, public string $tmpPath) {}

    public function handle(): void
    {
        $dish = Dish::find($this->dishId);
        $local = Storage::disk('local');

        if ($dish === null || ! $local->exists($this->tmpPath)) {
            $local->delete($this->tmpPath);

            return;
        }

        $image = imagecreatefromstring($local->get($this->tmpPath));

        if ($image === false) {
            $local->delete($this->tmpPath);

            throw new RuntimeException("Не удалось прочитать фото блюда #{$this->dishId}");
        }

        $image = $this->resize($image, (int) config('restaurant.photo.max_side'));

        ob_start();
        imagewebp($image, null, (int) config('restaurant.photo.webp_quality'));
        $webp = ob_get_clean();
        imagedestroy($image);

        if ($webp === false || $webp === '') {
            throw new RuntimeException("WebP encoding failed for dish {$dish->id}");
        }

        $path = 'dishes/'.$dish->id.'-'.now()->timestamp.'.webp';
        Storage::disk('public')->put($path, $webp);

        if ($dish->photo_path) {
            Storage::disk('public')->delete($dish->photo_path);
        }

        $dish->forceFill(['photo_path' => $path])->save();
        $local->delete($this->tmpPath);
    }

    private function resize(\GdImage $image, int $maxSide): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));

        if ($scale >= 1) {
            return $image;
        }

        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($image);

        return $resized;
    }
}
