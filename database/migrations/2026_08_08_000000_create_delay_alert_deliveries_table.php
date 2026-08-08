<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delay_alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->date('expected_arrival');
            $table->string('event', 64)->default('shipment.delayed');
            $table->string('channel', 24);
            $table->string('audience', 24);
            $table->string('destination');
            $table->char('destination_hash', 64);
            $table->text('message');
            $table->string('status', 24)->default('pending')->index();
            $table->uuid('processing_token')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['shipment_id', 'expected_arrival', 'event', 'channel', 'destination_hash'],
                'delay_alert_unique'
            );
            $table->index(['status', 'last_attempt_at'], 'delay_alert_retry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delay_alert_deliveries');
    }
};
