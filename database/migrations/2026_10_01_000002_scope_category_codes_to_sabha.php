<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_code_unique');
            $table->unique(['pradeshiya_sabha_id', 'code'], 'categories_sabha_code_unique');
        });
    }

    public function down(): void
    {
        $hasDuplicateCodes = DB::table('categories')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateCodes) {
            throw new RuntimeException('Cannot restore global category-code uniqueness while tenant duplicate codes exist.');
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_sabha_code_unique');
            $table->unique('code', 'categories_code_unique');
        });
    }
};