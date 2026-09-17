<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ParkingFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ParkingController extends Controller
{
    private const TYPES = ['CAR', 'MOTORCYCLE', 'TRUCK', 'BUS', 'OTHER'];

    public function audit(string $action, string $subject, array $details = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'action' => $action, 'subject' => $subject, 'details' => json_encode($details), 'created_at' => now()]);
    }

    private function admin(): void
    {
        abort_unless(in_array(Auth::user()->role, ['admin', 'super_admin']), 403, 'Akses khusus administrator.');
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', strtolower($d['email']).'|'.$r->ip());
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Terlalu banyak percobaan. Coba lagi dalam satu menit.');
        if (! Auth::attempt($d)) {
            RateLimiter::hit($key, 60);
            abort(422, 'Email atau kata sandi salah.');
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();
        $this->audit('LOGIN', Auth::user()->email);

        return ['user' => Auth::user(), 'csrf_token' => csrf_token()];
    }

    public function logout(Request $r)
    {
        $this->audit('LOGOUT', Auth::user()->email);
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return ['message' => 'Berhasil keluar.'];
    }

    public function password(Request $r)
    {
        $d = $r->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:12|confirmed']);
        $r->user()->password = Hash::make($d['password']);
        $r->user()->save();
        $r->session()->regenerate();
        $this->audit('PASSWORD_CHANGED', (string) Auth::id());

        return ['message' => 'Kata sandi diperbarui.', 'csrf_token' => csrf_token()];
    }

    private function sessions()
    {
        return DB::table('parking_sessions as s')->join('vehicles as v', 'v.id', '=', 's.vehicle_id')->join('parking_slots as p', 'p.id', '=', 's.slot_id')->join('parking_areas as a', 'a.id', '=', 'p.area_id')->join('parking_locations as l', 'l.id', '=', 'a.location_id')->join('users as u', 'u.id', '=', 's.officer_id')->select('s.*', 'v.license_plate', 'v.vehicle_type', 'p.code as slot_code', 'a.name as area_name', 'l.name as location_name', 'u.name as officer_name');
    }

    public function sessionList(Request $r)
    {
        $q = $this->sessions();
        if ($r->query('status')) {
            $q->where('s.status', $r->query('status'));
        } if ($s = $r->query('search')) {
            $q->where(fn ($q) => $q->where('v.license_plate', 'like', '%'.strtoupper($s).'%')->orWhere('s.ticket_number', 'like', '%'.strtoupper($s).'%')->orWhere('p.code', 'like', '%'.strtoupper($s).'%'));
        }

return $q->orderByDesc('s.id')->paginate(25);
    }

    public function session(string $id)
    {
        $s = $this->sessions()->where('s.id', $id)->first();
        abort_unless($s, 404, 'Sesi tidak ditemukan.');

        return $s;
    }

    public function ticket(string $token)
    {
        $s = $this->sessions()->where('s.ticket_token', $token)->first();
        abort_unless($s, 404, 'Tiket tidak ditemukan.');

        return ['ticket_number' => $s->ticket_number, 'license_plate' => $s->license_plate, 'vehicle_type' => $s->vehicle_type, 'slot_code' => $s->slot_code, 'location_name' => $s->location_name, 'entry_time' => $s->entry_time, 'exit_time' => $s->exit_time, 'status' => $s->status, 'total' => $s->total];
    }

    public function dashboard()
    {
        $slots = DB::table('parking_slots');
        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $trend[] = ['date' => $day->format('Y-m-d'), 'revenue' => (int) DB::table('payments')->whereDate('created_at', $day->toDateString())->sum('amount'), 'vehicles' => DB::table('parking_sessions')->whereDate('entry_time', $day->toDateString())->count()];
        }

        return ['total_slots' => $slots->count(), 'available' => DB::table('parking_slots')->where('status', 'AVAILABLE')->count(), 'occupied' => DB::table('parking_slots')->where('status', 'OCCUPIED')->count(), 'vehicles_today' => DB::table('parking_sessions')->where('entry_time', '>=', $today)->count(), 'active_sessions' => DB::table('parking_sessions')->where('status', 'ACTIVE')->count(), 'revenue_today' => (int) DB::table('payments')->where('created_at', '>=', $today)->sum('amount'), 'revenue_month' => (int) DB::table('payments')->where('created_at', '>=', $month)->sum('amount'), 'average_duration' => (int) round(DB::table('parking_sessions')->where('status', 'COMPLETED')->avg('duration_minutes') ?? 0), 'trend' => $trend, 'recent' => $this->sessions()->orderByDesc('s.id')->limit(6)->get(), 'activity' => DB::table('audit_logs')->orderByDesc('id')->limit(6)->get(), 'distribution' => DB::table('parking_sessions as s')->join('vehicles as v', 'v.id', '=', 's.vehicle_id')->where('s.status', 'ACTIVE')->groupBy('v.vehicle_type')->selectRaw('v.vehicle_type, COUNT(*) as count')->get()];
    }

    public function locations()
    {
        return DB::table('parking_locations')->get();
    }

    public function createLocation(Request $r)
    {
        $this->admin();
        $d = $r->validate(['name' => 'required|string|max:100', 'address' => 'required|string|max:255']);
        $id = DB::transaction(function () use ($d) {
            $id = DB::table('parking_locations')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
            $this->audit('LOCATION_CREATED', (string) $id);

            return $id;
        });

        return response()->json(['id' => $id] + $d, 201);
    }

    public function areas()
    {
        return DB::table('parking_areas as a')->join('parking_locations as l', 'l.id', '=', 'a.location_id')->select('a.*', 'l.name as location_name')->get();
    }

    public function createArea(Request $r)
    {
        $this->admin();
        $d = $r->validate(['location_id' => 'required|exists:parking_locations,id', 'name' => 'required|string|max:100', 'vehicle_type' => ['required', Rule::in(self::TYPES)], 'prefix' => 'required|regex:/^[A-Z0-9-]{1,12}$/', 'capacity' => 'required|integer|min:1|max:500']);

        return DB::transaction(function () use ($d) {
            $codes = [];
            for ($i = 1; $i <= $d['capacity']; $i++) {
                $codes[] = $d['prefix'].'-'.str_pad($i, 3, '0', STR_PAD_LEFT);
            } abort_if(DB::table('parking_slots')->whereIn('code', $codes)->exists(), 422, 'Prefix slot sudah digunakan.');
            $id = DB::table('parking_areas')->insertGetId(['location_id' => $d['location_id'], 'name' => $d['name'], 'vehicle_type' => $d['vehicle_type'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ($codes as $code) {
                DB::table('parking_slots')->insert(['area_id' => $id, 'code' => $code, 'status' => 'AVAILABLE', 'created_at' => now(), 'updated_at' => now()]);
            } $this->audit('AREA_CREATED', $d['name'], ['capacity' => $d['capacity']]);

            return ['id' => $id];
        });
    }

    public function slots()
    {
        return DB::table('parking_slots as p')->join('parking_areas as a', 'a.id', '=', 'p.area_id')->leftJoin('parking_sessions as s', 's.active_slot', '=', 'p.id')->leftJoin('vehicles as v', 'v.id', '=', 's.vehicle_id')->select('p.*', 'a.name as area_name', 'a.vehicle_type', 'v.license_plate', 's.entry_time', 's.ticket_number')->orderBy('p.id')->get();
    }

    public function updateSlot(Request $r, int $id)
    {
        $this->admin();
        $d = $r->validate(['status' => ['required', Rule::in(['AVAILABLE', 'MAINTENANCE', 'DISABLED'])]]);

        return DB::transaction(function () use ($d, $id) {
            $slot = DB::table('parking_slots')->where('id', $id)->lockForUpdate()->first();
            abort_unless($slot, 404);
            abort_if($slot->status === 'OCCUPIED', 409, 'Slot sedang terisi.');
            DB::table('parking_slots')->where('id', $id)->update($d + ['updated_at' => now()]);
            $this->audit('SLOT_UPDATED', $slot->code, $d);

            return ['message' => 'Status slot diperbarui.'];
        });
    }

    public function vehicles()
    {
        return DB::table('vehicles')->orderByDesc('id')->paginate(25);
    }

    public function rates()
    {
        return DB::table('parking_rates')->get();
    }

    public function saveRate(Request $r)
    {
        $this->admin();
        $d = $r->validate(['vehicle_type' => ['required', Rule::in(self::TYPES)], 'first_hour' => 'required|integer|min:0|max:10000000', 'additional_hour' => 'required|integer|min:0|max:10000000', 'daily_max' => 'required|integer|min:1|max:10000000', 'lost_penalty' => 'required|integer|min:0|max:10000000']);

        return DB::transaction(function () use ($d) {
            DB::table('parking_rates')->updateOrInsert(['vehicle_type' => $d['vehicle_type']], $d + ['updated_at' => now(), 'created_at' => now()]);
            $this->audit('RATE_UPDATED', $d['vehicle_type'], $d);

            return ['message' => 'Tarif disimpan. Sesi aktif tetap memakai tarif saat masuk.'];
        });
    }

    private function idempotent(Request $r, callable $action)
    {
        $key = $r->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[a-zA-Z0-9_-]{8,100}$/', $key), 422, 'Idempotency-Key wajib diisi (8–100 karakter).');
        $body = $r->all();
        ksort($body);
        $fp = hash('sha256', $r->path().json_encode($body));

        return DB::transaction(function () use ($key, $fp, $action) {
            DB::table('users')->where('id', Auth::id())->lockForUpdate()->first();
            $old = DB::table('idempotency_keys')->where('user_id', Auth::id())->where('key', $key)->first();
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fp), 409, 'Kunci permintaan telah digunakan untuk data berbeda.');

                return json_decode($old->response, true);
            } $result = $action();
            DB::table('idempotency_keys')->insert(['user_id' => Auth::id(), 'key' => $key, 'fingerprint' => $fp, 'response' => json_encode($result), 'created_at' => now(), 'updated_at' => now()]);

            return $result;
        }, 5);
    }

    public function checkIn(Request $r)
    {
        $r->merge(['license_plate' => strtoupper(preg_replace('/\s+/', ' ', trim($r->input('license_plate', ''))))]);
        $d = $r->validate(['license_plate' => 'required|string|max:15|regex:/^[A-Z0-9][A-Z0-9 -]{1,14}$/', 'vehicle_type' => ['required', Rule::in(self::TYPES)], 'area_id' => 'required|integer|exists:parking_areas,id']);

        return $this->idempotent($r, function () use ($d) {
            $area = DB::table('parking_areas')->where('id', $d['area_id'])->first();
            abort_unless($area->vehicle_type === $d['vehicle_type'], 422, 'Jenis kendaraan tidak sesuai area.');
            $rate = DB::table('parking_rates')->where('vehicle_type', $d['vehicle_type'])->first();
            abort_unless($rate, 422, 'Tarif belum dikonfigurasi.');
            // Lock the area before allocating. All entrants to the last slot are serialized.
            DB::table('parking_areas')->where('id', $area->id)->lockForUpdate()->first();
            DB::table('vehicles')->insertOrIgnore(['license_plate' => $d['license_plate'], 'vehicle_type' => $d['vehicle_type'], 'created_at' => now(), 'updated_at' => now()]);
            $vehicle = DB::table('vehicles')->where('license_plate', $d['license_plate'])->lockForUpdate()->first();
            abort_if(DB::table('parking_sessions')->where('active_plate', $d['license_plate'])->exists(), 409, 'Kendaraan masih memiliki sesi parkir aktif.');
            abort_unless($vehicle->vehicle_type === $d['vehicle_type'], 422, 'Jenis kendaraan berbeda dari data terdaftar.');
            $slot = DB::table('parking_slots')->where('area_id', $area->id)->where('status', 'AVAILABLE')->orderBy('id')->lockForUpdate()->first();
            abort_unless($slot, 409, 'Area parkir penuh. Pilih area lain.');
            $id = DB::table('parking_sessions')->insertGetId(['ticket_number' => 'PF-'.strtoupper(Str::random(10)), 'ticket_token' => bin2hex(random_bytes(32)), 'vehicle_id' => $vehicle->id, 'slot_id' => $slot->id, 'officer_id' => Auth::id(), 'active_plate' => $d['license_plate'], 'active_slot' => $slot->id, 'entry_time' => now(), 'status' => 'ACTIVE', 'rate_snapshot' => json_encode($rate), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('parking_slots')->where('id', $slot->id)->update(['status' => 'OCCUPIED', 'updated_at' => now()]);
            $this->audit('VEHICLE_ENTERED', $d['license_plate'], ['session_id' => $id, 'slot' => $slot->code]);

            return (array) $this->session((string) $id);
        });
    }

    private function lookup(string $query, bool $lock = false)
    {
        $q = DB::table('parking_sessions')->where('status', 'ACTIVE')->where(fn ($q) => $q->where('ticket_token', $query)->orWhere('ticket_number', strtoupper($query))->orWhere('active_plate', strtoupper(trim($query))));
        if ($lock) {
            $q->lockForUpdate();
        } $s = $q->first();
        abort_unless($s, 404, 'Sesi aktif tidak ditemukan. Gunakan nomor tiket, QR, atau plat kendaraan.');

        return $s;
    }

    public function quote(Request $r)
    {
        $d = $r->validate(['query' => 'required|string|max:200', 'lost_ticket' => 'sometimes|boolean']);
        $s = $this->lookup($d['query']);
        $fee = ParkingFee::calculate($s->entry_time, json_decode($s->rate_snapshot, true));
        $penalty = ($d['lost_ticket'] ?? false) ? json_decode($s->rate_snapshot, true)['lost_penalty'] : 0;

        return ['session' => $this->session((string) $s->id), 'fee' => $fee + ['penalty' => $penalty, 'total' => $fee['subtotal'] + $penalty]];
    }

    public function checkOut(Request $r)
    {
        $d = $r->validate(['query' => 'required|string|max:200', 'cash_received' => 'required|integer|min:0|max:100000000', 'lost_ticket' => 'required|boolean', 'vehicle_verified' => 'required|boolean', 'expected_total' => 'required|integer|min:0']);

        return $this->idempotent($r, function () use ($d) {
            $s = $this->lookup($d['query'], true);
            abort_unless($d['vehicle_verified'], 422, 'Verifikasi kendaraan wajib dilakukan.');
            $rate = json_decode($s->rate_snapshot, true);
            $fee = ParkingFee::calculate($s->entry_time, $rate);
            $penalty = $d['lost_ticket'] ? $rate['lost_penalty'] : 0;
            $total = $fee['subtotal'] + $penalty;
            abort_unless($total === $d['expected_total'], 409, 'Tarif berubah karena durasi bertambah. Hitung ulang tagihan.');
            abort_if($d['cash_received'] < $total, 422, 'Uang diterima kurang dari tagihan.');
            $reference = 'PAY-'.strtoupper(Str::random(12));
            DB::table('payments')->insert(['session_id' => $s->id, 'officer_id' => Auth::id(), 'reference' => $reference, 'method' => 'CASH', 'status' => 'PAID', 'amount' => $total, 'cash_received' => $d['cash_received'], 'change' => $d['cash_received'] - $total, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('parking_sessions')->where('id', $s->id)->update(['exit_time' => now(), 'exit_officer_id' => Auth::id(), 'status' => 'COMPLETED', 'active_plate' => null, 'active_slot' => null, 'duration_minutes' => $fee['duration_minutes'], 'subtotal' => $fee['subtotal'], 'penalty' => $penalty, 'total' => $total, 'updated_at' => now()]);
            DB::table('parking_slots')->where('id', $s->slot_id)->update(['status' => 'AVAILABLE', 'updated_at' => now()]);
            if ($d['lost_ticket']) {
                $this->audit('LOST_TICKET', $s->ticket_number, ['penalty' => $penalty]);
            } $this->audit('VEHICLE_EXITED', $s->ticket_number, ['total' => $total, 'payment' => $reference]);

            return ['message' => 'Pembayaran berhasil. Slot telah dikosongkan.', 'reference' => $reference, 'total' => $total, 'change' => $d['cash_received'] - $total, 'session' => $this->session((string) $s->id)];
        });
    }

    public function payments()
    {
        return DB::table('payments as p')->join('parking_sessions as s', 's.id', '=', 'p.session_id')->join('vehicles as v', 'v.id', '=', 's.vehicle_id')->join('users as u', 'u.id', '=', 'p.officer_id')->select('p.*', 's.ticket_number', 'v.license_plate', 'u.name as officer_name')->orderByDesc('p.id')->paginate(25);
    }

    public function reports(Request $r)
    {
        $this->admin();
        $d = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $rows = DB::table('payments as p')->join('parking_sessions as s', 's.id', '=', 'p.session_id')->join('vehicles as v', 'v.id', '=', 's.vehicle_id')->join('users as u', 'u.id', '=', 'p.officer_id')->whereBetween('p.created_at', [$d['from'].' 00:00:00', $d['to'].' 23:59:59'])->select('p.reference', 'v.license_plate', 's.ticket_number', 's.entry_time', 's.exit_time', 's.duration_minutes', 'p.amount', 'p.method', 'u.name as officer', 'p.created_at')->orderBy('p.created_at')->get();
        if ($r->query('format') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $f = fopen('php://output', 'w');
                fwrite($f, "\xEF\xBB\xBF");
                fputcsv($f, ['Referensi', 'Plat', 'Tiket', 'Masuk', 'Keluar', 'Durasi menit', 'Total IDR', 'Metode', 'Petugas', 'Waktu transaksi'], ',', '"', '');
                foreach ($rows as $row) {
                    fputcsv($f,array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/',$v) ? "'".$v : $v,(array) $row),',','"','');
                } fclose($f);
            }, 'parkflow-report.csv', ['Content-Type' => 'text/csv']);
        }

return ['rows' => $rows, 'total_revenue' => (int) $rows->sum('amount'), 'total_transactions' => $rows->count(), 'average_duration' => (int) round($rows->avg('duration_minutes') ?? 0)];
    }

    public function auditLogs()
    {
        $this->admin();

        return DB::table('audit_logs as a')->leftJoin('users as u','u.id','=','a.user_id')->select('a.*','u.name as user_name')->orderByDesc('a.id')->paginate(30);
    }

    public function staff()
    {
        $this->admin();

        return User::select('id','name','email','role','created_at')->get();
    }
}
