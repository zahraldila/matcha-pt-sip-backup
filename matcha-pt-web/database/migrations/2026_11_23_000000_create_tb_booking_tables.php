<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_booking')) {
            return;
        }

        Schema::create('tb_booking', function (Blueprint $table) {
            $table->id('booking_id');
            $table->string('booking_code', 32)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('court_id');

            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('duration_hours', 4, 2);
            $table->decimal('total_price', 12, 2);

            $table->string('customer_name');
            $table->string('customer_phone', 30);

            $table->string('booking_status')->default('confirmed');
            $table->string('payment_method')->default('cash');
            $table->string('payment_status')->default('unpaid');

            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('tb_user')
                ->restrictOnDelete();

            $table->foreign('court_id')
                ->references('court_id')
                ->on('tb_court')
                ->restrictOnDelete();

            $table->index(['court_id', 'booking_date', 'booking_status']);
        });

        Schema::create('tb_booking_slot', function (Blueprint $table) {
            $table->id('booking_slot_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('court_id');
            $table->date('slot_date');
            $table->time('slot_start');

            $table->timestamps();

            $table->foreign('booking_id')
                ->references('booking_id')
                ->on('tb_booking')
                ->cascadeOnDelete();

            $table->foreign('court_id')
                ->references('court_id')
                ->on('tb_court')
                ->restrictOnDelete();

            $table->unique(
                ['court_id', 'slot_date', 'slot_start'],
                'tb_booking_slot_court_date_start_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_booking_slot');
        Schema::dropIfExists('tb_booking');
    }
};
