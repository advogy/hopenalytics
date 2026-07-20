<?php

namespace App\Services\SocialStats;

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
        $response = Http::timeout($timeoutSeconds)
            ->withToken($this->token)
            ->retry(2, 3000)
            ->post("https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items", $input);

        if ($response->failed()) {
            throw new RuntimeException("Apify actor [{$actorId}] error [{$response->status()}]: {$response->body()}");
        }

        return $response->json() ?? [];
    }
}
