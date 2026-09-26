<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based authorisation for API routes.
 *
 * Sanctum's built-in `ability` middleware checks the abilities frozen into a
 * token at creation time, so a demotion would not take effect until the token
 * was reissued. This middleware resolves the operator's *current* role on every
 * request instead, which means revoking access is an immediate database change.
 *
 * Registered as the `operator` alias — see bootstrap/app.php.
 */
class EnsureOperatorAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'data' => ['status' => Response::HTTP_UNAUTHORIZED],
            ], Response::HTTP_UNAUTHORIZED);
        }

        foreach ($abilities as $ability) {
            if (! $this->allows($user, $ability)) {
                return response()->json([
                    'message' => 'This token is not permitted to perform that action.',
                    'data' => ['status' => Response::HTTP_FORBIDDEN],
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }

    private function allows(object $user, string $ability): bool
    {
        return match ($ability) {
            'lead.view' => method_exists($user, 'canViewLeads') && $user->canViewLeads(),
            'lead.manage' => method_exists($user, 'canManageLeads') && $user->canManageLeads(),
            'operator.admin' => method_exists($user, 'isAdmin') && $user->isAdmin(),
            default => false,
        };
    }
}
