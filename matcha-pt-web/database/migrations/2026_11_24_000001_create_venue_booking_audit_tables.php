<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_court_booking_payment', function (Blueprint $table) {
            $table->bigIncrements('payment_id');
            $table->unsignedBigInteger('booking_id')->index();
            $table->string('provider', 40)->default('manual');
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('metode_pembayaran', 40)->nullable();
            $table->decimal('jumlah', 12, 2)->default(0);
            $table->string('status', 30)->default('Pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tb_court_booking_cancellation', function (Blueprint $table) {
            $table->bigIncrements('cancellation_id');
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->string('previous_status', 30);
            $table->string('previous_payment_status', 30);
            $table->text('reason');
            $table->timestamp('cancelled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_court_booking_cancellation');
        Schema::dropIfExists('tb_court_booking_payment');
    }
};
