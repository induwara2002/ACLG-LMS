<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pradeshiya_sabha_id')->constrained('pradeshiya_sabhas')->restrictOnDelete();
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->string('shelf_location', 100)->nullable();
            $table->timestamps();
            $table->unique(['pradeshiya_sabha_id', 'book_id'], 'book_inventories_sabha_book_unique');
            $table->index(['pradeshiya_sabha_id', 'available_quantity'], 'book_inventories_sabha_available_index');
        });

        DB::table('book_copies')
            ->whereNull('pradeshiya_sabha_id')
            ->orderBy('id')
            ->chunkById(500, function ($copies): void {
                foreach ($copies as $copy) {
                    $sabhaId = DB::table('books')->where('id', $copy->book_id)->value('pradeshiya_sabha_id');
                    if ($sabhaId !== null) {
                        DB::table('book_copies')->where('id', $copy->id)->whereNull('pradeshiya_sabha_id')
                            ->update(['pradeshiya_sabha_id' => $sabhaId]);
                    }
                }
            });

        $inventoryRows = [];
        $copyGroups = DB::table('book_copies')
            ->whereNotNull('pradeshiya_sabha_id')
            ->select('pradeshiya_sabha_id', 'book_id')
            ->selectRaw('COUNT(*) AS total_quantity')
            ->selectRaw("SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END) AS available_quantity")
            ->groupBy('pradeshiya_sabha_id', 'book_id')
            ->get();

        foreach ($copyGroups as $group) {
            $book = DB::table('books')->where('id', $group->book_id)->first(['pradeshiya_sabha_id', 'shelf_number']);
            $inventoryRows[] = [
                'pradeshiya_sabha_id' => $group->pradeshiya_sabha_id,
                'book_id' => $group->book_id,
                'total_quantity' => $group->total_quantity,
                'available_quantity' => $group->available_quantity,
                'shelf_location' => $book && (int) $book->pradeshiya_sabha_id === (int) $group->pradeshiya_sabha_id
                    ? $book->shelf_number
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $knownPairs = [];
        foreach ($inventoryRows as $row) {
            $knownPairs[$row['pradeshiya_sabha_id'].':'.$row['book_id']] = true;
        }

        foreach (DB::table('books')->whereNotNull('pradeshiya_sabha_id')->orderBy('id')->cursor() as $book) {
            $key = $book->pradeshiya_sabha_id.':'.$book->id;
            if (isset($knownPairs[$key])) {
                continue;
            }

            $inventoryRows[] = [
                'pradeshiya_sabha_id' => $book->pradeshiya_sabha_id,
                'book_id' => $book->id,
                'total_quantity' => 0,
                'available_quantity' => 0,
                'shelf_location' => $book->shelf_number,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($inventoryRows, 500) as $chunk) {
            DB::table('book_inventories')->insert($chunk);
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('book_inventories');
    }
};