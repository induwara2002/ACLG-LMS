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
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE members MODIFY status ENUM('PENDING', 'ACTIVE', 'REJECTED', 'SUSPENDED', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE'");
        }
    }

    public function down(): void
    {
        if (DB::table('members')->whereIn('status', ['PENDING', 'REJECTED'])->exists()) {
            throw new \RuntimeException('Cannot roll back membership approval while pending or rejected applications exist.');
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE members MODIFY status ENUM('ACTIVE', 'SUSPENDED', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE'");
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'rejected_at', 'rejection_reason']);
            $table->dropConstrainedForeignId('rejected_by');
        });
    }
};
