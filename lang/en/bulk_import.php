<?php

return [
    'title' => 'Bulk Import Church/Personal/Institution',
    'subtitle' => 'Add or update many records at once via spreadsheet — limited to the region you manage (same as Manage Accounts).',
    'visible_count' => ':count record(s) visible in your region',
    'download_template' => 'Download Template',
    'upload_button' => 'Upload & Save',
    'how_title' => 'How it works',
    'how_step_1' => 'Download the template — the "Data" sheet lists ID, Name, City, Country, Conference for everything already in your region, plus a few blank rows below for new entries.',
    'how_step_2' => 'To UPDATE an existing record: leave the ID as-is, edit City/Country. The Conference column is never changed this way.',
    'how_step_3' => 'To ADD a new record: leave ID blank, fill in Name (required). For Church, Conference is required and must match an existing conference in your region. For Personal/Institution, Conference may be left blank.',
    'how_step_4' => 'Church and Institution also get a second "Media Sosial" sheet — fill its first column with an existing church/institution\'s ID, OR the name of a new one you just added on the "Data" sheet (same file). Personal has no social sheet — a Personal\'s social account requires the owner\'s own consent for privacy reasons.',
    'how_step_5' => 'Upload the filled-in file. A row with missing or unmatched data is skipped, nothing about it changes.',
    'how_step_6' => 'After uploading, run the matching geocode command (e.g. `php artisan churches:geocode`) via SSH to look up coordinates for whatever location data this just filled in.',
    'result' => 'Import complete — Data: :created created, :updated updated, :skippedInvalid skipped (blank Name), :skippedDaerahNotFound skipped (Conference not found), :skippedNotFound skipped (ID not found). Social: :socialCreated created, :socialUpdated updated, :socialSkipped skipped.',
];
