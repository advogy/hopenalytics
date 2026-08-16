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
        // contentDetails costs nothing extra — channels.list is a flat 1 quota unit regardless
        // of how many `part`s are requested — so it's always included here, giving
        // relatedPlaylists.uploads (the channel's uploads playlist ID) a free ride inside
        // raw_payload for fetchRecentVideos() below to reuse without its own channels.list call.
        $response = Http::retry(3, 2000)
            ->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'statistics,contentDetails',
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

    /**
     * Recent video titles/descriptions for hashtag matching (see MatchAccountHashtags) — a
     * playlistItems.list call against the channel's own uploads playlist costs 1 quota unit,
     * versus the 100 units a search.list keyword search would cost per channel per week, so this
     * is the cheap path deliberately chosen over the more "obvious" search-based approach. Only
     * called when hashtags are actively tracked (see fetchByParam's raw_payload, which is where
     * the caller reads $uploadsPlaylistId from).
     */
    public function fetchRecentVideos(string $uploadsPlaylistId, int $limit = 15): array
    {
        $response = Http::retry(3, 2000)
            ->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'part' => 'snippet',
                'playlistId' => $uploadsPlaylistId,
                'maxResults' => $limit,
                'key' => $this->apiKey,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("YouTube playlistItems API error [{$response->status()}]: {$response->body()}");
        }

        return collect($response->json('items') ?? [])
            ->map(function ($item) {
                $snippet = $item['snippet'] ?? [];
                $videoId = $snippet['resourceId']['videoId'] ?? '';

                return [
                    'external_post_id' => (string) $videoId,
                    'post_url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : '',
                    'author_handle' => $snippet['channelTitle'] ?? null,
                    // Hashtags in a YouTube upload are as likely to sit in the description as
                    // the title, so both are checked together rather than just the title alone.
                    'caption' => trim(($snippet['title'] ?? '').' '.($snippet['description'] ?? '')),
                    'likes_count' => null,
                    'comments_count' => null,
                    'views_count' => null,
                    'posted_at' => $snippet['publishedAt'] ?? null,
                ];
            })
            ->filter(fn ($video) => $video['external_post_id'] !== '')
            ->values()
            ->all();
    }
}
