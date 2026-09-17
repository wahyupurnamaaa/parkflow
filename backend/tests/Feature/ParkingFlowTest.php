<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParkingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('role', 'admin')->first());
    }

    private function enter(string $plate = 'B 1234 ABC', string $key = 'entry-key-123')
    {
        return $this->postJson('/api/parking/check-in', ['license_plate' => $plate, 'vehicle_type' => 'CAR', 'area_id' => 1], ['Idempotency-Key' => $key]);
    }

    private function exitBody(array $session): array
    {
        return ['query' => $session['ticket_number'], 'cash_received' => 10000, 'expected_total' => 5000, 'lost_ticket' => false, 'vehicle_verified' => true];
    }

    public function test_full_lifecycle_and_idempotent_payment(): void
    {
        $s = $this->enter()->assertOk()->assertJsonPath('status', 'ACTIVE')->json();
        $this->assertDatabaseHas('parking_slots', ['id' => $s['slot_id'], 'status' => 'OCCUPIED']);
        $this->getJson('/api/tickets/'.$s['ticket_token'])->assertOk()->assertJsonMissingPath('officer_id');
        $this->postJson('/api/parking/quote', ['query' => $s['ticket_number']])->assertOk()->assertJsonPath('fee.total', 5000);
        $body = $this->exitBody($s);
        $r = $this->postJson('/api/parking/check-out', $body, ['Idempotency-Key' => 'exit-key-123'])->assertOk()->assertJsonPath('change', 5000)->json();
        $this->postJson('/api/parking/check-out', $body, ['Idempotency-Key' => 'exit-key-123'])->assertOk()->assertJsonPath('reference', $r['reference']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('parking_slots', ['id' => $s['slot_id'], 'status' => 'AVAILABLE']);
        $this->assertDatabaseHas('parking_sessions', ['id' => $s['id'], 'active_plate' => null, 'active_slot' => null, 'status' => 'COMPLETED']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('revenue_today', 5000)->assertJsonPath('active_sessions', 0);
    }

    public function test_duplicate_entry_returns_same_ticket(): void
    {
        $s = $this->enter()->assertOk()->json();
        $this->enter()->assertOk()->assertJsonPath('id', $s['id']);
        $this->assertDatabaseCount('parking_sessions', 1);
    }

    public function test_reusing_key_with_changed_payload_conflicts(): void
    {
        $this->enter()->assertOk();
        $this->enter('B 9999 ABC')->assertConflict();
        $this->assertDatabaseCount('parking_sessions', 1);
    }

    public function test_vehicle_cannot_enter_twice(): void
    {
        $this->enter()->assertOk();
        $this->enter('B 1234 ABC', 'another-key-123')->assertConflict();
    }

    public function test_last_slot_is_only_allocated_once(): void
    {
        DB::table('parking_slots')->where('id', '>', 1)->update(['status' => 'DISABLED']);
        $this->enter()->assertOk();
        $this->enter('B 9999 ABC', 'another-key-123')->assertConflict();
        $this->assertDatabaseCount('parking_sessions', 1);
    }

    public function test_insufficient_payment_rolls_back_everything(): void
    {
        $s = $this->enter()->json();
        $b = $this->exitBody($s);
        $b['cash_received'] = 1000;
        $this->postJson('/api/parking/check-out', $b, ['Idempotency-Key' => 'exit-key-123'])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('parking_sessions', ['id' => $s['id'], 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('parking_slots', ['id' => $s['slot_id'], 'status' => 'OCCUPIED']);
    }

    public function test_rate_snapshot_survives_rate_change(): void
    {
        $s = $this->enter()->json();
        $this->postJson('/api/rates', ['vehicle_type' => 'CAR', 'first_hour' => 10000, 'additional_hour' => 5000, 'daily_max' => 90000, 'lost_penalty' => 30000])->assertOk();
        $this->postJson('/api/parking/quote', ['query' => $s['ticket_number']])->assertJsonPath('fee.total', 5000);
    }

    public function test_lost_ticket_penalty_and_verification(): void
    {
        $s = $this->enter()->json();
        $b = $this->exitBody($s);
        $b['query'] = 'B 1234 ABC';
        $b['lost_ticket'] = true;
        $b['expected_total'] = 30000;
        $b['cash_received'] = 30000;
        $b['vehicle_verified'] = false;
        $this->postJson('/api/parking/check-out', $b, ['Idempotency-Key' => 'exit-key-123'])->assertUnprocessable();
        $b['vehicle_verified'] = true;
        $this->postJson('/api/parking/check-out', $b, ['Idempotency-Key' => 'exit-key-123'])->assertOk()->assertJsonPath('total', 30000);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOST_TICKET']);
    }

    public function test_stale_quote_rejected_at_hour_boundary(): void
    {
        $this->travelTo(now()->startOfHour());
        $s = $this->enter()->json();
        $this->travel(61)->minutes();
        $this->postJson('/api/parking/check-out', $this->exitBody($s), ['Idempotency-Key' => 'exit-key-123'])->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_officer_cannot_change_rates_or_read_admin_reports(): void
    {
        $this->actingAs(User::where('role', 'officer')->first());
        $this->postJson('/api/rates', [])->assertForbidden();
        $this->postJson('/api/parking-areas', [])->assertForbidden();
        $this->postJson('/api/parking-locations', [])->assertForbidden();
        $this->getJson('/api/audit-logs')->assertForbidden();
        $this->getJson('/api/reports/revenue?from=2026-01-01&to=2026-12-31')->assertForbidden();
        $this->enter()->assertOk();
    }

    public function test_input_validation_and_idempotency_requirement(): void
    {
        $this->postJson('/api/parking/check-in', ['license_plate' => 'bad<script>', 'vehicle_type' => 'CAR', 'area_id' => 1])->assertUnprocessable();
        $this->postJson('/api/parking/check-in', ['license_plate' => 'B 1234 ABC', 'vehicle_type' => 'CAR', 'area_id' => 1])->assertUnprocessable();
        $this->postJson('/api/parking/check-in', ['license_plate' => 'B 1234 ABC', 'vehicle_type' => 'MOTORCYCLE', 'area_id' => 1], ['Idempotency-Key' => 'entry-key-123'])->assertUnprocessable();
    }

    public function test_occupied_slot_cannot_be_overridden(): void
    {
        $s = $this->enter()->json();
        $this->patchJson('/api/parking-slots/'.$s['slot_id'], ['status' => 'AVAILABLE'])->assertConflict();
    }

    public function test_area_capacity_and_unique_prefix(): void
    {
        $d = ['location_id' => 1, 'name' => 'VIP', 'vehicle_type' => 'CAR', 'prefix' => 'VIP', 'capacity' => 3];
        $this->postJson('/api/parking-areas', $d)->assertOk();
        $this->assertSame(3, DB::table('parking_slots')->where('code', 'like', 'VIP-%')->count());
        $this->postJson('/api/parking-areas', $d)->assertUnprocessable();
    }

    public function test_report_and_csv_export(): void
    {
        $s = $this->enter()->json();
        $this->postJson('/api/parking/check-out', $this->exitBody($s), ['Idempotency-Key' => 'exit-key-123'])->assertOk();
        $q = '?from='.now()->toDateString().'&to='.now()->toDateString();
        $this->getJson('/api/reports/revenue'.$q)->assertOk()->assertJsonPath('total_revenue', 5000)->assertJsonPath('total_transactions', 1);
        $this->get('/api/reports/revenue'.$q.'&format=csv')->assertOk()->assertDownload('parkflow-report.csv');
    }

    public function test_authentication_and_unknown_ticket(): void
    {
        auth()->logout();
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'admin@parkflow.test', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'admin@parkflow.test', 'password' => 'ParkFlow123!'])->assertOk();
        $this->getJson('/api/tickets/invalid-token')->assertNotFound();
    }

    public function test_repeated_bad_login_is_rate_limited(): void
    {
        auth()->logout();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@parkflow.test', 'password' => 'wrong'])->assertUnprocessable();
        }$this->postJson('/api/auth/login',['email' => 'admin@parkflow.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_health_endpoint(): void
    {
        $this->getJson('/health')->assertOk()->assertJsonPath('status','ok');
    }
}
