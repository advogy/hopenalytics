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
     * Apify returns 402 Payment Required once an account is out of usage credits — but a
     * monthly *plan* limit (as opposed to running out of prepaid credits) instead comes back as
     * 403 with error.type "platform-feature-disabled" and a "Monthly usage hard limit exceeded"
     * message (observed in production 2026-08-02: every Instagram fetch failed as a plain
     * RuntimeException all day instead of tripping the fallback-to-manual handling this
     * exception exists for, because neither the 402 check nor the old error-type substring
     * check recognized this shape). The message-text check is deliberately narrow — a 403 can
     * mean plenty of other, unrelated things — rather than treating every 403 as exhaustion.
     */
    private function isCreditsExhausted(Response $response): bool
    {
        if ($response->status() === 402) {
            return true;
        }

        $errorType = (string) ($response->json('error.type') ?? '');

        if (str_contains($errorType, 'usage-hard-limit') || str_contains($errorType, 'insufficient-funds')) {
            return true;
        }

        $errorMessage = (string) ($response->json('error.message') ?? '');

        return $response->status() === 403 && str_contains(strtolower($errorMessage), 'usage hard limit');
    }
}
