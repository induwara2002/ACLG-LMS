<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('members')) {
            return;
        }

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique();
            $table->foreignId('membership_type_id')->constrained('membership_types')->onDelete('restrict');
            $table->string('full_name');
            $table->string('nic')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('registration_fee', 10, 2)->default(220);
            $table->date('registration_date');
            $table->date('membership_start_date');
            $table->date('next_renewal_date')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'EXPIRED'])->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
