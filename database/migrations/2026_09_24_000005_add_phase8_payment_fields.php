<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type')->default('OVERDUE_FINE')->after('fine_id');
            $table->text('description')->nullable()->after('reference_number');
        });
        DB::statement("ALTER TABLE payments MODIFY status VARCHAR(20) NOT NULL DEFAULT 'PAID'");
        DB::statement("UPDATE payments SET payment_type = 'OVERDUE_FINE' WHERE payment_type IS NULL OR payment_type = ''");
        DB::statement("UPDATE payments SET status = 'PAID' WHERE status = 'completed'");
        DB::statement("UPDATE payments SET status = 'PENDING' WHERE status = 'pending'");
        DB::statement("UPDATE payments SET status = 'CANCELLED' WHERE status = 'failed'");

        Schema::table('fines', function (Blueprint $table) {
            $table->date('issued_date')->nullable()->after('amount');
            $table->date('paid_date')->nullable()->after('status');
        });
        DB::statement("ALTER TABLE fines MODIFY fine_type VARCHAR(40) NOT NULL DEFAULT 'OVERDUE'");
        DB::statement("ALTER TABLE fines MODIFY status VARCHAR(20) NOT NULL DEFAULT 'UNPAID'");
        DB::statement("UPDATE fines SET fine_type = 'OVERDUE' WHERE fine_type = 'late_return'");
        DB::statement("UPDATE fines SET fine_type = 'DAMAGE_COMPENSATION' WHERE fine_type = 'damage'");
        DB::statement("UPDATE fines SET fine_type = 'LOST_BOOK_COMPENSATION' WHERE fine_type = 'lost_book'");
        DB::statement("UPDATE fines SET fine_type = 'OTHER' WHERE fine_type = 'other'");
        DB::statement("UPDATE fines SET status = 'UNPAID' WHERE status = 'pending'");
        DB::statement("UPDATE fines SET status = 'PAID' WHERE status = 'paid'");
        DB::statement("UPDATE fines SET status = 'WAIVED' WHERE status = 'waived'");
        DB::statement("UPDATE fines SET issued_date = DATE(created_at) WHERE issued_date IS NULL");
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'description']);
        });
        Schema::table('fines', function (Blueprint $table) {
            $table->dropColumn(['issued_date', 'paid_date']);
        });
    }
};
