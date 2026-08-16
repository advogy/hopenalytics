<?php

namespace App\Support;

/**
 * Whether a piece of post/video text actually uses a tracked hashtag — an exact hashtag-token
 * match against the text's own "#word" mentions, not a loose substring search, so a tracked tag
 * like "gmahk" doesn't false-positive on an unrelated "#gmahknews" or a caption that merely
 * contains the word "gmahk" without a leading #.
 */
class HashtagMatcher
{
    /**
     * @param  array<int, string>  $lowercaseTrackedTags  Already-lowercased tag values (no
     *   leading #) to check for — see Hashtag::tag.
     * @return array<int, string> The lowercase tags (a subset of $lowercaseTrackedTags) that
     *   actually appear in $text.
     */
    public static function match(?string $text, array $lowercaseTrackedTags): array
    {
        if (! $text || ! $lowercaseTrackedTags) {
            return [];
        }

        preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $matches);

        $mentioned = collect($matches[1] ?? [])->map(fn ($tag) => mb_strtolower($tag))->unique();

        return $mentioned->intersect($lowercaseTrackedTags)->values()->all();
    }
}
