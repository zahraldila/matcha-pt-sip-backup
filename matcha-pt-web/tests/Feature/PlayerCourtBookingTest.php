<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlayerCourtBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    public function test_user_dashboard_renders_with_slots_and_preserves_privacy(): void
    {
        $owner = User::query()->create([
            'nama' => 'Owner Matcha',
            'email' => 'owner@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $player = User::query()->create([
            'nama' => 'Player Satu',
            'email' => 'player1@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);
        $otherUser = User::query()->create([
            'nama' => 'Rahasia Penyewa Lain',
            'email' => 'other@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $sport = Sport::query()->create(['nama_sport' => 'Tennis']);
        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Gelora Senayan Tennis Center',
            'jam_operasional' => '07:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'sport_id' => $sport->sport_id,
            'nama_court' => 'Court Center Court',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 150000,
        ]);

        $date = now()->addDay()->toDateString();

        // Create booking by other user on slot 10:00 - 11:00
        CourtBooking::query()->create([
            'court_id' => $court->court_id,
            'tanggal' => $date,
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'nama_penyewa' => $otherUser->nama,
            'kontak_penyewa' => '08999999999',
            'jumlah_bayar' => 150000,
            'status' => 'Booked',
            'status_pembayaran' => 'Paid',
            'sumber' => 'online',
            'created_by' => $otherUser->user_id,
        ]);

        $response = $this->actingAs($player)->get(route('user.dashboard', ['date' => $date]));

        $response->assertOk()
            ->assertSee('Dashboard Sewa Court')
            ->assertSee('Court Center Court')
            ->assertSee('Gelora Senayan Tennis Center')
            ->assertSee('10:00–11:00')
            ->assertSee('Booked')
            // Privacy check: Renter's personal name and phone MUST NOT be visible to other players
            ->assertDontSee($otherUser->nama)
            ->assertDontSee('08999999999');
    }

    public function test_user_can_book_court_successfully_and_payment_record_is_created(): void
    {
        $owner = User::query()->create([
            'nama' => 'Owner Arena',
            'email' => 'owner2@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $player = User::query()->create([
            'nama' => 'Budi Santoso',
            'no_hp' => '081234567890',
            'email' => 'budi@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Matcha Arena Cilandak',
            'jam_operasional' => '08:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court Padel 1',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 200000,
        ]);

        $date = now()->addDay()->toDateString();

        $payload = [
            'court_id' => $court->court_id,
            'tanggal' => $date,
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'nama_penyewa' => 'Budi Santoso',
            'kontak_penyewa' => '081234567890',
            'metode_pembayaran' => 'QRIS',
            'catatan' => 'Mohon siapkan 2 raket sewa',
        ];

        $response = $this->actingAs($player)
            ->post(route('player.bookings.store'), $payload);

        $response->assertRedirect(route('player.bookings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tb_court_booking', [
            'court_id' => $court->court_id,
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'nama_penyewa' => 'Budi Santoso',
            'status' => 'Booked',
            'status_pembayaran' => 'Paid',
            'sumber' => 'online',
            'created_by' => $player->user_id,
        ]);

        $booking = CourtBooking::query()->firstOrFail();
        $this->assertEquals(400000, $booking->jumlah_bayar); // 2 hours x 200k

        $this->assertDatabaseHas('tb_court_booking_payment', [
            'booking_id' => $booking->booking_id,
            'provider' => 'online',
            'metode_pembayaran' => 'QRIS',
            'jumlah' => 400000,
            'status' => 'Paid',
        ]);
    }

    public function test_double_booking_prevention_rejects_second_user_booking_same_slot(): void
    {
        $owner = User::query()->create([
            'nama' => 'Owner Padel',
            'email' => 'owner3@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $player1 = User::query()->create([
            'nama' => 'User Pertama',
            'email' => 'user1@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);
        $player2 = User::query()->create([
            'nama' => 'User Kedua',
            'email' => 'user2@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Padel Club Jakarta',
            'jam_operasional' => '07:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 2',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 100000,
        ]);

        $date = now()->addDays(2)->toDateString();

        $bookingPayload = [
            'court_id' => $court->court_id,
            'tanggal' => $date,
            'jam_mulai' => '14:00',
            'jam_selesai' => '15:00',
            'nama_penyewa' => 'User Pertama',
            'kontak_penyewa' => '0811111111',
            'metode_pembayaran' => 'QRIS',
        ];

        // User 1 books slot 14:00 - 15:00 -> SUCCESS
        $this->actingAs($player1)
            ->post(route('player.bookings.store'), $bookingPayload)
            ->assertRedirect(route('player.bookings.index'));

        $this->assertSame(1, CourtBooking::query()->count());

        // User 2 attempts to book the EXACT SAME slot -> MUST BE REJECTED with Validation Error
        $conflictPayload = [
            'court_id' => $court->court_id,
            'tanggal' => $date,
            'jam_mulai' => '14:00',
            'jam_selesai' => '15:00',
            'nama_penyewa' => 'User Kedua',
            'kontak_penyewa' => '0822222222',
            'metode_pembayaran' => 'Transfer Bank - BCA',
        ];

        $response = $this->actingAs($player2)
            ->from(route('user.dashboard'))
            ->post(route('player.bookings.store'), $conflictPayload);

        $response->assertRedirect(route('user.dashboard'))
            ->assertSessionHasErrors('jam_mulai');

        // Only 1 booking must exist in database!
        $this->assertSame(1, CourtBooking::query()->count());
    }

    public function test_owner_booking_and_user_booking_mutually_lock_each_other(): void
    {
        $owner = User::query()->create([
            'nama' => 'Venue Owner Mitra',
            'email' => 'owner4@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $player = User::query()->create([
            'nama' => 'Player Antrian',
            'email' => 'player_queue@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Matcha Tennis Bandung',
            'jam_operasional' => '08:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court Tanah Liat',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 120000,
        ]);

        $date = now()->addDay()->toDateString();

        // 1. Owner enters a manual booking for slot 16:00 - 17:00
        $this->actingAs($owner)->post(route('owner.bookings.store', $court->court_id), [
            'tanggal' => $date,
            'jam_mulai' => '16:00',
            'jam_selesai' => '17:00',
            'nama_penyewa' => 'Pelanggan Walk-in',
            'status_pembayaran' => 'Paid',
        ])->assertRedirect();

        // 2. User attempts to book overlapping slot 16:00 - 17:00 -> REJECTED!
        $this->actingAs($player)
            ->from(route('user.dashboard'))
            ->post(route('player.bookings.store'), [
                'court_id' => $court->court_id,
                'tanggal' => $date,
                'jam_mulai' => '16:00',
                'jam_selesai' => '17:00',
                'nama_penyewa' => 'Player Antrian',
            ])
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHasErrors('jam_mulai');

        // 3. Owner cancels the manual booking
        $manualBooking = CourtBooking::query()->firstOrFail();
        $this->actingAs($owner)->post(route('owner.bookings.cancel', $manualBooking->booking_id), [
            'alasan_pembatalan' => 'Pelanggan walk-in batal bermain',
        ])->assertRedirect();

        // 4. Now the slot becomes AVAILABLE again and User can book it!
        $this->actingAs($player)
            ->post(route('player.bookings.store'), [
                'court_id' => $court->court_id,
                'tanggal' => $date,
                'jam_mulai' => '16:00',
                'jam_selesai' => '17:00',
                'nama_penyewa' => 'Player Antrian',
                'metode_pembayaran' => 'QRIS',
            ])
            ->assertRedirect(route('player.bookings.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, CourtBooking::query()->count());
        $activeBooking = CourtBooking::query()->where('status', 'Booked')->firstOrFail();
        $this->assertEquals($player->user_id, $activeBooking->created_by);
    }

    public function test_my_bookings_page_displays_user_bookings_and_owner_cancellation_reason(): void
    {
        $owner = User::query()->create([
            'nama' => 'Owner Utama',
            'email' => 'owner5@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'venue_owner',
        ]);
        $player = User::query()->create([
            'nama' => 'Andi Wijaya',
            'email' => 'andi@matcha.test',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $venue = Venue::query()->create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => 'Arena Sport Club',
            'jam_operasional' => '07:00 - 22:00',
        ]);
        $court = Court::query()->create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court Indoor A',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 180000,
        ]);

        $date = now()->addDays(3)->toDateString();

        // Create booking for Andi
        $booking = CourtBooking::query()->create([
            'court_id' => $court->court_id,
            'tanggal' => $date,
            'jam_mulai' => '18:00',
            'jam_selesai' => '20:00',
            'nama_penyewa' => 'Andi Wijaya',
            'jumlah_bayar' => 360000,
            'status' => 'Booked',
            'status_pembayaran' => 'Paid',
            'sumber' => 'online',
            'created_by' => $player->user_id,
        ]);

        // Owner cancels Andi's booking with reason
        $cancellationReason = 'Lapangan perlu perbaikan darurat pada lampu sorot';
        $this->actingAs($owner)->post(route('owner.bookings.cancel', $booking->booking_id), [
            'alasan_pembatalan' => $cancellationReason,
        ])->assertRedirect();

        // Andi visits his "Booking Saya" page
        $response = $this->actingAs($player)->get(route('player.bookings.index'));

        $response->assertOk()
            ->assertSee('Booking Saya')
            ->assertSee('Court Indoor A')
            ->assertSee('Arena Sport Club')
            ->assertSee('Cancelled')
            ->assertSee('Dibatalkan oleh Pengelola Venue')
            ->assertSee($cancellationReason);
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
                $table->string('no_hp')->nullable();
                $table->string('foto')->nullable();
                $table->boolean('is_host')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_sport')) {
            Schema::create('tb_sport', function (Blueprint $table): void {
                $table->id('sport_id');
                $table->string('nama_sport');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_venue')) {
            Schema::create('tb_venue', function (Blueprint $table): void {
                $table->id('venue_id');
                $table->unsignedBigInteger('owner_user_id');
                $table->string('nama_venue');
                $table->string('jam_operasional')->nullable();
                $table->string('alamat')->nullable();
                $table->string('kota')->nullable();
                $table->string('main_photo')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function (Blueprint $table): void {
                $table->id('court_id');
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->string('nama_court');
                $table->string('status_ketersediaan')->default('Available');
                $table->string('tipe_court')->nullable();
                $table->string('image_url')->nullable();
                $table->text('deskripsi')->nullable();
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

        if (! Schema::hasTable('tb_venue_facility')) {
            Schema::create('tb_venue_facility', function (Blueprint $table): void {
                $table->id('facility_id');
                $table->unsignedBigInteger('venue_id');
                $table->string('nama_fasilitas');
                $table->text('deskripsi')->nullable();
                $table->text('foto')->nullable();
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court_facility')) {
            Schema::create('tb_court_facility', function (Blueprint $table): void {
                $table->unsignedBigInteger('court_id');
                $table->unsignedBigInteger('facility_id');
                $table->primary(['court_id', 'facility_id']);
            });
        }
    }
}
