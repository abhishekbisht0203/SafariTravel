<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureOperatorAbility;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // The API. Registered with the `api` middleware group and the `/api`
        // prefix; routes/api.php adds the `v1` segment.
        api: __DIR__.'/../routes/api.php',

        // WordPress serves the website; the backend has no web routes. The file
        // exists so the framework's defaults keep working and holds nothing a
        // visitor could reach.
        web: __DIR__.'/../routes/web.php',

        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Server-to-server calls from WordPress. Fails closed when no shared
            // key is configured, so a forgotten SAFARI_API_KEY cannot leave the
            // bridge open.
            'safari.api' => VerifyApiKey::class,

            // Role-based authorisation, resolved per request so a demotion takes
            // effect immediately rather than when the token is reissued.
            'operator' => EnsureOperatorAbility::class,
        ]);

        // No stateful (cookie + CSRF) API: the only credential this application
        // accepts is a Sanctum bearer token. Leaving `statefulApi()` off keeps
        // session cookies from ever authenticating an API call.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Two keys, one payload.
         *
         * `errors` is the shape Laravel (and any generic API client) expects:
         * one field to a list of messages.
         *
         * `data.fields` is the shape WordPress's REST errors produce — one field
         * to a single message — and it is what theme/assets/src/js/lead-form.js
         * reads to map a message back onto a form control. Emitting both means
         * the same JavaScript works against either endpoint and no consumer has
         * to special-case this API.
         */
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = $e->errors();

            $fields = array_map(
                static fn (array $messages): string => (string) (reset($messages) ?: ''),
                $errors
            );

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $errors,
                'data' => [
                    'status' => $e->status,
                    'fields' => $fields,
                ],
            ], $e->status);
        });

        // Unauthenticated API calls get JSON, never a redirect to a login page
        // that does not exist in this application.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'data' => ['status' => Response::HTTP_UNAUTHORIZED],
            ], Response::HTTP_UNAUTHORIZED);
        });
    })
    ->create();
