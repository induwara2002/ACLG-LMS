<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->onDelete('set null');
            $table->date('lost_date');
            $table->decimal('replacement_cost', 10, 2)->default(0);
            $table->enum('status', ['reported', 'replacement_pending', 'replaced', 'resolved'])->default('reported');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_books');
    }
};
