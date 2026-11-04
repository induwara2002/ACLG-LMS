<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('borrowing_id')->nullable()->constrained('borrowings')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->enum('fine_type', ['late_return', 'lost_book', 'damage', 'other'])->default('late_return');
            $table->enum('status', ['pending', 'paid', 'waived'])->default('pending');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
