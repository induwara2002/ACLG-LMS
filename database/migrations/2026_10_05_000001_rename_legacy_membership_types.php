<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameOrMerge('INDIVIDUAL', 'Children');
        $this->renameOrMerge('FAMILY', 'Elders');
    }

    public function down(): void
    {
        DB::table('membership_types')->whereRaw('upper(name) = ?', ['CHILDREN'])->update(['name' => 'Individual']);
        DB::table('membership_types')->whereRaw('upper(name) = ?', ['ELDERS'])->update(['name' => 'Family']);
    }

    private function renameOrMerge(string $legacyName, string $currentName): void
    {
        $legacy = DB::table('membership_types')->whereRaw('upper(name) = ?', [$legacyName])->first();
        if (! $legacy) {
            return;
        }

        $current = DB::table('membership_types')->whereRaw('upper(name) = ?', [strtoupper($currentName)])->first();
        if ($current) {
            DB::table('members')->where('membership_type_id', $legacy->id)->update(['membership_type_id' => $current->id]);
            DB::table('membership_types')->where('id', $legacy->id)->delete();

            return;
        }

        DB::table('membership_types')->where('id', $legacy->id)->update([
            'name' => $currentName,
            'description' => 'Membership for '.strtolower($currentName).'.',
        ]);
    }
};