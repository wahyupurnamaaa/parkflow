# Cakupan implementasi terhadap PRD

## MVP 1 tersedia
- Login/logout, perubahan password, CSRF, rate limiting, backend role admin/officer.
- Dashboard KPI, grafik pendapatan tujuh hari, okupansi, area dan sesi terkini.
- Lokasi, tambah area sekaligus slot, status perawatan/nonaktif.
- Data kendaraan otomatis dari check-in.
- Check-in atomik, auto assignment, tiket QR, tampilan tiket publik, cetak tiket.
- Check-out, verifikasi kendaraan, tarif backend, denda tiket hilang, cash/change, struk.
- Snapshot tarif, idempotency, unique constraints, audit aktivitas sensitif.
- Daftar sesi, pencarian plat/tiket/slot, riwayat transaksi dan pagination.
- Laporan periode, CSV yang dapat dibuka Excel, cetak/simpan PDF melalui browser.
- UI Bahasa Indonesia responsif; polling 15 detik.
- Automated unit/integration test, browser smoke test, Docker Compose, CI, API/ERD/docs.

## Belum diimplementasikan / roadmap V2
- Forgot/reset password via email, verifikasi email, Google OAuth, 2FA.
- CRUD akun petugas, granular permissions, assignment lokasi, shift/cashier closing.
- Membership, reservation, diskon benefit.
- Payment gateway sandbox, status asynchronous, webhook, refund.
- WebSocket/SSE, queue jobs untuk email/report, notifikasi persist/email.
- PWA install/offline, camera scanner (saat ini input token/URL atau scanner keyboard).
- Laporan XLSX/PDF server-side, occupancy/staff reports terpisah; ekspor saat ini CSV/print PDF.
- Semua API dan URL halaman lanjutan PRD: navigasi MVP ada di satu halaman operasional dengan tiket publik `/ticket/{token}`.
- Filter lintas tenant, multi-tenant SaaS, IoT/sensor/gate hardware.
- HTTPS publik, live deployment, repository GitHub, hasil load 100/500/1000 user.

## Langkah berikutnya
1. Jalankan PostgreSQL/Redis melalui Docker, uji konkurensi slot terakhir antar koneksi database.
2. Lengkapi pengaturan akun/permission dan observability serta backup/restore.
3. Ukur baseline, lalu tingkatkan ke 100/500/1000 user pada staging. Catat throughput, p50/p95/p99, error, CPU/memori; sesuaikan limiter sebelum mengartikan hasil load test.
4. Implementasikan kebutuhan V2 sesuai prioritas pengguna.

PRD menempatkan live demo dan load tests dalam definition of done portfolio-ready. Keduanya belum dipenuhi; versi ini adalah implementasi MVP lokal yang teruji, bukan klaim seluruh PRD selesai atau production-ready.
