<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('role')->default('officer'));
        Schema::create('parking_locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('address');
            $t->timestamps();
        });
        Schema::create('parking_areas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('parking_locations');
            $t->string('name');
            $t->string('vehicle_type');
            $t->timestamps();
        });
        Schema::create('parking_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('area_id')->constrained('parking_areas');
            $t->string('code')->unique();
            $t->string('status')->default('AVAILABLE');
            $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('license_plate')->unique();
            $t->string('vehicle_type');
            $t->string('owner')->nullable();
            $t->timestamps();
        });
        Schema::create('parking_rates', function (Blueprint $t) {
            $t->id();
            $t->string('vehicle_type')->unique();
            $t->unsignedInteger('first_hour');
            $t->unsignedInteger('additional_hour');
            $t->unsignedInteger('daily_max');
            $t->unsignedInteger('lost_penalty')->default(25000);
            $t->timestamps();
        });
        Schema::create('parking_sessions', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_number')->unique();
            $t->string('ticket_token', 64)->unique();
            $t->foreignId('vehicle_id')->constrained('vehicles');
            $t->foreignId('slot_id')->constrained('parking_slots');
            $t->foreignId('officer_id')->constrained('users');
            $t->foreignId('exit_officer_id')->nullable()->constrained('users');
            $t->string('active_plate')->nullable()->unique();
            $t->unsignedBigInteger('active_slot')->nullable()->unique();
            $t->timestamp('entry_time');
            $t->timestamp('exit_time')->nullable();
            $t->string('status')->default('ACTIVE');
            $t->json('rate_snapshot');
            $t->unsignedInteger('duration_minutes')->default(0);
            $t->unsignedInteger('subtotal')->default(0);
            $t->unsignedInteger('penalty')->default(0);
            $t->unsignedInteger('total')->default(0);
            $t->timestamps();
            $t->index(['status', 'entry_time']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('session_id')->unique()->constrained('parking_sessions');
            $t->foreignId('officer_id')->constrained('users');
            $t->string('reference')->unique();
            $t->string('method')->default('CASH');
            $t->string('status')->default('PAID');
            $t->unsignedInteger('amount');
            $t->unsignedInteger('cash_received');
            $t->unsignedInteger('change');
            $t->timestamps();
        });
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('key', 100);
            $t->string('fingerprint', 64);
            $t->json('response');
            $t->timestamps();
            $t->unique(['user_id', 'key']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject');
            $t->json('details')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'idempotency_keys', 'payments', 'parking_sessions', 'parking_rates', 'vehicles', 'parking_slots', 'parking_areas', 'parking_locations'] as $name) {
            Schema::dropIfExists($name);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
