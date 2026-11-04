<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BookInventoryService
{
    public function copyCreated(array $copy): void
    {
        if (! isset($copy['pradeshiya_sabha_id'])) {
            return;
        }

        $this->adjust(
            (int) $copy['pradeshiya_sabha_id'],
            (int) $copy['book_id'],
            1,
            strtoupper((string) $copy['status']) === 'AVAILABLE' ? 1 : 0
        );
    }

    public function copyUpdated(array $before, array $after): void
    {
        $oldSabhaId = isset($before['pradeshiya_sabha_id']) ? (int) $before['pradeshiya_sabha_id'] : null;
        $newSabhaId = isset($after['pradeshiya_sabha_id']) ? (int) $after['pradeshiya_sabha_id'] : null;
        $oldBookId = (int) $before['book_id'];
        $newBookId = (int) $after['book_id'];
        $wasAvailable = strtoupper((string) ($before['status'] ?? '')) === 'AVAILABLE';
        $isAvailable = strtoupper((string) ($after['status'] ?? '')) === 'AVAILABLE';

        if ($oldSabhaId === $newSabhaId && $oldBookId === $newBookId) {
            if ($oldSabhaId !== null && $wasAvailable !== $isAvailable) {
                $this->adjust($oldSabhaId, $oldBookId, 0, $isAvailable ? 1 : -1);
            }

            return;
        }

        if ($oldSabhaId !== null) {
            $this->adjust($oldSabhaId, $oldBookId, -1, $wasAvailable ? -1 : 0);
        }
        if ($newSabhaId !== null) {
            $this->adjust($newSabhaId, $newBookId, 1, $isAvailable ? 1 : 0);
        }
    }

    public function copyDeleted(array $copy): void
    {
        if (isset($copy['pradeshiya_sabha_id'])) {
            $this->adjust(
                (int) $copy['pradeshiya_sabha_id'],
                (int) $copy['book_id'],
                -1,
                strtoupper((string) $copy['status']) === 'AVAILABLE' ? -1 : 0
            );
        }
    }

    public function setShelfLocation(int $sabhaId, int $bookId, ?string $shelfLocation): void
    {
        $now = now();
        DB::table('book_inventories')->upsert([[
            'pradeshiya_sabha_id' => $sabhaId,
            'book_id' => $bookId,
            'total_quantity' => 0,
            'available_quantity' => 0,
            'shelf_location' => $shelfLocation,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['pradeshiya_sabha_id', 'book_id'], ['shelf_location', 'updated_at']);
    }

    public function sync(int $sabhaId, int $bookId): void
    {
        DB::transaction(function () use ($sabhaId, $bookId): void {
            $now = now();
            DB::table('book_inventories')->upsert([[
                'pradeshiya_sabha_id' => $sabhaId,
                'book_id' => $bookId,
                'total_quantity' => 0,
                'available_quantity' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['pradeshiya_sabha_id', 'book_id'], ['updated_at']);

            DB::table('book_inventories')
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('book_id', $bookId)
                ->lockForUpdate()
                ->first();

            $counts = DB::table('book_copies')
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('book_id', $bookId)
                ->selectRaw('COUNT(*) AS total_quantity')
                ->selectRaw("SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END) AS available_quantity")
                ->first();

            DB::table('book_inventories')
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('book_id', $bookId)
                ->update([
                    'total_quantity' => (int) ($counts->total_quantity ?? 0),
                    'available_quantity' => (int) ($counts->available_quantity ?? 0),
                    'updated_at' => $now,
                ]);
        });
    }

    private function adjust(int $sabhaId, int $bookId, int $totalDelta, int $availableDelta): void
    {
        DB::transaction(function () use ($sabhaId, $bookId, $totalDelta, $availableDelta): void {
            $now = now();
            DB::table('book_inventories')->upsert([[
                'pradeshiya_sabha_id' => $sabhaId,
                'book_id' => $bookId,
                'total_quantity' => 0,
                'available_quantity' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['pradeshiya_sabha_id', 'book_id'], ['updated_at']);

            $query = DB::table('book_inventories')
                ->where('pradeshiya_sabha_id', $sabhaId)
                ->where('book_id', $bookId);

            if ($totalDelta > 0) {
                $query->increment('total_quantity', $totalDelta, ['updated_at' => $now]);
            } elseif ($totalDelta < 0) {
                $query->where('total_quantity', '>=', abs($totalDelta))
                    ->decrement('total_quantity', abs($totalDelta), ['updated_at' => $now]);
            }

            if ($availableDelta > 0) {
                DB::table('book_inventories')->where('pradeshiya_sabha_id', $sabhaId)->where('book_id', $bookId)
                    ->increment('available_quantity', $availableDelta, ['updated_at' => $now]);
            } elseif ($availableDelta < 0) {
                DB::table('book_inventories')->where('pradeshiya_sabha_id', $sabhaId)->where('book_id', $bookId)
                    ->where('available_quantity', '>=', abs($availableDelta))
                    ->decrement('available_quantity', abs($availableDelta), ['updated_at' => $now]);
            }
        });
    }
}