<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pradeshiya_sabha_id')->constrained('pradeshiya_sabhas')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->enum('status', ['PENDING', 'CANCELLED', 'FULFILLED', 'EXPIRED'])->default('PENDING');
            $table->timestamp('reserved_at')->useCurrent();
            $table->timestamps();
            $table->index(['pradeshiya_sabha_id', 'member_id', 'status'], 'book_reservations_member_status_index');
            $table->index(['pradeshiya_sabha_id', 'book_id', 'status'], 'book_reservations_book_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_reservations');
    }
};