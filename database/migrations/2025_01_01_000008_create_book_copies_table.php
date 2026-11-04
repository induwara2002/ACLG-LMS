<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('book_copies')) {
            return;
        }

        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->string('accession_number')->unique();
            $table->enum('status', ['AVAILABLE', 'ISSUED', 'LOST', 'DAMAGED', 'REPAIR'])->default('AVAILABLE');
            $table->enum('condition', ['GOOD', 'FAIR', 'DAMAGED'])->default('GOOD');
            $table->date('acquisition_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
