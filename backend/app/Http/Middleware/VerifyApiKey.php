<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the server-to-server calls coming from WordPress.
 *
 * The shared key is compared in constant time, and the routes are refused
 * outright when no key is configured. That fails closed: an operator who
 * forgets to set SAFARI_API_KEY gets a 404 (the bridge is off) rather than an
 * unauthenticated write endpoint.
 *
 * Registered as the `safari.api` alias — see bootstrap/app.php.
 */
class VerifyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('safari.api.key', ''));

        if ($expected === '') {
            return response()->json([
                'message' => 'The API bridge is disabled because SAFARI_API_KEY is not configured.',
            ], Response::HTTP_NOT_FOUND);
        }

        $header = (string) config('safari.api.header', 'X-Safari-Api-Key');
        $provided = trim((string) $request->header($header, ''));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Invalid or missing API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
