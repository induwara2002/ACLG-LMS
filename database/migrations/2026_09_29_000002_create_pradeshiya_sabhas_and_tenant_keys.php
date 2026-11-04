<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantTables = [
        'users',
        'members',
        'books',
        'book_copies',
        'borrowings',
        'renewals',
        'membership_payments',
        'payments',
        'fines',
        'lost_books',
        'damage_records',
        'audit_logs',
    ];

    public function up(): void
    {
        Schema::create('pradeshiya_sabhas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name')->unique();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        foreach ($this->tenantTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('pradeshiya_sabha_id')
                    ->nullable()
                    ->constrained('pradeshiya_sabhas')
                    ->nullOnDelete();
            });
        }

        Schema::create('pradeshiya_sabha_admin_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pradeshiya_sabha_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pradeshiya_sabha_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pradeshiya_sabha_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['pradeshiya_sabha_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pradeshiya_sabha_settings');
        Schema::dropIfExists('pradeshiya_sabha_admin_assignments');

        foreach (array_reverse($this->tenantTables) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('pradeshiya_sabha_id');
            });
        }

        Schema::dropIfExists('pradeshiya_sabhas');
    }
};