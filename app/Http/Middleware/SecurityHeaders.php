<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // A per-request nonce with no 'unsafe-inline' or 'unsafe-eval': injected
        // markup cannot run script. This is why the frontend uses Alpine's CSP build.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->remove('X-Powered-By');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $script = ["'self'", "'nonce-{$nonce}'"];
        $frame = ["'none'"];
        $connect = ["'self'"];
        $style = ["'self'", "'nonce-{$nonce}'"];

        if (config('cureplus.captcha.driver') === 'turnstile') {
            $script[] = 'https://challenges.cloudflare.com';
            $frame = ['https://challenges.cloudflare.com'];
        }

        // Vite's dev server only exists locally, and it injects styles at runtime.
        if (Vite::isRunningHot()) {
            $hot = trim((string) file_get_contents(public_path('hot')));
            $script[] = $hot;
            $style[] = $hot;
            $style[] = "'unsafe-inline'";
            $connect[] = $hot;
            $connect[] = str_replace(['http://', 'https://'], 'ws://', $hot);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            'frame-src '.implode(' ', $frame),
            "manifest-src 'self'",
            "worker-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
