<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VenueOwnerBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_manual_booking_prevents_overlap_and_cancelled_slot_can_be_booked_again(): void
    {
        $owner = User::query()->create([
            'nama' => 'Venue Owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Matcha Arena',
            'jam_operasional' => '07:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 1',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 100000,
        ]);
        $date = now()->addDay()->toDateString();
        $payload = [
            'tanggal' => $date,
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'nama_penyewa' => 'Penyewa Test',
            'status_pembayaran' => 'Paid',
        ];

        $this->actingAs($owner)
            ->post(route('owner.bookings.store', $court->court_id), $payload)
            ->assertRedirect();
        $this->assertDatabaseHas('tb_court_booking', [
            'court_id' => $court->court_id,
            'tanggal' => $date,
            'status' => 'Booked',
        ]);

        $this->from(route('owner.dashboard'))
            ->post(route('owner.bookings.store', $court->court_id), $payload)
            ->assertSessionHasErrors('jam_mulai');
        $this->assertSame(1, CourtBooking::query()->count());

        $booking = CourtBooking::query()->firstOrFail();
        $this->post(route('owner.bookings.cancel', $booking->booking_id), [
            'alasan_pembatalan' => 'Permintaan perubahan jadwal',
        ])->assertRedirect();
        $this->assertDatabaseHas('tb_court_booking', [
            'booking_id' => $booking->booking_id,
            'status' => 'Cancelled',
        ]);
        $this->assertDatabaseHas('tb_court_booking_cancellation', [
            'booking_id' => $booking->booking_id,
            'reason' => 'Permintaan perubahan jadwal',
            'previous_status' => 'Booked',
        ]);

        $this->post(route('owner.bookings.store', $court->court_id), $payload)->assertRedirect();
        $this->assertSame(2, CourtBooking::query()->count());
    }

    public function test_owner_api_records_manual_payment_and_cancellation_history(): void
    {
        $owner = User::query()->create([
            'nama' => 'API Venue Owner',
            'email' => 'api-owner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'API Matcha Arena',
            'jam_operasional' => '07:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court API',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 100000,
        ]);
        $date = now()->addDay()->toDateString();

        $this->actingAs($owner)
            ->postJson("/api/v1/owner/courts/{$court->court_id}/bookings", [
                'tanggal' => $date,
                'jam_mulai' => '10:00',
                'jam_selesai' => '11:00',
                'nama_penyewa' => 'Penyewa API',
                'status_pembayaran' => 'Pending',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'Pending Payment');

        $booking = CourtBooking::query()->firstOrFail();
        $this->assertDatabaseHas('tb_court_booking_payment', [
            'booking_id' => $booking->booking_id,
            'provider' => 'manual',
            'status' => 'Pending',
        ]);

        $this->get("/api/v1/owner/courts/{$court->court_id}/schedule?date={$date}")
            ->assertOk()
            ->assertJsonPath('data.slots.3.status', 'Pending Payment')
            ->assertJsonPath('data.slots.3.booking.renter_name', 'Penyewa API');

        $this->postJson("/api/v1/owner/courts/{$court->court_id}/bookings", [
            'tanggal' => $date,
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'nama_penyewa' => 'Penyewa Duplikat',
            'status_pembayaran' => 'Pending',
        ])->assertUnprocessable();
        $this->assertSame(1, CourtBooking::query()->count());

        $this->postJson("/api/v1/owner/bookings/{$booking->booking_id}/cancel", [
            'alasan_pembatalan' => 'Jadwal perlu ditutup',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled');

        $this->assertDatabaseHas('tb_court_booking_cancellation', [
            'booking_id' => $booking->booking_id,
            'reason' => 'Jadwal perlu ditutup',
            'previous_status' => 'Pending Payment',
            'previous_payment_status' => 'Pending',
        ]);
    }

    public function test_owner_api_rejects_unauthenticated_and_non_owner_users(): void
    {
        $this->get('/api/v1/owner/venues')->assertUnauthorized();

        $player = User::query()->create([
            'nama' => 'Player',
            'email' => 'player@example.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $this->actingAs($player)
            ->get('/api/v1/owner/venues')
            ->assertForbidden();
    }

    public function test_past_manual_booking_returns_to_form_with_validation_error(): void
    {
        $owner = User::query()->create([
            'nama' => 'Venue Owner',
            'email' => 'past-owner@example.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Matcha Arena',
            'jam_operasional' => '07:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 1',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 100000,
        ]);
        $date = now()->toDateString();
        Carbon::setTestNow(Carbon::parse($date.' 15:00:00'));

        try {
            $this->actingAs($owner)
                ->from(route('owner.dashboard'))
                ->post(route('owner.bookings.store', $court->court_id), [
                    'tanggal' => $date,
                    'jam_mulai' => '10:00',
                    'jam_selesai' => '11:00',
                    'nama_penyewa' => 'Penyewa Test',
                    'status_pembayaran' => 'Paid',
                ])
                ->assertRedirect(route('owner.dashboard'))
                ->assertSessionHasErrors('jam_mulai');

            $this->assertSame(0, CourtBooking::query()->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createTestSchema(): void
    {
        if (! Schema::hasTable('tb_user')) {
            Schema::create('tb_user', function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('nama');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->default('member');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_venue')) {
            Schema::create('tb_venue', function (Blueprint $table): void {
                $table->id('venue_id');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('nama_venue');
                $table->string('jam_operasional')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function (Blueprint $table): void {
                $table->id('court_id');
                $table->unsignedBigInteger('venue_id');
                $table->string('nama_court');
                $table->string('status_ketersediaan')->default('Available');
                $table->decimal('harga_per_jam', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court_booking')) {
            Schema::create('tb_court_booking', function (Blueprint $table): void {
                $table->id('booking_id');
                $table->unsignedBigInteger('court_id');
                $table->date('tanggal');
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->string('nama_penyewa');
                $table->string('kontak_penyewa')->nullable();
                $table->decimal('jumlah_bayar', 12, 2)->default(0);
                $table->string('status');
                $table->string('status_pembayaran');
                $table->string('sumber');
                $table->text('catatan')->nullable();
                $table->text('alasan_pembatalan')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court_booking_payment')) {
            Schema::create('tb_court_booking_payment', function (Blueprint $table): void {
                $table->id('payment_id');
                $table->unsignedBigInteger('booking_id');
                $table->string('provider');
                $table->string('provider_reference')->nullable();
                $table->string('metode_pembayaran')->nullable();
                $table->decimal('jumlah', 12, 2)->default(0);
                $table->string('status');
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court_booking_cancellation')) {
            Schema::create('tb_court_booking_cancellation', function (Blueprint $table): void {
                $table->id('cancellation_id');
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->string('previous_status');
                $table->string('previous_payment_status');
                $table->text('reason');
                $table->timestamp('cancelled_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court_schedule')) {
            Schema::create('tb_court_schedule', function (Blueprint $table): void {
                $table->id('schedule_id');
                $table->unsignedBigInteger('court_id');
                $table->unsignedTinyInteger('weekday');
                $table->boolean('is_open')->default(true);
                $table->time('jam_buka');
                $table->time('jam_tutup');
                $table->unsignedSmallInteger('durasi_menit')->default(60);
                $table->decimal('harga_per_slot', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['court_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('tb_court_slot_block')) {
            Schema::create('tb_court_slot_block', function (Blueprint $table): void {
                $table->id('block_id');
                $table->unsignedBigInteger('court_id');
                $table->date('tanggal');
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->string('alasan')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }
}
