<?php

return [
    'title' => 'Manage Goals',
    'subtitle' => 'Set the global target for each metric. This target is split evenly across every Union, then each Union\'s share is split evenly again across its own Conferences — computed from church and institution data.',
    'metric_reach' => 'Followers / Subscribers',
    'metric_views' => 'Views',
    'metric_likes' => 'Likes',
    'metric_posts' => 'Post / Video',
    'target_year' => 'Target year',
    'target_value' => 'Target value',
    'save' => 'Save Goals',
    'saved' => 'Goals saved successfully.',
    'section_title' => 'Goals',
    'goal_title' => ':year :metric goal',
    'scope_global' => 'Global Level',
    'scope_nasional_scoped' => 'National Level: :names',
    'scope_divisi' => 'Division Level: :name',
    // No "Union" in front of :name — every real Union's own name already says "Uni" ("Union"),
    // so adding it too used to read as "Union Level: Uni Uni Indonesia...".
    'scope_uni' => 'Union Level: :name',
    'scope_daerah' => 'Conference Level: :name',

    // Same five levels as scope_* above, but just the bare level word with no value joined in —
    // used wherever the level and the region name(s) print as two separate lines instead of one
    // combined string (see ChurchDashboardController::index()'s own $regionScopeLabel).
    'level_global' => 'Global Level',
    'level_nasional' => 'National Level',
    'level_divisi' => 'Division Level',
    'level_uni' => 'Union Level',
    'level_daerah' => 'Conference Level',
];
