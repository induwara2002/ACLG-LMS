<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || Schema::hasColumn('categories', 'code')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->text('description')->nullable()->after('name');
        });

        DB::statement("UPDATE categories SET code = LPAD(id, 3, '0') WHERE code IS NULL");
        DB::statement("ALTER TABLE categories MODIFY code VARCHAR(255) NOT NULL");
        Schema::table('categories', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        // Legacy category columns and data are intentionally retained.
    }
};