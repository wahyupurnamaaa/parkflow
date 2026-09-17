# REST API MVP

Base URL lokal frontend `http://127.0.0.1:3000/api`; backend langsung `http://127.0.0.1:8000/api`.

Gunakan `Accept: application/json`, session cookie, dan `X-CSRF-TOKEN` untuk mutasi. GET `/auth/csrf` menginisialisasi cookie dan mengembalikan token. POST `/auth/login` menerima `email,password` dan mengembalikan `user,csrf_token` baru. Simpan cookie dan gunakan token baru. Semua endpoint di bawah kecuali csrf/login/ticket/health memerlukan role operasional. Admin mencakup admin dan super_admin.

| Method | Path | Peran / fungsi |
|---|---|---|
| GET | /auth/csrf | Publik, token CSRF |
| POST | /auth/login | Publik, login dengan rate limiting |
| GET | /auth/me | User saat ini |
| POST | /auth/logout | Logout + invalidasi session |
| POST | /auth/password | `current_password,password,password_confirmation` (min 12) |
| GET | /dashboard | KPI, trend 7 hari, sesi terbaru |
| GET, POST | /parking-locations | GET operasional; POST admin: `name,address` |
| GET, POST | /parking-areas | POST admin: `location_id,name,vehicle_type,prefix,capacity` |
| GET | /parking-slots | Semua slot, area, kendaraan aktif |
| PATCH | /parking-slots/{id} | Admin, `status`: AVAILABLE/MAINTENANCE/DISABLED; slot terisi ditolak |
| GET | /vehicles | Daftar kendaraan, pagination |
| GET, POST | /rates | POST admin: `vehicle_type,first_hour,additional_hour,daily_max,lost_penalty` |
| GET | /parking/sessions | `page,search,status`; pencarian plat/tiket/slot |
| GET | /parking/sessions/{id} | Detail sesi termasuk tiket |
| POST | /parking/check-in | `license_plate,vehicle_type,area_id`; Idempotency-Key wajib |
| POST | /parking/quote | `query,lost_ticket` → `session,fee` |
| POST | /parking/check-out | `query,cash_received,expected_total,lost_ticket,vehicle_verified`; Idempotency-Key wajib |
| GET | /payments | Pembayaran selesai, pagination |
| GET | /reports/revenue | Admin, `from,to` (YYYY-MM-DD); opsional `format=csv` |
| GET | /staff | Admin, daftar akun |
| GET | /audit-logs | Admin, audit read-only dengan pagination |
| GET | /tickets/{token} | Publik, subset data tiket |
| GET | /health | Di root backend/Nginx, pemeriksaan database |

`vehicle_type`: CAR, MOTORCYCLE, TRUCK, BUS, OTHER. Nilai uang adalah bilangan bulat Rupiah. Status slot dan sesi mengikuti field backend. Pembayaran MVP hanya CASH/PAID; check-out mengerjakan pembayaran dan pelepasan slot dalam satu transaksi.

Check-in contoh:

```json
{"license_plate":"B 1234 ABC","vehicle_type":"CAR","area_id":1}
```

Gunakan header `Idempotency-Key: <UUID unik>`. Retry request yang sama memakai key yang sama. Respons berisi `id,ticket_number,ticket_token,slot_code,entry_time,status` dan detail sesi. QR frontend berisi URL `/ticket/{ticket_token}`.

Quote menerima plat kendaraan, nomor tiket, atau token (URL QR diparsing frontend). Checkout contoh setelah quote:

```json
{"query":"B 1234 ABC","cash_received":10000,"expected_total":5000,"lost_ticket":false,"vehicle_verified":true}
```

Respons `reference,total,change,session,message`. Total tidak dipercaya dari client; backend menghitung dan membandingkan `expected_total`. Jangan memanggil check-out tanpa mengonfirmasi kendaraan dan pembayaran tunai fisik.

Error: 401 belum login; 403 role tidak sesuai; 404 sesi tidak ada; 409 konflik slot/sesi/idempotency atau quote berubah; 419 CSRF/session kedaluwarsa; 422 validasi; 429 rate limit. Respons error memiliki `message`, dan opsional `errors` untuk field.

Pagination Laravel: `data,current_page,last_page,total,per_page` (25 untuk sesi/kendaraan/pembayaran, 30 untuk audit). Area dan slot berupa array. Endpoint PRD lanjutan seperti payments/webhook, reservation, memberships, refund belum diimplementasikan.
