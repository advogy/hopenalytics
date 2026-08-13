<?php

return [
    'title' => 'Settings',
    'subtitle' => 'Configure the weekly auto-fetch schedule.',
    'saved' => 'Schedule settings saved successfully.',
    'auto_fetch_active' => 'Weekly auto-fetch active',
    'day' => 'Day',
    'time_wib' => 'Time (WIB)',
    'next_fetch' => 'Next fetch:',
    'auto_fetch_inactive' => 'Weekly auto-fetch is currently off.',
    'save_settings' => 'Save Settings',
    'schedule_note' => 'This schedule triggers the :command command, which fetches the latest data for all auto-fetch accounts (except those marked manual).',
    'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],

    'cs_title' => 'National Coordinator',
    'cs_subtitle' => 'This number and link are the default used by the Customer Service button in the bottom-right corner of every page — used for anyone whose Union hasn\'t set its own Union Coordinator (configured via Manage Accounts → Union).',
    'cs_whatsapp_number' => 'National Coordinator WhatsApp Number',
    'cs_whatsapp_number_hint' => 'International format, no "+" or leading 0, e.g. 628123456789. Leave blank to hide this link.',
    'cs_whatsapp_group_link' => 'National WhatsApp Group Link',
    'cs_whatsapp_group_link_hint' => 'The WhatsApp group invite link (chat.whatsapp.com/…). Leave blank to hide this link.',

    'apify_title' => 'API Credentials & Weekly Auto-Fetch',
    'apify_subtitle' => 'Instagram, TikTok, and Facebook are fetched through a paid third-party service (Apify). YouTube uses its own free official API, but still needs its own API key.',
    'apify_token' => 'Apify API Token',
    'apify_token_hint_set' => 'A token is already set. Leave this field blank to keep the current token.',
    'apify_token_hint_unset' => "Not set yet — currently using the server's .env APIFY_TOKEN (if any). Fill this in to manage it from this page instead, no .env changes needed.",
    'apify_fallback_to_manual' => 'If Apify credits run out, automatically mark the account for manual data entry',

    'youtube_api_key' => 'YouTube API Key',
    'youtube_api_key_hint_set' => 'A key is already set. Leave this field blank to keep the current key.',
    'youtube_api_key_hint_unset' => "Not set yet — currently using the server's .env YOUTUBE_API_KEY (if any). Fill this in to manage it from this page instead, no .env changes needed.",

    'platform_title' => 'Tracked Platforms',
    'platform_subtitle' => 'Uncheck a platform to hide it across the whole app — it won\'t be offered when adding an account, won\'t show on any card/chart/directory, and stops being fetched weekly. Existing data stays saved and reappears instantly once checked again.',
    'platform_account_count' => ':count accounts',
];
