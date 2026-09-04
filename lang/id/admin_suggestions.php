<?php

return [
    'tab_label' => 'Saran Admin',
    'title' => 'Saran Admin Gereja Baru',
    'subtitle' => 'Anggota yang mengetik nama Gereja baru (belum terdaftar) saat melengkapi profil — setujui untuk membuat gerejanya dan menjadikannya admin, atau tolak agar dia tetap jadi akun personal.',
    'none_pending' => 'Tidak ada saran admin yang menunggu persetujuan.',
    'col_requester' => 'Diajukan Oleh',
    'col_church_name' => 'Nama Gereja yang Diajukan',
    'col_submitted_at' => 'Diajukan Pada',
    'similar_warning' => 'Mungkin sudah terdaftar dengan nama lain (:count) — klik untuk lihat',
    'similar_disclaimer' => 'Ini hanya saran otomatis dari sistem berdasarkan kemiripan nama, bukan kepastian bahwa gereja ini benar-benar sudah terdaftar. Periksa sendiri sebelum memutuskan.',
    'approve' => 'Setujui',
    'reject' => 'Tolak',
    'approve_confirm' => 'Setujui ":name" sebagai admin Gereja ":church"? Gereja baru akan dibuat dan dia akan langsung bisa menambahkan akun media sosial untuk gereja tersebut.',
    'reject_confirm' => 'Tolak saran admin dari ":name"? Dia akan tetap menjadi akun personal biasa dan gereja yang diajukan tidak akan dibuat.',
    'approved' => 'Disetujui — ":name" sekarang admin Gereja ":church".',
    'rejected' => 'Saran admin dari ":name" ditolak.',
    'status_pending' => 'Menunggu',
    'status_approved' => 'Disetujui',
    'status_rejected' => 'Ditolak',

    // Email — dikirim hanya saat disetujui, tidak pernah saat ditolak.
    'mail_subject' => '[:app] Anda kini admin Gereja ":church"',
    'mail_greeting' => 'Halo :name,',
    'mail_body' => 'Kabar baik! Saran Anda untuk menjadi admin gereja berikut telah disetujui dan gerejanya baru saja dibuat di sistem:',
    'mail_next_steps' => 'Anda sekarang bisa masuk dan mulai menambahkan akun media sosial untuk gereja ini dari halaman gereja Anda.',
    'mail_cta' => 'Masuk ke Akun',
];
