# Candramawa App — MVP 1.1 (User Management)

Laravel 13 + Inertia (Vue 3) + Tailwind 4. SQLite di localhost, MySQL saat live. Tampilan berbahasa Inggris.

Cakupan MVP 1.1: login, buat user, ubah user, ganti kata sandi, nonaktifkan user.
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

## Tes

```bash
php artisan test
```
