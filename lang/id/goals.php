<?php

return [
    'title' => 'Kelola Tujuan',
    'subtitle' => 'Atur target global untuk setiap metrik. Target ini dibagi rata ke setiap Uni, lalu bagian tiap Uni dibagi rata lagi ke setiap Daerah di bawahnya — dihitung dari data gereja dan institusi.',
    'metric_reach' => 'Followers / Subscribers',
    'metric_views' => 'Views',
    'metric_likes' => 'Likes',
    'metric_posts' => 'Post / Video',
    'target_year' => 'Tahun target',
    'target_value' => 'Target nilai',
    'save' => 'Simpan Tujuan',
    'saved' => 'Tujuan berhasil disimpan.',
    'section_title' => 'Tujuan',
    'goal_title' => ':year Tujuan :metric',
    'scope_global' => 'Level Global',
    'scope_nasional_scoped' => 'Level Nasional: :names',
    'scope_divisi' => 'Level Divisi: :name',
    // No "Uni " before :name — every real Union's own name already starts with "Uni" (e.g. "Uni
    // Indonesia Kawasan Barat"), so prefixing it too used to read as "Level Uni: Uni Uni
    // Indonesia...".
    'scope_uni' => 'Level Uni: :name',
    'scope_daerah' => 'Level Daerah: :name',

    // Same five levels as scope_* above, but just the bare level word with no value joined in —
    // used wherever the level and the region name(s) print as two separate lines instead of one
    // combined string (see ChurchDashboardController::index()'s own $regionScopeLabel).
    'level_global' => 'Level Global',
    'level_nasional' => 'Level Nasional',
    'level_divisi' => 'Level Divisi',
    'level_uni' => 'Level Uni',
    'level_daerah' => 'Level Daerah',
];
