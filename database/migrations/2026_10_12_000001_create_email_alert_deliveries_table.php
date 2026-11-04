<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 64);
            $table->string('alertable_type', 32);
            $table->unsignedBigInteger('alertable_id');
            $table->date('period_date');
            $table->timestamp('reserved_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['alert_type', 'alertable_type', 'alertable_id', 'period_date'],
                'email_alert_delivery_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_alert_deliveries');
    }
};
