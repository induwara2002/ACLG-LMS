<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->restrictOnDelete();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('book_id')->unique()->after('id');
            $table->string('ddc_number', 20)->after('category_id');
            $table->string('shelf_number')->nullable()->after('ddc_number');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['book_id']);
            $table->dropColumn(['book_id', 'ddc_number', 'shelf_number']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};