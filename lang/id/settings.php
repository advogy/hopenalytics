<?php

return [
    'title' => 'Pengaturan',
    'subtitle' => 'Atur jadwal auto-fetch mingguan.',
    'auto_fetch_active' => 'Auto-fetch mingguan aktif',
    'day' => 'Hari',
    'time_wib' => 'Jam (WIB)',
    'next_fetch' => 'Fetch berikutnya:',
    'auto_fetch_inactive' => 'Auto-fetch mingguan sedang nonaktif.',
    'save_settings' => 'Simpan Pengaturan',
    'schedule_note' => 'Jadwal ini memicu perintah :command, yang mengambil data terbaru untuk semua akun auto-fetch (kecuali yang ditandai manual).',
    'days' => ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],

    'cs_title' => 'Koordinator Nasional',
    'cs_subtitle' => 'Nomor dan tautan ini dipakai sebagai default oleh tombol Customer Service di kanan bawah setiap halaman — dipakai untuk siapapun yang wilayah Uni-nya belum mengisi Koordinator Uni sendiri (diatur lewat Kelola Akun → Uni).',
    'cs_whatsapp_number' => 'Nomor WhatsApp Koordinator Nasional',
    'cs_whatsapp_number_hint' => 'Format internasional tanpa "+" atau angka 0 di depan, contoh: 628123456789. Kosongkan untuk menyembunyikan tautan ini.',
    'cs_whatsapp_group_link' => 'Tautan Grup WhatsApp Nasional',
    'cs_whatsapp_group_link_hint' => 'Tautan undangan grup WhatsApp (chat.whatsapp.com/…). Kosongkan untuk menyembunyikan tautan ini.',

    'apify_title' => 'Fallback Pengambilan Data',
    'apify_subtitle' => 'Instagram, TikTok, dan Facebook diambil lewat layanan pihak ketiga (Apify) yang memakai kredit berbayar. YouTube tidak terpengaruh — datanya diambil lewat API resmi YouTube yang gratis.',
    'apify_fallback_to_manual' => 'Jika kredit Apify habis, otomatis tandai akun untuk isi data manual',
];
