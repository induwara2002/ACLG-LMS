<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'status'"
        );

        if ($column === null || ! preg_match('/^enum\((.*)\)$/i', $column->COLUMN_TYPE, $matches)) {
            throw new RuntimeException('The members.status column must be a MySQL enum before it can be repaired.');
        }

        preg_match_all("/'(?:\\\\.|''|[^'])*'/", $matches[1], $enumValues);
        $values = $enumValues[0];
        if ($values === []) {
            throw new RuntimeException('The members.status enum values could not be read safely.');
        }

        $requiredValues = ["'PENDING'", "'ACTIVE'", "'REJECTED'", "'SUSPENDED'", "'EXPIRED'"];
        $missingValues = array_diff($requiredValues, $values);

        if ($missingValues === []) {
            return;
        }

        $values = array_merge($values, $missingValues);
        DB::statement(
            "ALTER TABLE members MODIFY status ENUM(" . implode(',', $values) . ") NOT NULL DEFAULT 'ACTIVE'"
        );
    }

    public function down(): void
    {
        // Status values are retained to avoid making existing member records invalid.
    }
};
