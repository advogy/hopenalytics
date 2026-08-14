<?php

namespace App\Enums;

/**
 * Which chat app a CoordinatorGroup link points to — deliberately its own small enum rather
 * than reusing SocialPlatform (youtube/instagram/tiktok/facebook/x): this is "where the group
 * chat lives", not a tracked social account, and the two lists don't overlap 1:1 (WhatsApp and
 * Messenger aren't fetched-stats platforms at all).
 */
enum GroupPlatform: string
{
    case WhatsApp = 'whatsapp';
    case Messenger = 'messenger';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Messenger => 'Messenger',
        };
    }
}
