<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check('database', fn () => DB::select('select 1')),
            'redis' => $this->check('redis', fn () => $this->pingRedis()),
        ];

        $healthy = ! in_array('down', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'services' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function check(string $service, callable $operation): string
    {
        try {
            $operation();

            return 'up';
        } catch (Throwable $exception) {
            Log::warning('Health check failed.', [
                'service' => $service,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return 'down';
        }
    }

    private function pingRedis(): void
    {
        $host = (string) config('database.redis.default.host');
        $port = (int) config('database.redis.default.port');
        $socket = stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, 1);

        if ($socket === false) {
            throw new \RuntimeException("Redis connection failed: {$errorCode} {$errorMessage}");
        }

        fwrite($socket, "*1\r\n$4\r\nPING\r\n");
        $response = fgets($socket);
        fclose($socket);

        if ($response !== "+PONG\r\n") {
            throw new \RuntimeException('Redis returned an unexpected response.');
        }
    }
}
