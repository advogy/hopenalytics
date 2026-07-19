<?php

namespace App\Services\SocialStats;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class YouTubeStatsFetcher
{
    private readonly string $apiKey;

    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey ?: (string) config('services.youtube.api_key');
    }

    public function fetch(string $channelId): array
    {
        return $this->fetchByParam('id', $channelId);
    }

    /**
     * Fetch by the channel's public @handle instead of its internal channel ID.
     */
    public function fetchByHandle(string $handle): array
    {
        $handle = str_starts_with($handle, '@') ? $handle : "@{$handle}";

        return $this->fetchByParam('forHandle', $handle);
    }

    private function fetchByParam(string $param, string $value): array
    {
        $response = Http::retry(3, 2000)
            ->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'statistics',
                $param => $value,
                'key' => $this->apiKey, // GANTI: YOUTUBE_API_KEY di .env
            ]);

        if ($response->failed()) {
            throw new RuntimeException("YouTube API error [{$response->status()}]: {$response->body()}");
        }

        $item = $response->json('items.0');

        if (! $item) {
            throw new RuntimeException("YouTube channel not found: {$value}");
        }

        $stats = $item['statistics'];

        return [
            'subscribers_count' => (int) ($stats['subscriberCount'] ?? 0),
            'views_count' => (int) ($stats['viewCount'] ?? 0),
            'videos_count' => (int) ($stats['videoCount'] ?? 0),
            'raw_payload' => $item,
        ];
    }
}
