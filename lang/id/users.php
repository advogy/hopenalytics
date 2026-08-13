<?php

return [
    'subtitle_any_level' => 'Tugaskan anggota terdaftar sebagai admin/pimpinan di level manapun, atau cabut/ubah penugasan yang sudah ada.',
    'subtitle_own_level' => 'Tugaskan anggota terdaftar sebagai admin/pimpinan :level, atau cabut/ubah penugasan yang sudah ada.',

    'tab_unassigned' => 'Belum Ditugaskan',
    'tab_admin' => 'Admin',
    'tab_pemimpin' => 'Pemimpin',
    'tab_terhapus' => 'Terhapus',

    'unassigned_title' => 'Anggota Belum Ditugaskan',
    'unassigned_subtitle' => 'Anggota terdaftar yang belum memiliki peran apapun.',
    'search_placeholder' => 'Cari nama atau email…',
    'no_match' => 'Tidak ada anggota yang cocok.',
    'col_user' => 'Pengguna',
    'col_assign' => 'Tugaskan',
    'assign' => 'Tugaskan',
    'search_scope_placeholder' => 'Cari…',
    'search_scope_for' => 'Cari :level…',
    'select_unions' => 'Pilih Uni…',
    'unions_selected_count' => ':count Uni dipilih',

    'admin_all_title' => 'Semua Admin',
    'admin_level_title' => 'Admin :level',
    'admin_subtitle' => 'Admin yang sedang aktif mengelola wilayahnya masing-masing.',
    'no_admin_yet' => 'Belum ada admin yang ditugaskan.',
    'col_role' => 'Peran',
    'col_scope' => 'Cakupan',
    'revoke_confirm' => 'Cabut peran ":name"? Mereka akan kembali menjadi anggota biasa.',
    'revoke' => 'Cabut',
    'release_region' => 'Lepas Wilayah',
    'release_region_confirm' => 'Lepas data wilayah (Uni/Daerah/Gereja) dari ":name"?',
    'release_region_confirm_active_role' => 'Lepas data wilayah dari ":name"? Peran :role akan kehilangan cakupan wilayah kerja sampai ditugaskan ulang.',

    'pemimpin_all_title' => 'Semua Pemimpin',
    'pemimpin_level_title' => 'Pemimpin :level',
    'pemimpin_subtitle' => 'Pemimpin dengan akses lihat-saja di wilayahnya masing-masing.',
    'no_pemimpin_yet' => 'Belum ada pemimpin yang ditugaskan.',

    'institusi_admin_title' => 'Admin & Pimpinan Institusi',
    'institusi_admin_subtitle_prefix' => 'Institusi berdiri sendiri, tidak di bawah Uni/Daerah — kelola daftarnya di',
    'no_institusi_admin_yet' => 'Belum ada Admin/Pimpinan Institusi.',

    'trashed_title' => 'Pengguna Terhapus',
    'trashed_subtitle' => 'Akun yang sudah "dihapus" (soft delete) — baris ini masih ada di database dan bisa saja masih menyangkut penugasan lama. Pulihkan kalau salah hapus, atau hapus permanen untuk benar-benar membersihkannya.',
    'no_trashed' => 'Tidak ada pengguna yang terhapus.',
    'col_deleted_at' => 'Dihapus Pada',
    'restore' => 'Pulihkan',
    'force_delete' => 'Hapus Permanen',
    'force_delete_confirm' => 'Hapus permanen ":name"? Tindakan ini tidak bisa dibatalkan.',

    'edit_title' => 'Edit Akun Pengguna',
    'edit_name_label' => 'Nama Akun',
    'edit_name_hint' => 'Nama login/tampilan akun ini. Berbeda dari nama profil Personal (Direktori Akun), yang diedit terpisah lewat Kelola Akun.',

    'this_is_you' => 'Ini akun Anda',

    'resend_otp' => 'Kirim Ulang OTP',
    'deactivate_user_confirm' => 'Nonaktifkan ":name"? Mereka tidak akan bisa login sampai diaktifkan kembali.',
    'delete_user_confirm' => 'Hapus ":name"? Akun ini akan hilang dari semua daftar.',
    'verified' => 'Terverifikasi',
    'pending_verification' => 'Menunggu Verifikasi',

    // Flash messages.
    'assigned' => '":name" berhasil ditugaskan.',
    'role_revoked' => 'Peran ":name" telah dicabut.',
    'region_released' => 'Wilayah ":name" telah dilepas.',
    'region_released_bulk_none' => 'Tidak ada pengguna belum ditugaskan yang dilepas.',
    'region_released_bulk' => 'Wilayah dari :count pengguna belum ditugaskan telah dilepas (:names).',
    'user_deleted' => '":name" telah dihapus.',
    'user_restored' => '":name" berhasil dipulihkan.',
    'user_force_deleted' => '":name" berhasil dihapus permanen.',
    'user_updated' => '":name" berhasil diperbarui.',
    'user_status_changed' => '":name" telah :status.',
    'otp_resent_to' => 'Kode OTP baru telah dikirim ke :email.',
];
