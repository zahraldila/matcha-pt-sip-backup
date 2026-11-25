<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use App\Services\VenueBookingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VenueBookingTest extends TestCase
{
    private VenueBookingService $service;

    private User $user;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        $this->user = User::create([
            'nama' => 'Pemain Test',
            'email' => 'booking@example.test',
            'password' => 'password',
            'role' => 'member',
        ]);

        $venue = Venue::create([
            'nama_venue' => 'Venue Test',
            'alamat' => 'Jakarta',
            'jam_operasional' => '08:00 - 12:00',
            'hari_buka' => 'Setiap Hari (Senin - Minggu)',
        ]);

        $this->court = Court::create([
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 1',
            'status_ketersediaan' => 'Available',
            'harga_per_jam' => 300000,
        ]);

        $this->service = app(VenueBookingService::class);
    }

    public function test_booking_sukses_dan_status_cash_unpaid(): void
    {
        $booking = $this->service->createBooking($this->bookingData('09:00', '11:00'), (int) $this->user->user_id);

        $this->assertDatabaseHas('tb_booking', [
            'booking_id' => $booking->booking_id,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'booking_status' => 'pending',
        ]);
        $confirmed = $this->service->confirmBooking($booking->booking_id, (int) $this->user->user_id);
        $this->assertSame('confirmed', $confirmed->booking_status);
        $this->assertCount(2, $booking->slots);
    }

    public function test_double_booking_ditolak(): void
    {
        $this->service->createBooking($this->bookingData('09:00', '11:00'), (int) $this->user->user_id);

        try {
            $this->service->createBooking($this->bookingData('09:00', '10:00'), (int) $this->user->user_id);
            $this->fail('Booking bentrok seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame('Slot sudah dipesan orang lain', $exception->errors()['slot'][0]);
        }
    }

    public function test_batal_membebaskan_slot(): void
    {
        $booking = $this->service->createBooking($this->bookingData('09:00', '10:00'), (int) $this->user->user_id);

        $this->service->cancelBooking((int) $booking->booking_id, (int) $this->user->user_id);

        $this->assertDatabaseMissing('tb_booking_slot', [
            'booking_id' => $booking->booking_id,
        ]);
        $this->assertDatabaseHas('tb_booking', [
            'booking_id' => $booking->booking_id,
            'booking_status' => 'cancelled',
        ]);

        $replacement = $this->service->createBooking($this->bookingData('09:00', '10:00'), (int) $this->user->user_id);
        $this->assertNotNull($replacement->booking_id);
    }

    public function test_harga_dihitung_dari_harga_court_dan_durasi(): void
    {
        $booking = $this->service->createBooking($this->bookingData('08:00', '11:00'), (int) $this->user->user_id);

        $this->assertEquals(900000, (float) $booking->total_price);
        $this->assertEquals(3, (float) $booking->duration_hours);
    }

    private function bookingData(string $start, string $end): array
    {
        return [
            'court_id' => $this->court->court_id,
            'booking_date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $end,
            'customer_name' => 'Pemain Test',
            'customer_phone' => '081234567890',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('tb_user', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('nama');
            $table->string('no_hp')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('member');
            $table->timestamps();
        });

        Schema::create('tb_venue', function (Blueprint $table) {
            $table->id('venue_id');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('nama_venue');
            $table->string('alamat')->nullable();
            $table->string('jam_operasional')->nullable();
            $table->string('hari_buka')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_venue_avail', function (Blueprint $table) {
            $table->id('availability_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('hari')->nullable();
            $table->time('jam_buka')->nullable();
            $table->time('jam_tutup')->nullable();
            $table->string('booking_status')->nullable();
        });

        Schema::create('tb_court', function (Blueprint $table) {
            $table->id('court_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('nama_court');
            $table->string('status_ketersediaan')->default('Available');
            $table->decimal('harga_per_jam', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tb_booking', function (Blueprint $table) {
            $table->id('booking_id');
            $table->string('booking_code')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('court_id');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('duration_hours', 4, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('booking_status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->string('payment_method')->default('cash');
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('tb_booking_slot', function (Blueprint $table) {
            $table->id('booking_slot_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('court_id');
            $table->date('slot_date');
            $table->time('slot_start');
            $table->timestamps();
            $table->unique(['court_id', 'slot_date', 'slot_start']);
        });
    }
}
