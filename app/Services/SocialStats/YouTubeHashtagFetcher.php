<?php

namespace App\Services\SocialStats;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Unlike Instagram/TikTok, YouTube's Data API has no real hashtag-search endpoint — this falls
 * back to a plain keyword search for "#tag" via search.list, which matches title/description
 * mentions rather than a true hashtag index. Imprecise, but it's the closest available option.
 */
class YouTubeHashtagFetcher
{
    // How many videos to sample per hashtag. search.list costs 100 quota units/call regardless
    // of maxResults, so this only trades result breadth, not cost.
    private const RESULTS_LIMIT = 25;

    private readonly string $apiKey;

    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey ?: (string) config('services.youtube.api_key');
    }

    /** @return array<int, array{external_post_id: string, post_url: string, author_handle: ?string, caption: ?string, likes_count: ?int, comments_count: ?int, views_count: ?int, posted_at: ?string, raw_payload: array}> */
    public function fetch(string $tag): array
    {
        $searchResponse = Http::retry(3, 2000)
            ->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => "#{$tag}",
                'type' => 'video',
                'order' => 'date',
                'maxResults' => self::RESULTS_LIMIT,
                'key' => $this->apiKey,
            ]);

        if ($searchResponse->failed()) {
            throw new RuntimeException("YouTube search API error [{$searchResponse->status()}]: {$searchResponse->body()}");
        }

        $videoIds = collect($searchResponse->json('items') ?? [])
            ->map(fn ($item) => $item['id']['videoId'] ?? null)
            ->filter()
            ->values();

        if ($videoIds->isEmpty()) {
            return [];
        }

        // search.list doesn't return view/like counts — a follow-up videos.list call is needed
        // for statistics (1 quota unit, versus search.list's 100).
        $statsResponse = Http::retry(3, 2000)
            ->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,statistics',
                'id' => $videoIds->implode(','),
                'key' => $this->apiKey,
            ]);

        if ($statsResponse->failed()) {
            throw new RuntimeException("YouTube videos API error [{$statsResponse->status()}]: {$statsResponse->body()}");
        }

        return collect($statsResponse->json('items') ?? [])
            ->map(fn ($item) => [
                'external_post_id' => $item['id'],
                'post_url' => "https://www.youtube.com/watch?v={$item['id']}",
                'author_handle' => $item['snippet']['channelTitle'] ?? null,
                'caption' => $item['snippet']['title'] ?? null,
                'likes_count' => isset($item['statistics']['likeCount']) ? (int) $item['statistics']['likeCount'] : null,
                'comments_count' => isset($item['statistics']['commentCount']) ? (int) $item['statistics']['commentCount'] : null,
                'views_count' => isset($item['statistics']['viewCount']) ? (int) $item['statistics']['viewCount'] : null,
                'posted_at' => $item['snippet']['publishedAt'] ?? null,
                'raw_payload' => $item,
            ])
            ->values()
            ->all();
    }
}
