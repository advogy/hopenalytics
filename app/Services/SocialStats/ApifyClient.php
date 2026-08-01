<?php

namespace App\Services\SocialStats;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApifyClient
{
    private readonly string $token;

    public function __construct(string $token = '')
    {
        $this->token = $token ?: (string) config('services.apify.token');
    }

    /**
     * Run an Apify actor synchronously and return its first dataset item.
     */
    public function runActorSync(string $actorId, array $input, int $timeoutSeconds = 60): array
    {
        $items = $this->runActorSyncAll($actorId, $input, $timeoutSeconds);

        if (! $items) {
            throw new RuntimeException("Apify actor [{$actorId}] returned no data.");
        }

        return $items[0];
    }

    /**
     * Run an Apify actor synchronously and return every dataset item (e.g. a batch of recent posts/videos).
     */
    public function runActorSyncAll(string $actorId, array $input, int $timeoutSeconds = 60): array
    {
        // Bearer header rather than ?token=... in the URL — a connection-level failure
        // (timeout/DNS/SSL) throws with the full request URL in its message, which
        // otherwise would have leaked the token straight into an admin-visible error (the
        // single-refresh button's flash message, the Queue Monitor's failed-job preview).
        //
        // retry()'s default $throw=true means a still-failing response after retries are
        // exhausted throws its own RequestException here, rather than being returned as a
        // normal (if unsuccessful) Response — that's what's caught below, not $response->failed().
        try {
            $response = Http::timeout($timeoutSeconds)
                ->withToken($this->token)
                ->retry(2, 3000)
                ->post("https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items", $input);
        } catch (RequestException $e) {
            if ($this->isCreditsExhausted($e->response)) {
                throw new ApifyCreditsExhaustedException("Apify actor [{$actorId}] error [{$e->response->status()}]: {$e->response->body()}");
            }

            throw new RuntimeException("Apify actor [{$actorId}] error [{$e->response->status()}]: {$e->response->body()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Apify returns 402 Payment Required once an account is out of usage credits or has hit its
     * plan's monthly usage limit — everything else (actor errors, bad input, rate limiting) comes
     * back as a different status. The error-type substring check is a fallback in case Apify ever
     * reports this condition under a different status code; adjust if a real exhausted-account
     * response is observed not to match either check.
     */
    private function isCreditsExhausted(Response $response): bool
    {
        if ($response->status() === 402) {
            return true;
        }

        $errorType = (string) ($response->json('error.type') ?? '');

        return str_contains($errorType, 'usage-hard-limit') || str_contains($errorType, 'insufficient-funds');
    }
}
