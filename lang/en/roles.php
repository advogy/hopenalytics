<?php

// Keyed by UserRole's own backing enum value — see UserRole::label(). "Pimpinan" (a read-only
// counterpart to each Admin level) translates to "Leader" — the org-level nouns themselves
// (Union/Conference/Church/Institution/Division/National/Global) match this app's established
// English vocabulary for those terms elsewhere (see e.g. lang/en/goals.php's own scope_* labels).
return [
    'superadmin' => 'Superadmin',
    'admin_global' => 'Global Admin',
    'admin_nasional' => 'National Admin',
    'admin_divisi' => 'Division Admin',
    'admin_uni' => 'Union Admin',
    'admin_daerah' => 'Conference Admin',
    'admin_gereja' => 'Church Admin',
    'admin_institusi' => 'Institution Admin',
    'pimpinan_global' => 'Global Leader',
    'pimpinan_nasional' => 'National Leader',
    'pimpinan_divisi' => 'Division Leader',
    'pimpinan_uni' => 'Union Leader',
    'pimpinan_daerah' => 'Conference Leader',
    'pimpinan_gereja' => 'Church Leader',
    'pimpinan_institusi' => 'Institution Leader',
];
