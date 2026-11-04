<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pradeshiya_sabhas', function (Blueprint $table) {
            if (! Schema::hasColumn('pradeshiya_sabhas', 'address')) {
                $table->text('address')->nullable();
            }
            if (! Schema::hasColumn('pradeshiya_sabhas', 'phone')) {
                $table->string('phone', 50)->nullable();
            }
            if (! Schema::hasColumn('pradeshiya_sabhas', 'email')) {
                $table->string('email')->nullable();
            }
        });

        foreach (['authors', 'publishers', 'categories'] as $name) {
            if (! Schema::hasColumn($name, 'pradeshiya_sabha_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->foreignId('pradeshiya_sabha_id')
                        ->nullable()
                        ->constrained('pradeshiya_sabhas')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['categories', 'publishers', 'authors'] as $name) {
            if (Schema::hasColumn($name, 'pradeshiya_sabha_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('pradeshiya_sabha_id');
                });
            }
        }

        Schema::table('pradeshiya_sabhas', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'email']);
        });
    }
};