<?php

return [
    'updated_on' => 'Terakhir diperbarui: 1 September 2026',

    // --- Kebijakan Privasi ---
    'privacy_title' => 'Kebijakan Privasi',
    'privacy_intro' => ':app ("kami", "sistem") adalah dasbor pemantauan pertumbuhan akun media sosial untuk struktur organisasi gerejawi (Divisi/Uni/Daerah/Gereja), Institusi, dan Personal (individu). Kebijakan ini menjelaskan data apa yang kami kumpulkan, bagaimana data itu dipakai, dan hak Anda atas data tersebut.',

    'privacy_s1_title' => '1. Data yang Kami Kumpulkan',
    'privacy_s1_p1' => '<strong>Akun pengguna:</strong> nama, alamat email, kata sandi (disimpan dalam bentuk ter-hash, tidak pernah dalam bentuk teks biasa), serta catatan login (alamat IP, user agent peramban, waktu login/keluar).',
    'privacy_s1_p2' => '<strong>Data Personal (individu):</strong> nama, kota, negara, dan koordinat lokasi (opsional) yang dilaporkan sendiri oleh pengguna atau dimasukkan oleh admin di wilayahnya.',
    'privacy_s1_p3' => '<strong>Data akun media sosial:</strong> nama platform, handle/username, URL profil, dan <strong>statistik publik</strong> akun tersebut (jumlah pengikut/subscriber, views, likes, jumlah postingan) — bukan pesan pribadi, bukan konten privat, dan bukan kredensial login akun media sosial itu sendiri. Data ini diambil dari informasi yang sudah dipublikasikan secara terbuka oleh akun yang bersangkutan di platformnya masing-masing.',

    'privacy_s2_title' => '2. Izin Khusus untuk Akun Personal',
    'privacy_s2_p1' => 'Untuk akun media sosial milik <strong>Personal</strong> (individu), sistem <strong>tidak akan pernah</strong> mengambil data statistiknya tanpa persetujuan eksplisit dari yang bersangkutan (atau admin yang mengelola profil tersebut atas namanya) — ditandai lewat kotak centang izin saat akun ditambahkan. Tanpa izin ini, akun tetap tercatat di sistem namun datanya tidak pernah ditarik, baik secara otomatis maupun manual. Izin ini dapat dicabut kapan saja dengan menghubungi admin pengelola wilayah Anda, atau dengan menghapus akun media sosial tersebut dari sistem.',

    'privacy_s3_title' => '3. Bagaimana Data Digunakan',
    'privacy_s3_items' => [
        'Menampilkan grafik pertumbuhan, skor pertumbuhan, peringkat, dan perbandingan antar akun/wilayah.',
        'Menampilkan peta lokasi entitas dan direktori akun.',
        'Menyusun laporan/ekspor (PDF, Word, Excel) untuk keperluan internal organisasi.',
        'Mode presentasi untuk acara/rapat.',
    ],

    'privacy_s4_title' => '4. Siapa yang Dapat Melihat Data Anda',
    'privacy_s4_p1' => 'Akses ke data dibatasi berjenjang sesuai peran dan wilayah masing-masing admin (admin Gereja hanya melihat wilayahnya, admin Daerah/Uni/Divisi melihat wilayah di bawahnya, dan seterusnya) — bukan akses bebas ke seluruh data oleh siapa pun yang login. Direktori akun dan Analitik & Grafik yang sifatnya ringkasan publik dapat dilihat oleh sesama pengguna terdaftar di sistem.',

    'privacy_s5_title' => '5. Pihak Ketiga yang Terlibat',
    'privacy_s5_items' => [
        '<strong>Apify</strong> — pengambilan data publik Instagram, TikTok, Facebook, dan X.',
        '<strong>YouTube Data API v3</strong> (resmi, milik Google) — pengambilan data channel YouTube.',
        '<strong>OpenStreetMap Nominatim</strong> — geocoding nama kota menjadi koordinat peta.',
        'Penyedia layanan email — pengiriman kode verifikasi (OTP) ke email Anda.',
    ],

    'privacy_s6_title' => '6. Hak Anda atas Data',
    'privacy_s6_p1' => 'Anda berhak meminta koreksi, pembaruan, atau penghapusan data Anda dengan menghubungi admin pengelola wilayah/organisasi Anda, atau melalui kontak di bagian bawah halaman ini.',

    'privacy_s7_title' => '7. Pengguna dari Luar Indonesia',
    'privacy_s7_p1' => ':app melayani organisasi di luar Indonesia, khususnya di Asia Tenggara. Bagi pengguna yang berdomisili di negara lain, hukum perlindungan data di negara tersebut (mis. GDPR di Uni Eropa, PDPA di Thailand/Malaysia/Singapura, atau ketentuan setara lainnya) tetap dapat berlaku atas data Anda, di luar dan tanpa mengesampingkan kebijakan ini. Kami berupaya mengikuti prinsip umum perlindungan data yang berlaku luas — persetujuan sebelum pengambilan data, pembatasan akses berjenjang, dan hak koreksi/penghapusan data — terlepas dari negara asal pengguna.',

    'privacy_s8_title' => '8. Perubahan Kebijakan',
    'privacy_s8_p1' => 'Kebijakan ini dapat diperbarui sewaktu-waktu mengikuti perkembangan fitur aplikasi. Tanggal pembaruan terakhir selalu tercantum di bagian atas halaman ini.',

    'privacy_s9_title' => '9. Kontak',
    'privacy_s9_p1' => 'Pertanyaan seputar privasi data dapat disampaikan ke :email.',

    // --- Syarat Layanan ---
    'terms_title' => 'Syarat Layanan',
    'terms_intro' => 'Dengan mendaftar dan menggunakan :app, Anda menyetujui syarat-syarat berikut. Mohon dibaca sebelum menggunakan layanan ini.',

    'terms_s1_title' => '1. Kelayakan Pengguna',
    'terms_s1_p1' => 'Layanan ini disediakan untuk anggota, pelayan, dan admin organisasi gerejawi serta institusi terkait yang berwenang mengelola atau memantau data akun media sosial dalam struktur organisasi ini.',

    'terms_s2_title' => '2. Akun & Tanggung Jawab Pengguna',
    'terms_s2_items' => [
        'Anda bertanggung jawab menjaga kerahasiaan kata sandi akun Anda.',
        'Anda bertanggung jawab atas keakuratan data yang Anda masukkan (nama, wilayah, handle akun media sosial, dll.).',
        'Anda tidak boleh mendaftarkan akun media sosial milik orang lain tanpa izin pemiliknya.',
    ],

    'terms_s3_title' => '3. Penggunaan yang Diperbolehkan',
    'terms_s3_p1' => 'Layanan ini murni untuk memantau dan membandingkan pertumbuhan akun media sosial — bukan untuk menerbitkan/menjadwalkan konten ke media sosial, bukan untuk mengakses akun media sosial siapa pun secara tidak sah, dan bukan untuk tujuan yang melanggar hukum yang berlaku.',

    'terms_s4_title' => '4. Data & Pihak Ketiga',
    'terms_s4_p1' => 'Statistik akun media sosial diambil dari data publik yang disediakan oleh platform terkait (Instagram, TikTok, Facebook, X, YouTube) melalui layanan pihak ketiga (lihat Kebijakan Privasi). Kami tidak bertanggung jawab atas keakuratan, ketersediaan, atau perubahan kebijakan dari platform-platform tersebut.',

    'terms_s5_title' => '5. Perubahan & Penghentian Layanan',
    'terms_s5_p1' => 'Kami dapat mengubah, menangguhkan, atau menghentikan sebagian maupun seluruh fitur layanan ini sewaktu-waktu, termasuk menonaktifkan pemantauan platform tertentu, dengan atau tanpa pemberitahuan sebelumnya.',

    'terms_s6_title' => '6. Batasan Tanggung Jawab',
    'terms_s6_p1' => 'Layanan disediakan "sebagaimana adanya" tanpa jaminan apa pun. Kami tidak bertanggung jawab atas kerugian yang timbul dari ketidakakuratan data pihak ketiga, gangguan layanan, atau penyalahgunaan akun oleh pihak yang tidak berwenang akibat kelalaian pengguna sendiri dalam menjaga kredensial akunnya.',

    'terms_s7_title' => '7. Hukum yang Berlaku',
    'terms_s7_p1' => 'Syarat ini diatur oleh hukum Republik Indonesia.',

    'terms_s8_title' => '8. Kontak',
    'terms_s8_p1' => 'Pertanyaan seputar syarat layanan ini dapat disampaikan ke :email.',
];
