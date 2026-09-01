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
    'how_step_4' => 'Koordinat lokasinya dicari otomatis di background setelah upload — tidak perlu langkah tambahan. Prosesnya bertahap (sekitar 1 lokasi per detik), jadi untuk data yang banyak bisa makan waktu beberapa menit sampai semua marker muncul di peta.',
    'result' => 'Import selesai: :updated data diperbarui, :skippedBlank dilewati (kota/negara masih kosong), :skippedNotFound dilewati (ID tidak ditemukan). :queued lokasi sedang dicari koordinatnya di background.',
];
