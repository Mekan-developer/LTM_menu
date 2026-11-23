<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Язык интерфейса: TM по умолчанию, RU — переключателем в шапке (ТЗ 3.4). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['tk', 'ru']);
        $locale = $request->session()->get('locale', config('app.locale'));

        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
