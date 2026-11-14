<?php

// Kebijakan Privasi & Syarat Layanan (partials/privacy-content, partials/terms-content).
// Each section: 'id' (anchor), 'title', optional 'paragraphs' and 'items' (HTML allowed);
// ':app' and ':email' are filled in by the view.
return [
    'updated_on' => 'Terakhir diperbarui: 13 November 2026',

    // --- Kebijakan Privasi ---
    'privacy_title' => 'Kebijakan Privasi',
    'privacy_intro' => ':app ("kami", "sistem") adalah dasbor pemantauan pertumbuhan akun media sosial untuk struktur organisasi gerejawi (Divisi/Uni/Daerah/Gereja), Institusi, dan Personal (individu). Kebijakan ini menjelaskan data apa yang kami kumpulkan, bagaimana data itu dipakai, berapa lama disimpan, dan hak Anda atas data tersebut, termasuk cara menghapusnya.',

    'privacy_sections' => [
        [
            'id' => 'data-dikumpulkan',
            'title' => '1. Data yang Kami Kumpulkan',
            'paragraphs' => [
                '<strong>Akun pengguna:</strong> nama, alamat email, kata sandi (disimpan dalam bentuk ter-hash, tidak pernah dalam bentuk teks biasa), peran dan wilayah tugas, serta catatan login (alamat IP, user agent peramban, waktu login/keluar).',
                '<strong>Data Personal (individu):</strong> nama, kota, negara, koordinat lokasi (opsional), serta Uni, Daerah, dan gereja yang dipilih sendiri oleh pengguna atau dimasukkan oleh admin di wilayahnya.',
                '<strong>Data akun media sosial:</strong> nama platform, handle/username, URL profil, dan <strong>statistik publik</strong> akun tersebut: jumlah pengikut/subscriber, views, likes, komentar, bagikan, jumlah postingan, serta contoh beberapa postingan terbaru (teks, tanggal, dan jumlah interaksinya) untuk pencocokan hashtag. Kami tidak mengambil pesan pribadi, konten privat, maupun kata sandi akun media sosial.',
                '<strong>Data keanggotaan gereja:</strong> jumlah anggota tercatat tiap gereja per tanggal tertentu, yang diunggah oleh admin divisi dan dikonfirmasi oleh admin uni. Data ini berupa <strong>angka jumlah</strong>, bukan daftar nama atau data pribadi anggota.',
                '<strong>Akun yang dihubungkan:</strong> bila pemilik Halaman Facebook atau akun Instagram Business/Creator memilih menghubungkannya (lihat bagian 3), kami menyimpan ID dan nama Halaman/akun tersebut serta token akses yang diberikan Meta, dalam bentuk terenkripsi.',
            ],
        ],
        [
            'id' => 'izin-personal',
            'title' => '2. Izin Khusus untuk Akun Personal',
            'paragraphs' => [
                'Untuk akun media sosial milik <strong>Personal</strong> (individu), sistem <strong>tidak akan pernah</strong> mengambil data statistiknya tanpa persetujuan eksplisit dari yang bersangkutan (atau admin yang mengelola profil tersebut atas namanya), yang ditandai lewat kotak centang izin saat akun ditambahkan. Tanpa izin ini, akun tetap tercatat namun datanya tidak pernah ditarik, dan tidak ikut dihitung dalam skor pertumbuhan gabungan maupun keterlibatan anggota.',
                'Izin ini dapat dicabut kapan saja dengan menghapus akun media sosial tersebut dari Profil Saya, atau dengan menghubungi admin pengelola wilayah Anda.',
            ],
        ],
        [
            'id' => 'akun-dihubungkan',
            'title' => '3. Akun yang Dihubungkan lewat Meta (Facebook/Instagram)',
            'paragraphs' => [
                'Pemilik atau admin sebuah Halaman Facebook, atau akun Instagram Business/Creator yang ditautkan ke Halaman tersebut, dapat <strong>menghubungkan</strong> akunnya lewat login Facebook. Dengan begitu, statistiknya dibaca langsung dari Graph API resmi Meta, bukan diambil dari halaman publik.',
                'Saat menghubungkan, Anda memberi izin kepada :app untuk: melihat daftar Halaman yang Anda kelola (<em>pages_show_list</em>), membaca jumlah pengikut dan statistik postingan Halaman (<em>pages_read_engagement</em>), serta membaca profil dan statistik akun Instagram Business/Creator yang tertaut (<em>instagram_basic</em>, <em>instagram_manage_insights</em>).',
                'Kami <strong>hanya membaca</strong>. Kami tidak memposting, tidak mengubah apa pun di Halaman atau akun Anda, tidak membaca pesan, dan tidak menyimpan data profil Facebook pribadi Anda. Token akses disimpan terenkripsi dan hanya dipakai untuk membaca statistik akun yang Anda pilih.',
                'Hubungan ini dapat diputus kapan saja lewat tombol <strong>Putuskan</strong> di halaman Edit Akun, atau dengan menghapus :app dari <em>Facebook → Pengaturan → Integrasi Bisnis</em> (atau <em>Aplikasi dan Situs Web</em>). Saat diputus dari :app, token langsung dihapus dari sistem kami.',
            ],
        ],
        [
            'id' => 'penggunaan-data',
            'title' => '4. Bagaimana Data Digunakan',
            'items' => [
                'Menampilkan grafik pertumbuhan, skor pertumbuhan (termasuk pertumbuhan gabungan gereja dan akun personal jemaatnya), peringkat, dan perbandingan antar akun/wilayah.',
                'Menghitung keterlibatan anggota: jumlah anggota tercatat, target per periode, dan berapa anggota yang aktif (terdaftar di sistem, punya akun media sosial terdaftar, atau memposting dalam periode).',
                'Menampilkan peta lokasi entitas dan direktori akun.',
                'Menyusun laporan/ekspor (PDF, Word, Excel) untuk keperluan internal organisasi.',
                'Mode presentasi untuk acara/rapat.',
                'Memilih bahasa tampilan pada kunjungan pertama berdasarkan lokasi negara dari alamat IP (Indonesia atau bukan). Alamat IP tidak disimpan untuk keperluan ini.',
            ],
        ],
        [
            'id' => 'akses-data',
            'title' => '5. Siapa yang Dapat Melihat Data Anda',
            'paragraphs' => [
                'Akses ke data dibatasi berjenjang sesuai peran dan wilayah masing-masing admin: admin Gereja hanya melihat gereja yang dikelolanya dan Daerahnya, admin Daerah/Uni/Divisi melihat wilayah di bawahnya, dan seterusnya. Data jumlah anggota hanya dapat diunggah oleh admin divisi dan dikonfirmasi oleh admin uni di wilayahnya.',
                'Direktori akun dan Analitik & Grafik yang sifatnya ringkasan dapat dilihat oleh sesama pengguna terdaftar di sistem.',
            ],
        ],
        [
            'id' => 'pihak-ketiga',
            'title' => '6. Pihak Ketiga yang Terlibat',
            'items' => [
                '<strong>Apify</strong>: pengambilan data publik Instagram, TikTok, Facebook, X, dan Threads untuk akun yang tidak dihubungkan.',
                '<strong>Meta Platforms</strong> (Facebook Login dan Graph API): login dan pembacaan statistik untuk akun Facebook/Instagram yang dihubungkan oleh pemiliknya. Penggunaan Facebook juga tunduk pada kebijakan privasi Meta.',
                '<strong>YouTube Data API v3</strong> (resmi, milik Google): pengambilan data channel YouTube.',
                '<strong>OpenStreetMap Nominatim</strong>: geocoding nama kota menjadi koordinat peta.',
                'Penyedia layanan email: pengiriman kode verifikasi (OTP) dan pemberitahuan ke email Anda.',
                'Penyedia hosting: tempat aplikasi dan basis data dijalankan.',
            ],
        ],
        [
            'id' => 'penyimpanan',
            'title' => '7. Lama Penyimpanan Data',
            'items' => [
                'Catatan login disimpan selama <strong>6 bulan</strong>, lalu dihapus otomatis.',
                'Log audit tindakan admin disimpan selama <strong>12 bulan</strong>, lalu dihapus otomatis.',
                'Statistik akun media sosial dan data jumlah anggota disimpan selama akun atau gereja tersebut terdaftar, karena dipakai untuk grafik riwayat pertumbuhan.',
                'Token akun yang dihubungkan disimpan hanya selama hubungan itu aktif, dan langsung dihapus saat diputus.',
                'Sesi login (cookie) berakhir setelah tidak aktif. Kami tidak memakai cookie iklan atau pelacak pihak ketiga.',
            ],
        ],
        [
            'id' => 'hak-anda',
            'title' => '8. Hak Anda atas Data',
            'paragraphs' => [
                'Anda berhak meminta salinan, koreksi, pembaruan, atau penghapusan data Anda. Sebagian besar data dapat Anda ubah sendiri lewat Profil Saya; selebihnya dapat dimintakan kepada admin pengelola wilayah/organisasi Anda, atau melalui kontak di bagian bawah halaman ini.',
            ],
        ],
        [
            'id' => 'hapus-data',
            'title' => '9. Cara Menghapus Data Anda',
            'paragraphs' => [
                'Untuk memutus akun Facebook/Instagram yang dihubungkan: buka <strong>Edit Akun</strong> untuk akun tersebut di :app lalu klik <strong>Putuskan</strong>, atau hapus :app dari <em>Facebook → Pengaturan → Integrasi Bisnis</em>. Token akses langsung dihapus.',
                'Untuk menghapus akun media sosial Anda dari pemantauan: hapus akun tersebut dari <strong>Profil Saya → Media Sosial</strong>. Datanya tidak lagi diambil.',
                'Untuk menghapus seluruh data Anda, termasuk akun pengguna, data Personal, dan riwayat statistik akun media sosial Anda: kirim email ke :email dengan subjek <strong>"Hapus Data"</strong> dari alamat email yang terdaftar. Kami akan memverifikasi permintaan dan menghapus data tersebut paling lambat <strong>30 hari</strong> sejak permintaan diterima, lalu mengonfirmasinya lewat email. Data jumlah anggota gereja yang berupa angka agregat tidak termasuk data pribadi dan tetap disimpan oleh organisasi.',
            ],
        ],
        [
            'id' => 'luar-indonesia',
            'title' => '10. Pengguna dari Luar Indonesia',
            'paragraphs' => [
                ':app melayani organisasi di luar Indonesia, khususnya di Asia Tenggara. Bagi pengguna yang berdomisili di negara lain, hukum perlindungan data di negara tersebut (mis. GDPR di Uni Eropa, PDPA di Thailand/Malaysia/Singapura, atau ketentuan setara lainnya) tetap dapat berlaku atas data Anda, di luar dan tanpa mengesampingkan kebijakan ini. Kami juga mengikuti Undang-Undang Pelindungan Data Pribadi (UU PDP) Republik Indonesia.',
            ],
        ],
        [
            'id' => 'perubahan',
            'title' => '11. Perubahan Kebijakan',
            'paragraphs' => [
                'Kebijakan ini dapat diperbarui sewaktu-waktu mengikuti perkembangan fitur aplikasi. Tanggal pembaruan terakhir selalu tercantum di bagian atas halaman ini.',
            ],
        ],
        [
            'id' => 'kontak',
            'title' => '12. Kontak',
            'paragraphs' => [
                'Pertanyaan atau permintaan seputar privasi data dapat disampaikan ke :email.',
            ],
        ],
    ],

    // --- Syarat Layanan ---
    'terms_title' => 'Syarat Layanan',
    'terms_intro' => 'Dengan mendaftar dan menggunakan :app, Anda menyetujui syarat-syarat berikut. Mohon dibaca sebelum menggunakan layanan ini.',

    'terms_sections' => [
        [
            'id' => 'kelayakan',
            'title' => '1. Kelayakan Pengguna',
            'paragraphs' => [
                'Layanan ini disediakan untuk anggota, pelayan, dan admin organisasi gerejawi serta institusi terkait yang berwenang mengelola atau memantau data akun media sosial dalam struktur organisasi ini.',
            ],
        ],
        [
            'id' => 'tanggung-jawab',
            'title' => '2. Akun & Tanggung Jawab Pengguna',
            'items' => [
                'Anda bertanggung jawab menjaga kerahasiaan kata sandi akun Anda.',
                'Anda bertanggung jawab atas keakuratan data yang Anda masukkan (nama, wilayah, gereja, handle akun media sosial, dll.).',
                'Anda tidak boleh mendaftarkan akun media sosial milik orang lain tanpa izin pemiliknya.',
                'Admin bertanggung jawab memakai hak aksesnya hanya untuk wilayah dan gereja yang memang ditugaskan kepadanya.',
            ],
        ],
        [
            'id' => 'penggunaan',
            'title' => '3. Penggunaan yang Diperbolehkan',
            'paragraphs' => [
                'Layanan ini murni untuk memantau dan membandingkan pertumbuhan akun media sosial serta keterlibatan anggota. Layanan ini bukan untuk menerbitkan atau menjadwalkan konten ke media sosial, bukan untuk mengakses akun media sosial siapa pun secara tidak sah, dan bukan untuk tujuan yang melanggar hukum yang berlaku.',
            ],
        ],
        [
            'id' => 'menghubungkan-akun',
            'title' => '4. Menghubungkan Akun Facebook/Instagram',
            'items' => [
                'Anda hanya boleh menghubungkan Halaman Facebook atau akun Instagram yang Anda kelola secara sah, atau yang Anda diberi wewenang untuk mengelolanya oleh pemiliknya.',
                'Dengan menghubungkan, Anda mengizinkan :app membaca statistik akun tersebut seperti dijelaskan di Kebijakan Privasi. Izin ini dapat Anda cabut kapan saja.',
                'Penggunaan Facebook Login dan data dari Meta juga tunduk pada Ketentuan Layanan dan Kebijakan Platform Meta.',
            ],
        ],
        [
            'id' => 'data-anggota',
            'title' => '5. Data Jumlah Anggota',
            'items' => [
                'Admin divisi bertanggung jawab atas keakuratan jumlah anggota yang diunggah, dan admin uni atas angka yang dikonfirmasinya.',
                'File unggahan hanya berisi jumlah anggota per gereja. Jangan mengunggah nama, kontak, atau data pribadi anggota lainnya.',
            ],
        ],
        [
            'id' => 'pihak-ketiga',
            'title' => '6. Data & Pihak Ketiga',
            'paragraphs' => [
                'Statistik akun media sosial diambil dari data yang disediakan oleh platform terkait (Instagram, TikTok, Facebook, X, Threads, YouTube), baik lewat layanan pihak ketiga maupun lewat API resmi untuk akun yang dihubungkan (lihat Kebijakan Privasi). Kami tidak bertanggung jawab atas keakuratan, ketersediaan, atau perubahan kebijakan dari platform-platform tersebut.',
            ],
        ],
        [
            'id' => 'perubahan-layanan',
            'title' => '7. Perubahan & Penghentian Layanan',
            'paragraphs' => [
                'Kami dapat mengubah, menangguhkan, atau menghentikan sebagian maupun seluruh fitur layanan ini sewaktu-waktu, termasuk menonaktifkan pemantauan platform tertentu, dengan atau tanpa pemberitahuan sebelumnya. Akun pengguna yang melanggar syarat ini dapat dinonaktifkan oleh admin.',
            ],
        ],
        [
            'id' => 'batasan',
            'title' => '8. Batasan Tanggung Jawab',
            'paragraphs' => [
                'Layanan disediakan "sebagaimana adanya" tanpa jaminan apa pun. Kami tidak bertanggung jawab atas kerugian yang timbul dari ketidakakuratan data pihak ketiga, gangguan layanan, atau penyalahgunaan akun oleh pihak yang tidak berwenang akibat kelalaian pengguna sendiri dalam menjaga kredensial akunnya.',
            ],
        ],
        [
            'id' => 'hukum',
            'title' => '9. Hukum yang Berlaku',
            'paragraphs' => ['Syarat ini diatur oleh hukum Republik Indonesia.'],
        ],
        [
            'id' => 'kontak',
            'title' => '10. Kontak',
            'paragraphs' => ['Pertanyaan seputar syarat layanan ini dapat disampaikan ke :email.'],
        ],
    ],
];
