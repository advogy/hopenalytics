<?php

return [
    'title' => 'Target Metrik',
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
    'page_title' => 'Tujuan',
    'page_subtitle' => 'Capaian target metrik media sosial dan keterlibatan anggota di wilayah Anda.',
    'metrics_title' => 'Target Metrik',
    'edit_targets' => 'Atur Target',
    'see_all' => 'Lihat selengkapnya',
    'engagement_summary' => 'Keterlibatan Anggota · :period',
    'engagement_summary_line' => ':active dari target :target anggota aktif (:percent% dari :members anggota tercatat)',
    'engagement_summary_unfilled' => 'Jumlah anggota periode ini belum diisi.',
    'tab_progress' => 'Capaian & Keterlibatan',
    'tab_members' => 'Daftar Anggota & Target',
    'members_subtitle' => 'Jumlah anggota tercatat tiap gereja dan target keterlibatannya (:percent%). Angka anggota diambil dari data terakhir yang sudah dikonfirmasi sampai :date.',
    'members_group' => 'Tampilkan per',
    'members_group_gereja' => 'Gereja',
    'members_group_daerah' => 'Daerah',
    'members_group_uni' => 'Uni',
    'members_all_conferences' => 'Semua Daerah',
    'members_search' => 'Cari nama',
    'members_search_placeholder' => 'mis. Menteng',
    'members_filled' => ':filled / :total gereja',
    'members_latest_as_of' => 'Data terbaru',
    'members_target_col' => 'Target (:percent%)',
    'members_export_subtitle' => 'Periode :period · target :percent% · per :group',
    'tab_progress_note' => 'Tab ini memantau kemajuan: berapa anggota yang sudah aktif dibanding targetnya, dan pertumbuhan media sosial tiap wilayah.',
    'tab_progress_link' => 'Hanya perlu jumlah anggota dan target tiap gereja? Buka Daftar Anggota & Target →',
    'tab_members_note' => 'Tab ini hanya berisi data dasar: jumlah anggota tercatat tiap gereja dan target keterlibatannya, tanpa statistik keaktifan.',
    'tab_members_link' => 'Ingin melihat berapa yang sudah aktif? Buka Capaian & Keterlibatan →',
];
