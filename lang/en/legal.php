<?php

// Privacy Policy & Terms of Service (partials/privacy-content, partials/terms-content).
// Each section: 'id' (anchor), 'title', optional 'paragraphs' and 'items' (HTML allowed);
// ':app' and ':email' are filled in by the view.
return [
    'updated_on' => 'Last updated: 13 November 2026',

    // --- Privacy Policy ---
    'privacy_title' => 'Privacy Policy',
    'privacy_intro' => ':app ("we", "the system") is a dashboard for monitoring the growth of social media accounts across a church organisation (Division/Union/Conference/Church), Institutions, and Personal (individual) accounts. This policy explains what data we collect, how it is used, how long it is kept, and your rights over it, including how to delete it.',

    'privacy_sections' => [
        [
            'id' => 'data-dikumpulkan',
            'title' => '1. Data We Collect',
            'paragraphs' => [
                '<strong>User accounts:</strong> name, email address, password (stored hashed, never in plain text), role and assigned region, and sign-in records (IP address, browser user agent, sign-in/sign-out time).',
                '<strong>Personal (individual) data:</strong> name, city, country, location coordinates (optional), and the Union, Conference and church chosen by the user or entered by an admin of their region.',
                '<strong>Social media account data:</strong> platform, handle/username, profile URL, and the account\'s <strong>public statistics</strong>: followers/subscribers, views, likes, comments, shares, number of posts, plus a sample of recent posts (text, date and interaction counts) for hashtag matching. We never collect private messages, private content, or social media passwords.',
                '<strong>Church membership data:</strong> the recorded number of members of each church as of a date, uploaded by Division admins and confirmed by Union admins. This is a <strong>count</strong>, not a list of names or members\' personal data.',
                '<strong>Connected accounts:</strong> when the owner of a Facebook Page or Instagram Business/Creator account chooses to connect it (see section 3), we store that Page/account\'s ID and name and the access token Meta provides, encrypted.',
            ],
        ],
        [
            'id' => 'izin-personal',
            'title' => '2. Consent for Personal Accounts',
            'paragraphs' => [
                'For social media accounts owned by a <strong>Personal</strong> (individual) profile, the system <strong>never</strong> collects statistics without explicit consent from that person (or the admin managing the profile on their behalf), given through a consent checkbox when the account is added. Without consent the account stays listed, but its data is never fetched and it isn\'t counted in combined growth or member engagement.',
                'Consent can be withdrawn at any time by removing the account from My Profile, or by contacting the admin of your region.',
            ],
        ],
        [
            'id' => 'akun-dihubungkan',
            'title' => '3. Accounts Connected through Meta (Facebook/Instagram)',
            'paragraphs' => [
                'The owner or admin of a Facebook Page, or of an Instagram Business/Creator account linked to that Page, can <strong>connect</strong> it through Facebook login. Its statistics are then read directly from Meta\'s official Graph API instead of from public pages.',
                'When connecting, you allow :app to: see the list of Pages you manage (<em>pages_show_list</em>), read the Page\'s follower count and post statistics (<em>pages_read_engagement</em>), and read the profile and statistics of the linked Instagram Business/Creator account (<em>instagram_basic</em>, <em>instagram_manage_insights</em>).',
                'We <strong>only read</strong>. We don\'t post, don\'t change anything on your Page or account, don\'t read messages, and don\'t store your personal Facebook profile data. The access token is stored encrypted and used only to read the statistics of the account you chose.',
                'You can disconnect at any time with the <strong>Disconnect</strong> button on the account\'s Edit page, or by removing :app under <em>Facebook → Settings → Business Integrations</em> (or <em>Apps and Websites</em>). When disconnected in :app, the token is deleted from our system right away.',
            ],
        ],
        [
            'id' => 'penggunaan-data',
            'title' => '4. How Data Is Used',
            'items' => [
                'Showing growth charts, growth scores (including the combined growth of a church and its members\' personal accounts), rankings, and comparisons between accounts and regions.',
                'Measuring member engagement: recorded membership, the target per period, and how many members are active (registered in the system, with a registered social account, or posting within the period).',
                'Showing a map of entities and a directory of accounts.',
                'Producing reports/exports (PDF, Word, Excel) for internal organisational use.',
                'Presentation mode for events and meetings.',
                'Choosing the display language on a first visit from the country of the IP address (Indonesia or elsewhere). The IP address isn\'t stored for this.',
            ],
        ],
        [
            'id' => 'akses-data',
            'title' => '5. Who Can See Your Data',
            'paragraphs' => [
                'Access is limited by role and region: a Church admin only sees the churches they manage and their Conference, Conference/Union/Division admins see the regions below them, and so on. Membership figures can only be uploaded by Division admins and confirmed by Union admins of that region.',
                'The account directory and the summary pages of Analytics & Charts can be seen by other registered users of the system.',
            ],
        ],
        [
            'id' => 'pihak-ketiga',
            'title' => '6. Third Parties Involved',
            'items' => [
                '<strong>Apify</strong>: collects public data from Instagram, TikTok, Facebook, X and Threads for accounts that aren\'t connected.',
                '<strong>Meta Platforms</strong> (Facebook Login and Graph API): sign-in and reading statistics for Facebook/Instagram accounts connected by their owners. Use of Facebook is also subject to Meta\'s privacy policy.',
                '<strong>YouTube Data API v3</strong> (official, by Google): collects YouTube channel data.',
                '<strong>OpenStreetMap Nominatim</strong>: turns city names into map coordinates.',
                'Email service provider: sends verification codes (OTP) and notifications to your email.',
                'Hosting provider: where the application and database run.',
            ],
        ],
        [
            'id' => 'penyimpanan',
            'title' => '7. How Long Data Is Kept',
            'items' => [
                'Sign-in records are kept for <strong>6 months</strong>, then deleted automatically.',
                'The admin audit log is kept for <strong>12 months</strong>, then deleted automatically.',
                'Social media statistics and membership figures are kept for as long as the account or church is registered, since they make up the growth history charts.',
                'A connected account\'s token is kept only while the connection is active, and deleted as soon as it is disconnected.',
                'Your sign-in session (cookie) ends after a period of inactivity. We don\'t use advertising or third-party tracking cookies.',
            ],
        ],
        [
            'id' => 'hak-anda',
            'title' => '8. Your Rights',
            'paragraphs' => [
                'You may ask for a copy of your data, or for it to be corrected, updated or deleted. Most of it you can change yourself in My Profile; for the rest, contact the admin of your region or organisation, or use the contact at the bottom of this page.',
            ],
        ],
        [
            'id' => 'hapus-data',
            'title' => '9. How to Delete Your Data',
            'paragraphs' => [
                'To disconnect a connected Facebook/Instagram account: open the account\'s <strong>Edit</strong> page in :app and click <strong>Disconnect</strong>, or remove :app under <em>Facebook → Settings → Business Integrations</em>. The access token is deleted right away.',
                'To stop your social media account being monitored: remove it in <strong>My Profile → Social Media</strong>. Its data is no longer collected.',
                'To delete all your data, including your user account, Personal data and your social accounts\' statistics history: email :email with the subject <strong>"Delete Data"</strong> from your registered email address. We will verify the request, delete the data within <strong>30 days</strong> of receiving it, and confirm by email. Church membership figures are aggregate counts, not personal data, and stay with the organisation.',
            ],
        ],
        [
            'id' => 'luar-indonesia',
            'title' => '10. Users outside Indonesia',
            'paragraphs' => [
                ':app serves organisations outside Indonesia, especially in Southeast Asia. For users living in other countries, that country\'s data protection law (e.g. the GDPR in the European Union, the PDPA in Thailand/Malaysia/Singapore, or equivalent rules) may still apply to your data, alongside and without overriding this policy. We also follow Indonesia\'s Personal Data Protection Law (UU PDP).',
            ],
        ],
        [
            'id' => 'perubahan',
            'title' => '11. Changes to This Policy',
            'paragraphs' => [
                'This policy may be updated as the application\'s features change. The date of the last update is always shown at the top of this page.',
            ],
        ],
        [
            'id' => 'kontak',
            'title' => '12. Contact',
            'paragraphs' => [
                'Questions or requests about data privacy can be sent to :email.',
            ],
        ],
    ],

    // --- Terms of Service ---
    'terms_title' => 'Terms of Service',
    'terms_intro' => 'By registering for and using :app, you agree to the following terms. Please read them before using the service.',

    'terms_sections' => [
        [
            'id' => 'kelayakan',
            'title' => '1. Who May Use the Service',
            'paragraphs' => [
                'This service is provided for members, workers and admins of church organisations and related institutions who are authorised to manage or monitor social media account data within this organisation.',
            ],
        ],
        [
            'id' => 'tanggung-jawab',
            'title' => '2. Accounts & User Responsibilities',
            'items' => [
                'You are responsible for keeping your password confidential.',
                'You are responsible for the accuracy of the data you enter (name, region, church, social media handles, etc.).',
                'You may not register someone else\'s social media account without the owner\'s permission.',
                'Admins are responsible for using their access only for the regions and churches assigned to them.',
            ],
        ],
        [
            'id' => 'penggunaan',
            'title' => '3. Permitted Use',
            'paragraphs' => [
                'This service is only for monitoring and comparing social media account growth and member engagement. It is not for publishing or scheduling content to social media, not for accessing anyone\'s social media account without authorisation, and not for any unlawful purpose.',
            ],
        ],
        [
            'id' => 'menghubungkan-akun',
            'title' => '4. Connecting Facebook/Instagram Accounts',
            'items' => [
                'You may only connect a Facebook Page or Instagram account that you lawfully manage, or that its owner has authorised you to manage.',
                'By connecting, you allow :app to read that account\'s statistics as described in the Privacy Policy. You can withdraw this at any time.',
                'Use of Facebook Login and data from Meta is also subject to Meta\'s Terms of Service and Platform Terms.',
            ],
        ],
        [
            'id' => 'data-anggota',
            'title' => '5. Membership Figures',
            'items' => [
                'Division admins are responsible for the accuracy of the membership figures they upload, and Union admins for the figures they confirm.',
                'Upload files hold only the number of members per church. Don\'t upload names, contact details or other personal data of members.',
            ],
        ],
        [
            'id' => 'pihak-ketiga',
            'title' => '6. Data & Third Parties',
            'paragraphs' => [
                'Social media statistics come from data provided by the platforms concerned (Instagram, TikTok, Facebook, X, Threads, YouTube), either through third-party services or, for connected accounts, through official APIs (see the Privacy Policy). We are not responsible for the accuracy, availability, or policy changes of those platforms.',
            ],
        ],
        [
            'id' => 'perubahan-layanan',
            'title' => '7. Changes & Termination',
            'paragraphs' => [
                'We may change, suspend or discontinue part or all of the service at any time, including stopping the monitoring of a given platform, with or without notice. User accounts that break these terms may be deactivated by an admin.',
            ],
        ],
        [
            'id' => 'batasan',
            'title' => '8. Limitation of Liability',
            'paragraphs' => [
                'The service is provided "as is" without warranty of any kind. We are not liable for losses arising from inaccurate third-party data, service interruptions, or misuse of an account by unauthorised people due to the user\'s own failure to keep their credentials safe.',
            ],
        ],
        [
            'id' => 'hukum',
            'title' => '9. Governing Law',
            'paragraphs' => ['These terms are governed by the laws of the Republic of Indonesia.'],
        ],
        [
            'id' => 'kontak',
            'title' => '10. Contact',
            'paragraphs' => ['Questions about these terms can be sent to :email.'],
        ],
    ],
];
