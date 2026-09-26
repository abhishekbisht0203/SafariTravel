<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Spam\TurnstileVerifier;
use App\Services\WordPress\WordPressClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operational view of the API and its relationship to WordPress.
 *
 * Always 200 as long as the API itself is up — the `wordPress` block reports
 * coupling problems without failing the probe, so a monitoring check on this
 * endpoint does not flap just because WordPress is restarting.
 */
class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     */
    public function __invoke(WordPressClient $wordpress, TurnstileVerifier $turnstile): JsonResponse
    {
        $database = $this->databaseStatus();
        $queue = $this->queueStatus();

        return response()->json([
            'status' => $database['ok'] ? 'ok' : 'degraded',
            'service' => (string) config('app.name'),
            'version' => (string) config('app.version', '1.0.0'),
            'environment' => (string) config('app.env'),
            'time' => now()->toIso8601String(),
            'database' => $database,
            'queue' => $queue,
            'wordPress' => $wordpress->health(),
            'turnstile' => [
                'enabled' => $turnstile->enabled(),
                'site_key' => $turnstile->siteKey(),
            ],
        ], $database['ok'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * @return array{ok: bool, driver: string, error?: string}
     */
    private function databaseStatus(): array
    {
        $driver = (string) config('database.default');

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'driver' => $driver,
                'error' => Str::limit($e->getMessage(), 200),
            ];
        }

        return ['ok' => true, 'driver' => $driver];
    }

    /**
     * @return array{connection: string, pending: int|null}
     */
    private function queueStatus(): array
    {
        $connection = (string) config('queue.default');

        if (! in_array($connection, ['database', 'redis'], true)) {
            // sync / null run inline, so there is never a backlog.
            return ['connection' => $connection, 'pending' => 0];
        }

        try {
            return [
                'connection' => $connection,
                'pending' => Queue::size(),
            ];
        } catch (\Throwable) {
            return ['connection' => $connection, 'pending' => null];
        }
    }
}
