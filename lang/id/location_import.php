<?php

return [
    'title' => 'Import Lokasi',
    'subtitle' => 'Isi kota dan negara secara massal lewat spreadsheet — dipakai untuk melengkapi data yang belum punya marker di peta.',
    'missing_count' => ':count belum punya kota/negara',
    'download_template' => 'Download Template',
    'upload_button' => 'Upload & Simpan',
    'how_title' => 'Cara pakai',
    'how_step_1' => 'Download template — berisi ID, Nama, Kota, dan Negara untuk semua data yang kota/negaranya masih kosong.',
    'how_step_2' => 'Isi kolom Kota dan Negara di Excel/Google Sheets. Jangan ubah kolom ID atau Nama.',
    'how_step_3' => 'Upload kembali file yang sudah diisi — baris yang kota dan negaranya masih kosong akan dilewati, tidak akan mengubah apapun.',
    'how_step_4' => 'Setelah upload, jalankan perintah geocode (misalnya `php artisan churches:geocode`) lewat SSH untuk mencari koordinatnya.',
    'result' => 'Import selesai: :updated data diperbarui, :skippedBlank dilewati (kota/negara masih kosong), :skippedNotFound dilewati (ID tidak ditemukan).',
];
