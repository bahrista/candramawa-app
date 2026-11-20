# Candramawa App

Laravel 13 + Inertia (Vue 3) + Tailwind 4. SQLite di localhost, MySQL saat live. Tampilan berbahasa Inggris.

Cakupan: login, user management, brand (tim, pillar, Knowledge Base, Products), dan Content Plan.
Jenis akun: **Superadmin** dan **User**. Spesifikasi lengkap ada di Google Sheet "Database User Management".

## Menjalankan di localhost

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-superadmin     # akun pertama, kata sandi lewat prompt tersembunyi
npm run build                         # atau: npm run dev
php artisan serve
```

Buka http://localhost:8000 lalu login dengan username Superadmin tadi.

## Aturan yang dipakai (usulan di tab Pertanyaan, menunggu konfirmasi)

- Boleh banyak Superadmin, tapi harus selalu ada minimal satu yang aktif.
- Superadmin tidak bisa menonaktifkan dirinya sendiri atau Superadmin terakhir.
- Tidak ada hapus user, hanya nonaktifkan (sesinya langsung diakhiri).
- Username: huruf kecil, angka, titik, garis bawah; 3–50 karakter; unik.
- Kata sandi minimal 8 karakter. Sesi 2 jam tanpa aktivitas, ada "Remember me".
- User boleh mengubah nama dan email sendiri; username hanya Superadmin.
- Kata sandi sementara (dibuat Superadmin) memaksa user ke halaman Profile sampai diganti.

## Brand

Menu **Brands**: setiap brand punya halaman dengan lima tab.

| Tab | Isi |
| --- | --- |
| Overview | Nama, deskripsi, logo, warna. |
| Team | Siapa yang menjadi **Brand Owner**, **Content Manager**, **Multimedia Creator**, **Copy Writer**, **Publisher**. Satu orang boleh memegang beberapa peran. |
| Content pillars | Tema konten brand. Mengisi dropdown *Content pillar* di Add Content Plan. Pillar yang sudah dipakai konten tidak bisa dihapus, hanya di-*archive*. |
| Knowledge Base | Catatan brand (brand story, tone of voice, target audience, dst). Kelak menjadi basis data AI. |
| Products | Produk/jasa brand. Kelak menjadi basis data AI. |

- Tim brand menjadi daftar orang yang bisa ditugaskan di sebuah Content Plan (Multimedia Creator, Copy Writer, Publisher).
- Yang bisa mengubah brand dan timnya: Superadmin, pembuat brand, dan Brand Owner.
  Content Manager brand ikut mengelola pillar, Knowledge Base, Products, dan boleh membuat konten untuk brand itu.
  Anggota tim lain hanya bisa melihat.
- Pembuat brand otomatis menjadi Brand Owner.
- Tiap entri Knowledge Base dan Product punya **link yang bisa disalin** (`/brands/{id}/knowledge/{id}` dan `/brands/{id}/products/{id}`).
  Link itu bisa ditempel ke *Reference* di brief konten.

## Content Plan

Menu **Content Plan** di sidebar berisi **Add Content Plan** dan **All Content**.

- Satu form untuk semua tombol: **Save Draft**, **Submit to Production**, **Submit to Review**
  (di tahap berikutnya juga **Save Changes** dan **Return to Production**).
- Alur status: Draft → In Production → In Review. Draft hanya terlihat oleh pembuatnya (Content Manager)
  dan Superadmin. Setelah di-submit ke produksi, orang yang ditugaskan (Multimedia Creator, Copy Writer,
  Publisher) ikut melihatnya dan mengisi *Post detail*. Anggota tim brand bisa melihat konten yang sudah tidak draft.
- **Content pillar** adalah dropdown dari tab *Content pillars* milik brand. Kolom *Format* sudah dihapus;
  laporan nanti memakai content type.
- **Content type** berbentuk pohon platform → tipe, bisa dilipat per platform, ada pencarian, *Expand all* / *Collapse all*.
  Satu konten boleh memilih banyak tipe; setiap tipe punya isian sendiri (media, caption, tag, catatan) dan pratinjau sendiri.

  | Platform | Tipe |
  | --- | --- |
  | Instagram | Single Post, Carousel, Reel, Story |
  | Facebook | Feed, Reel, Story |
  | TikTok | Video, Carousel |
  | Other | Flyer, Sales Kit |

  Menambah platform atau tipe baru (Threads, X, YouTube, dst) cukup dengan menambah baris di `config/content_types.php`.
  Aturan tiap tipe (jumlah media, caption wajib atau tidak, batas karakter, bentuk pratinjau) ada di file yang sama.
- **Reference** di brief boleh berisi link biasa, entri Knowledge Base, atau Product dari brand yang sama.
- **Publish kit** (untuk Publisher): tombol **Copy caption** (caption + tag siap tempel), **Download** per file dan **Download all**.
  Browser bisa meminta izin pop-up untuk *Download all*.
- **Share**: tombol Share membuat tiga link untuk konten yang sama, masing-masing langsung membuka tab yang tepat:
  `?view=production` (brief, untuk Multimedia Creator dan Copy Writer), `?view=approval` (untuk Content Manager dan Brand Owner),
  `?view=publisher` (Publish kit). Ada tombol salin link, kirim lewat WhatsApp, dan salin pesan.
  Penerima harus login dan menjadi anggota tim brand atau orang yang ditugaskan; selain itu mereka melihat halaman "no access".
- Tanggal dan jam tampil dalam zona waktu `CONTENT_TIMEZONE` (bawaan `Asia/Jakarta`, WIB); disimpan sebagai UTC.
  Deadline otomatis = tanggal publish dikurangi 24 jam (`config/content.php`).
- Pratinjau memuat file Google Drive. File harus dibagikan sebagai "Anyone with the link"
  (atau pelihat sudah login ke akun Google yang punya akses). Hanya link `drive.google.com` (https) yang diterima.

### Memperbarui dari versi sebelumnya

Setelah mengambil kode terbaru jalankan:

```bash
php artisan migrate
npm run build
```

Migrasi mengubah data lama dengan aman: *Post type* lama menjadi *content type* (`instagram_feed` menjadi `instagram_single`),
Content Pillar yang dulu berupa teks bebas dijadikan pillar milik brand, kolom *Format* dihapus,
dan pembuat setiap brand ditambahkan sebagai Brand Owner.

## Tes

```bash
php artisan test      # PHP
npm run test:js       # JavaScript (link Google Drive, tanggal, content type, caption)
```
