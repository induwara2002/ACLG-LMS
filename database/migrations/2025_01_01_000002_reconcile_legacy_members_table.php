<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        $baseColumns = [
            'membership_type_id',
            'full_name',
            'registration_fee',
            'registration_date',
            'membership_start_date',
            'next_renewal_date',
            'status',
        ];

        if (collect($baseColumns)->every(fn (string $column) => Schema::hasColumn('members', $column))) {
            return;
        }

        $columns = [
            'membership_type_id' => fn (Blueprint $table) => $table->foreignId('membership_type_id')->nullable()->after('id'),
            'full_name' => fn (Blueprint $table) => $table->string('full_name')->nullable()->after('nic'),
            'registration_fee' => fn (Blueprint $table) => $table->decimal('registration_fee', 10, 2)->nullable()->default(220),
            'registration_date' => fn (Blueprint $table) => $table->date('registration_date')->nullable(),
            'membership_start_date' => fn (Blueprint $table) => $table->date('membership_start_date')->nullable(),
            'next_renewal_date' => fn (Blueprint $table) => $table->date('next_renewal_date')->nullable(),
            'status' => fn (Blueprint $table) => $table->string('status')->nullable()->default('ACTIVE'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('members', $column)) {
                Schema::table('members', $definition);
            }
        }

        $fallbackMembershipType = DB::table('membership_types')->orderBy('id')->value('id');
        if (! $fallbackMembershipType) {
            $fallbackMembershipType = DB::table('membership_types')->insertGetId([
                'name' => 'Individual',
                'max_books' => 2,
                'description' => 'Individual membership for one reader.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement("UPDATE members m LEFT JOIN users u ON u.id = m.user_id SET
            m.full_name = COALESCE(NULLIF(m.full_name, ''), u.name, m.member_id),
            m.membership_type_id = COALESCE(m.membership_type_id, ?),
            m.registration_fee = COALESCE(m.registration_fee, 220),
            m.registration_date = COALESCE(m.registration_date, DATE(m.registered_at), CURRENT_DATE),
            m.membership_start_date = COALESCE(m.membership_start_date, DATE(m.registered_at), CURRENT_DATE),
            m.next_renewal_date = COALESCE(m.next_renewal_date, DATE_ADD(COALESCE(DATE(m.registered_at), CURRENT_DATE), INTERVAL 1 MONTH)),
            m.status = COALESCE(NULLIF(m.status, ''), 'ACTIVE')", [$fallbackMembershipType]);

        DB::statement("ALTER TABLE members MODIFY membership_type_id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE members MODIFY full_name VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE members MODIFY registration_fee DECIMAL(10,2) NOT NULL DEFAULT 220.00");
        DB::statement("ALTER TABLE members MODIFY registration_date DATE NOT NULL");
        DB::statement("ALTER TABLE members MODIFY membership_start_date DATE NOT NULL");
        DB::statement("ALTER TABLE members MODIFY next_renewal_date DATE NULL");

        if (! $this->hasForeignKey('members', 'members_membership_type_id_foreign')) {
            Schema::table('members', function (Blueprint $table) {
                $table->foreign('membership_type_id')->references('id')->on('membership_types')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Legacy columns and data are intentionally retained; this reconciliation is not destructive.
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};