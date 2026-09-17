# Hasil pengujian

Tanggal: 17 September 2026. Lingkungan: macOS, PHP 8.5.10, Node 25.9.0, Laravel 13.32.0, Next.js 16.3.5, SQLite lokal, Chrome headless.

| Pemeriksaan | Hasil |
|---|---|
| PHPUnit `php artisan test` | 22 tests passed, 83 assertions |
| Laravel Pint `vendor/bin/pint --test` | Passed |
| Next.js production build | Passed |
| TypeScript strict mode | Passed sebagai bagian build |
| Chrome admin login | Passed |
| Browser check-in → tiket QR → cash payment → slot dilepas | Passed |
| Navigasi 10 halaman operasional | Passed, tanpa error JavaScript |
| UI mobile 390 × 844 | Passed, tidak ada horizontal overflow dokumen |
| Request mutasi tanpa CSRF di server nyata | HTTP 419, passed |
| Docker Compose config | Valid |
| Shell syntax startup/setup | Valid |

## Backend yang diuji

Boundary tarif pada 0 detik, tepat 1 jam, lebih 1 detik, 24/48 jam, cap harian, tarif nol, durasi nonnegatif. Siklus parkir lengkap, pembayaran hanya sekali saat retry, key sama dengan payload berbeda, kendaraan masih aktif, slot terakhir, rollback pembayaran kurang, snapshot tarif, denda tiket hilang/verifikasi, quote basi melewati jam, role petugas, validasi input, slot terisi tidak dapat ditimpa, pembuatan area/prefix unik, laporan/CSV, auth/login rate limiter, tiket tidak dikenal, health.

## Artifacts

- [Dashboard](dashboard.png)
- [Tiket QR](ticket.png)
- [Mobile](mobile.png)
- `frontend/tests/smoke.cjs`: smoke test browser yang dapat dijalankan ulang.
- `backend/tests/Feature/ParkingFlowTest.php`, `backend/tests/Unit/ParkingFeeTest.php`.

Screenshot dan transaksi uji menggunakan data lokal nyata. Akhiran plat E2E menandai data yang dibuat smoke test.

## Belum diverifikasi

Docker daemon tidak aktif sehingga image build/container runtime, PostgreSQL row-lock antar request bersamaan, Redis, dan worker belum dijalankan. Tes slot terakhir saat ini berurutan pada SQLite. Load test 100/500/1000 user, p95/p99, throughput, CPU/memori, deployment HTTPS, browser selain Chrome, dan integrasi hardware/payment provider belum diuji. Tidak ada klaim production-readiness atau hasil performa yang belum diukur.
