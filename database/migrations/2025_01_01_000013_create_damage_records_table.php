<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->onDelete('set null');
            $table->enum('damage_type', ['physical', 'missing_pages', 'water_damage', 'binding', 'other'])->default('physical');
            $table->date('damage_date');
            $table->text('description')->nullable();
            $table->decimal('repair_cost', 10, 2)->default(0);
            $table->enum('status', ['reported', 'under_repair', 'repaired', 'discarded'])->default('reported');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_records');
    }
};
