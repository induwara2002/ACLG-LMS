<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('borrowings', 'renewal_count')) {
            Schema::table('borrowings', function (Blueprint $table) {
                $table->unsignedInteger('renewal_count')->default(0)->after('status');
                $table->index(['member_id', 'status']);
                $table->index(['book_copy_id', 'status']);
            });
        }

        DB::statement("ALTER TABLE borrowings MODIFY status VARCHAR(20) NOT NULL DEFAULT 'ISSUED'");
        DB::statement("UPDATE borrowings SET status = UPPER(status)");
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropIndex(['member_id', 'status']);
            $table->dropIndex(['book_copy_id', 'status']);
            $table->dropColumn('renewal_count');
        });
    }
};