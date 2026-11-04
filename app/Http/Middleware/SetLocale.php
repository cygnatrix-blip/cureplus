<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user('patient')->locale ?? $request->session()->get('locale');

        if (is_string($locale) && array_key_exists($locale, config('cureplus.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
