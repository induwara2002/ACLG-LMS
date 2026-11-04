<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->index('member_id');
            $table->index('payment_type');
            $table->index('status');
            $table->index('reference');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('member_id');
            $table->index('payment_type');
            $table->index('status');
            $table->index('reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropIndex(['member_id']);
            $table->dropIndex(['payment_type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['reference']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['member_id']);
            $table->dropIndex(['payment_type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['reference_number']);
        });
    }
};
