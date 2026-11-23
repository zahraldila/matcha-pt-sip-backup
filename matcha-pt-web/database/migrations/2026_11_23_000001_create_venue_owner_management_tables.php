<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_venue_facility', function (Blueprint $table) {
            $table->bigIncrements('facility_id');
            $table->unsignedBigInteger('venue_id')->index();
            $table->string('nama_fasilitas', 120);
            $table->text('deskripsi')->nullable();
            $table->text('foto')->nullable();
            $table->unsignedBigInteger('sport_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tb_court_facility', function (Blueprint $table) {
            $table->unsignedBigInteger('court_id');
            $table->unsignedBigInteger('facility_id');
            $table->primary(['court_id', 'facility_id']);
            $table->index('facility_id');
        });

        Schema::create('tb_court_schedule', function (Blueprint $table) {
            $table->bigIncrements('schedule_id');
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

        Schema::create('tb_court_slot_block', function (Blueprint $table) {
            $table->bigIncrements('block_id');
            $table->unsignedBigInteger('court_id');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('alasan', 180)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'tanggal']);
        });

        Schema::create('tb_court_booking', function (Blueprint $table) {
            $table->bigIncrements('booking_id');
            $table->unsignedBigInteger('court_id');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('nama_penyewa', 120);
            $table->string('kontak_penyewa', 80)->nullable();
            $table->decimal('jumlah_bayar', 12, 2)->default(0);
            $table->string('status', 30)->default('Booked');
            $table->string('status_pembayaran', 30)->default('Pending');
            $table->string('sumber', 20)->default('manual');
            $table->text('catatan')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_court_booking');
        Schema::dropIfExists('tb_court_slot_block');
        Schema::dropIfExists('tb_court_schedule');
        Schema::dropIfExists('tb_court_facility');
        Schema::dropIfExists('tb_venue_facility');
    }
};
