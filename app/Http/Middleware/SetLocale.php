<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @var array<int, string> */
    public const SUPPORTED = ['en', 'tl', 'cbk', 'gly'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale', 'en'));
        if (! is_string($locale) || ! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }
        App::setLocale($locale);

        return $next($request);
    }
}
