<?php

return [
    'title' => 'Location Import',
    'subtitle' => 'Bulk-fill city and country via spreadsheet — for records still missing a map marker.',
    'missing_count' => ':count missing city/country',
    'download_template' => 'Download Template',
    'upload_button' => 'Upload & Save',
    'how_title' => 'How it works',
    'how_step_1' => 'Download the template — contains ID, Name, City, and Country for every record still missing city/country.',
    'how_step_2' => 'Fill in the City and Country columns in Excel/Google Sheets. Don\'t change the ID or Name columns.',
    'how_step_3' => 'Upload the filled-in file — a row with both City and Country still blank is skipped, nothing about it changes.',
    'how_step_4' => 'After uploading, run the matching geocode command (e.g. `php artisan churches:geocode`) via SSH to look up coordinates.',
    'result' => 'Import complete: :updated updated, :skippedBlank skipped (city/country still blank), :skippedNotFound skipped (ID not found).',
];
