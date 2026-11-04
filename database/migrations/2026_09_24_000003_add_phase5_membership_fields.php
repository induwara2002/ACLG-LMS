<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('full_name');
            $table->date('expiry_date')->nullable()->after('next_renewal_date');
        });

        DB::statement("UPDATE members m INNER JOIN users u ON u.id = m.user_id SET m.email = u.email WHERE m.email IS NULL");

        DB::statement("UPDATE members SET status = 'SUSPENDED' WHERE status = 'INACTIVE'");
        DB::statement("ALTER TABLE members MODIFY status ENUM('ACTIVE', 'EXPIRED', 'SUSPENDED') NOT NULL DEFAULT 'ACTIVE'");

        Schema::table('membership_payments', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('payment_date');
            $table->string('status')->default('PAID')->after('reference');
        });

        DB::statement("ALTER TABLE membership_payments MODIFY payment_type VARCHAR(40) NOT NULL DEFAULT 'REGISTRATION'");
        DB::statement("UPDATE membership_payments SET payment_type = 'REGISTRATION' WHERE payment_type = 'registration'");
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropColumn(['reference', 'status']);
        });

        DB::statement("ALTER TABLE membership_payments MODIFY payment_type ENUM('registration', 'renewal', 'penalty', 'other') NOT NULL DEFAULT 'registration'");

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'expiry_date']);
        });

        DB::statement("ALTER TABLE members MODIFY status ENUM('ACTIVE', 'INACTIVE', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE'");
    }
};
