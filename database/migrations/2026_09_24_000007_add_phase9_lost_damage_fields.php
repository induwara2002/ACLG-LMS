<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function foreignKeyExists(string $table, string $column, string $referencedTable): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(*) AS count
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?",
            [$table, $column, $referencedTable]
        );

        return (int) ($result->count ?? 0) > 0;
    }

    public function up(): void
    {
        if (! Schema::hasColumn('lost_books', 'borrowing_id')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->foreignId('borrowing_id')->nullable()->after('member_id');
            });
        }

        if (! $this->foreignKeyExists('lost_books', 'borrowing_id', 'borrowings')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->foreign('borrowing_id')->references('id')->on('borrowings')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('lost_books', 'compensation_amount')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->decimal('compensation_amount', 10, 2)->nullable()->after('replacement_cost');
            });
        }

        if (! Schema::hasColumn('lost_books', 'payment_status')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->string('payment_status')->default('UNPAID')->after('status');
            });
        }

        if (! Schema::hasColumn('lost_books', 'created_by')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('notes');
            });
        }

        if (! $this->foreignKeyExists('lost_books', 'created_by', 'users')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('lost_books', 'status')) {
            DB::statement("ALTER TABLE lost_books MODIFY status VARCHAR(30) NOT NULL DEFAULT 'REPORTED'");
            DB::statement("UPDATE lost_books SET status = 'REPORTED' WHERE status IN ('reported', 'replacement_pending')");
            DB::statement("UPDATE lost_books SET status = 'RESOLVED' WHERE status IN ('replaced', 'resolved')");
        }

        if (Schema::hasColumn('lost_books', 'payment_status')) {
            DB::statement("UPDATE lost_books SET payment_status = 'UNPAID' WHERE payment_status IS NULL OR payment_status = ''");
        }

        if (Schema::hasColumn('lost_books', 'compensation_amount') && Schema::hasColumn('lost_books', 'replacement_cost')) {
            DB::statement("UPDATE lost_books SET compensation_amount = replacement_cost WHERE compensation_amount IS NULL AND replacement_cost IS NOT NULL");
        }

        if (! Schema::hasColumn('damage_records', 'borrowing_id')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->foreignId('borrowing_id')->nullable()->after('member_id');
            });
        }

        if (! $this->foreignKeyExists('damage_records', 'borrowing_id', 'borrowings')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->foreign('borrowing_id')->references('id')->on('borrowings')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('damage_records', 'compensation_amount')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->decimal('compensation_amount', 10, 2)->nullable()->after('repair_cost');
            });
        }

        if (! Schema::hasColumn('damage_records', 'payment_status')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->string('payment_status')->default('UNPAID')->after('status');
            });
        }

        if (! Schema::hasColumn('damage_records', 'created_by')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('status');
            });
        }

        if (! $this->foreignKeyExists('damage_records', 'created_by', 'users')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('damage_records', 'damage_type')) {
            DB::statement("ALTER TABLE damage_records MODIFY damage_type VARCHAR(20) NOT NULL DEFAULT 'MAJOR'");
            DB::statement("UPDATE damage_records SET damage_type = 'MINOR' WHERE damage_type IN ('physical', 'missing_pages', 'water_damage', 'binding', 'other')");
        }

        if (Schema::hasColumn('damage_records', 'status')) {
            DB::statement("ALTER TABLE damage_records MODIFY status VARCHAR(30) NOT NULL DEFAULT 'REPORTED'");
            DB::statement("UPDATE damage_records SET status = 'REPORTED' WHERE status IN ('reported')");
            DB::statement("UPDATE damage_records SET status = 'UNDER_REPAIR' WHERE status IN ('under_repair')");
            DB::statement("UPDATE damage_records SET status = 'REPAIRED' WHERE status IN ('repaired')");
            DB::statement("UPDATE damage_records SET status = 'DISCARDED' WHERE status IN ('discarded')");
        }

        if (Schema::hasColumn('damage_records', 'compensation_amount') && Schema::hasColumn('damage_records', 'repair_cost')) {
            DB::statement("UPDATE damage_records SET compensation_amount = repair_cost WHERE compensation_amount IS NULL AND repair_cost IS NOT NULL");
        }

        if (Schema::hasColumn('damage_records', 'payment_status')) {
            DB::statement("UPDATE damage_records SET payment_status = 'UNPAID' WHERE payment_status IS NULL OR payment_status = ''");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('damage_records', 'borrowing_id')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->dropConstrainedForeignId('borrowing_id');
            });
        }

        if (Schema::hasColumn('damage_records', 'created_by')) {
            Schema::table('damage_records', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        Schema::table('damage_records', function (Blueprint $table) {
            $table->dropColumn(['compensation_amount', 'payment_status', 'created_by']);
        });

        if (Schema::hasColumn('lost_books', 'borrowing_id')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->dropConstrainedForeignId('borrowing_id');
            });
        }

        if (Schema::hasColumn('lost_books', 'created_by')) {
            Schema::table('lost_books', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        Schema::table('lost_books', function (Blueprint $table) {
            $table->dropColumn(['compensation_amount', 'payment_status', 'created_by']);
        });
    }
};
