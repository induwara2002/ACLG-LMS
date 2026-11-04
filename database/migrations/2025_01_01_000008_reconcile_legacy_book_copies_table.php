<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_copies') || Schema::hasColumn('book_copies', 'accession_number')) {
            return;
        }

        Schema::table('book_copies', function (Blueprint $table) {
            $table->string('accession_number')->nullable()->after('book_id');
        });

        DB::statement("UPDATE book_copies SET accession_number = copy_code WHERE accession_number IS NULL");
        DB::statement("ALTER TABLE book_copies MODIFY accession_number VARCHAR(255) NOT NULL");
        Schema::table('book_copies', function (Blueprint $table) {
            $table->unique('accession_number');
        });
    }

    public function down(): void
    {
        // Legacy copy columns and data are intentionally retained.
    }
};