<?php

return [
    'updated_on' => 'Last updated: September 1, 2026',

    // --- Privacy Policy ---
    'privacy_title' => 'Privacy Policy',
    'privacy_intro' => ':app ("we", "the system") is a growth-monitoring dashboard for social media accounts across a tiered church organizational structure (Division/Union/Conference/Church), Institutions, and Personal (individual) profiles. This policy explains what data we collect, how it\'s used, and your rights over it.',

    'privacy_s1_title' => '1. Data We Collect',
    'privacy_s1_p1' => '<strong>User accounts:</strong> name, email address, password (stored hashed, never in plain text), and login records (IP address, browser user agent, login/logout times).',
    'privacy_s1_p2' => '<strong>Personal (individual) data:</strong> name, city, country, and location coordinates (optional), self-reported by the user or entered by an admin in their region.',
    'privacy_s1_p3' => '<strong>Social media account data:</strong> platform name, handle/username, profile URL, and that account\'s <strong>public statistics</strong> (follower/subscriber count, views, likes, post count) — never private messages, private content, or the social media account\'s own login credentials. This data is drawn from information the account itself has already published publicly on its platform.',

    'privacy_s2_title' => '2. Special Consent for Personal Accounts',
    'privacy_s2_p1' => 'For social media accounts owned by a <strong>Personal</strong> (an individual), the system will <strong>never</strong> pull its statistics without explicit consent from that individual (or an admin managing that profile on their behalf) — captured via a consent checkbox when the account is added. Without this consent, the account stays registered in the system but its data is never fetched, automatically or manually. This consent can be withdrawn at any time by contacting your regional admin, or by deleting that social media account from the system.',

    'privacy_s3_title' => '3. How Data Is Used',
    'privacy_s3_items' => [
        'Displaying growth charts, growth scores, rankings, and comparisons across accounts/regions.',
        'Displaying an entity location map and account directory.',
        'Compiling reports/exports (PDF, Word, Excel) for internal organizational use.',
        'A presentation mode for events/meetings.',
    ],

    'privacy_s4_title' => '4. Who Can See Your Data',
    'privacy_s4_p1' => "Access to data is tiered by each admin's own role and region (a Church admin only sees their own church, a Conference/Union/Division admin sees the regions below them, and so on) — not open access to all data for anyone logged in. The account directory and Analytics & Charts, being public-summary in nature, can be seen by any registered user in the system.",

    'privacy_s5_title' => '5. Third Parties Involved',
    'privacy_s5_items' => [
        '<strong>Apify</strong> — fetching public data from Instagram, TikTok, Facebook, and X.',
        '<strong>YouTube Data API v3</strong> (official, owned by Google) — fetching YouTube channel data.',
        '<strong>OpenStreetMap Nominatim</strong> — geocoding city names into map coordinates.',
        'An email service provider — sending verification (OTP) codes to your email.',
    ],

    'privacy_s6_title' => '6. Your Rights Over Your Data',
    'privacy_s6_p1' => 'You have the right to request correction, updates, or deletion of your data by contacting your region/organization\'s admin, or through the contact details at the bottom of this page.',

    'privacy_s7_title' => '7. Users Outside Indonesia',
    'privacy_s7_p1' => ':app serves organizations outside Indonesia, particularly in Southeast Asia. For users domiciled in other countries, that country\'s own data-protection law (e.g. GDPR in the European Union, PDPA in Thailand/Malaysia/Singapore, or equivalent provisions elsewhere) may still apply to your data, in addition to and without overriding this policy. We aim to follow broadly accepted data-protection principles — consent before data is pulled, tiered access restrictions, and the right to correct/delete data — regardless of a user\'s country of origin.',

    'privacy_s8_title' => '8. Changes to This Policy',
    'privacy_s8_p1' => 'This policy may be updated from time to time as the application\'s features evolve. The most recent update date is always shown at the top of this page.',

    'privacy_s9_title' => '9. Contact',
    'privacy_s9_p1' => 'Questions about data privacy can be sent to :email.',

    // --- Terms of Service ---
    'terms_title' => 'Terms of Service',
    'terms_intro' => 'By registering for and using :app, you agree to the following terms. Please read them before using this service.',

    'terms_s1_title' => '1. User Eligibility',
    'terms_s1_p1' => 'This service is provided for members, ministers, and admins of church organizations and related institutions who are authorized to manage or monitor social media account data within this organizational structure.',

    'terms_s2_title' => '2. Account & User Responsibilities',
    'terms_s2_items' => [
        "You're responsible for keeping your account password confidential.",
        "You're responsible for the accuracy of the data you enter (name, region, social media handles, etc.).",
        "You may not register a social media account belonging to someone else without that owner's permission.",
    ],

    'terms_s3_title' => '3. Permitted Use',
    'terms_s3_p1' => "This service is purely for monitoring and comparing social media account growth — not for publishing/scheduling content to social media, not for accessing anyone's social media account without authorization, and not for any purpose that violates applicable law.",

    'terms_s4_title' => '4. Data & Third Parties',
    'terms_s4_p1' => 'Social media account statistics are drawn from public data provided by the relevant platforms (Instagram, TikTok, Facebook, X, YouTube) through third-party services (see the Privacy Policy). We are not responsible for the accuracy, availability, or policy changes of those platforms.',

    'terms_s5_title' => '5. Changes & Discontinuation of Service',
    'terms_s5_p1' => "We may change, suspend, or discontinue some or all of this service's features at any time, including disabling monitoring for a particular platform, with or without prior notice.",

    'terms_s6_title' => '6. Limitation of Liability',
    'terms_s6_p1' => 'The service is provided "as is" without any warranty. We are not liable for losses arising from inaccurate third-party data, service disruptions, or account misuse by unauthorized parties resulting from a user\'s own failure to safeguard their account credentials.',

    'terms_s7_title' => '7. Governing Law',
    'terms_s7_p1' => 'These terms are governed by the laws of the Republic of Indonesia.',

    'terms_s8_title' => '8. Contact',
    'terms_s8_p1' => 'Questions about these terms of service can be sent to :email.',
];
