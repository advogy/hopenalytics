<?php

return [
    'title' => 'Bulk Import Gereja/Personal/Institusi',
    'subtitle' => 'Tambah atau ubah banyak data sekaligus lewat spreadsheet — dibatasi ke wilayah yang bisa Anda kelola (sama seperti Kelola Akun).',
    'visible_count' => ':count data terlihat di wilayah Anda',
    'download_template' => 'Download Template',
    'upload_button' => 'Upload & Simpan',
    'how_title' => 'Cara pakai',
    'how_step_1' => 'Download template — sheet "Data" berisi ID, Nama, Kota, Negara, Daerah untuk semua data yang sudah ada di wilayah Anda, plus beberapa baris kosong di bawahnya untuk data baru.',
    'how_step_2' => 'Untuk MENGUBAH data yang sudah ada: biarkan kolom ID seperti apa adanya, ubah Kota/Negara-nya. Kolom Daerah tidak akan diubah lewat cara ini.',
    'how_step_3' => 'Untuk MENAMBAH data baru: kosongkan kolom ID, isi Nama (wajib). Untuk Gereja, kolom Daerah wajib diisi dengan nama Daerah yang sudah ada di wilayah Anda. Untuk Personal/Institusi, Daerah boleh dikosongkan.',
    'how_step_4' => 'Untuk Gereja dan Institusi, ada sheet kedua "Media Sosial" — isi kolom pertama dengan ID gereja/institusi yang sudah ada, ATAU nama gereja/institusi baru yang Anda tambahkan di sheet "Data" (di baris yang sama file ini). Personal tidak punya sheet media sosial — akun media sosial personal wajib dikonfirmasi langsung oleh pemiliknya karena alasan privasi.',
    'how_step_5' => 'Upload kembali file yang sudah diisi. Baris yang datanya tidak lengkap atau tidak ditemukan akan dilewati, tidak akan mengubah apapun.',
    'how_step_6' => 'Setelah upload, jalankan perintah geocode (misalnya `php artisan churches:geocode`) lewat SSH untuk mencari koordinat lokasi yang baru diisi.',
    'result' => 'Import selesai — Data: :created dibuat, :updated diperbarui, :skippedInvalid dilewati (Nama kosong), :skippedDaerahNotFound dilewati (Daerah tidak ditemukan), :skippedNotFound dilewati (ID tidak ditemukan). Media Sosial: :socialCreated dibuat, :socialUpdated diperbarui, :socialSkipped dilewati.',
];
