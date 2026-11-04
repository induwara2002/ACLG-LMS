<?php

namespace Database\Seeders;

use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PradeshiyaSabhaAdminSeeder extends Seeder
{
    private const EMAIL_SLUGS = [
        'BAL' => 'balapitiya',
        'AMB' => 'ambalangoda',
        'WEL' => 'welivitiya',
        'KAR' => 'karandeniya',
        'BEN' => 'bentota',
        'ELP' => 'elpitiya',
        'NEL' => 'neluwa',
        'THA' => 'thawalama',
        'NAG' => 'nagoda',
        'NIY' => 'niyagama',
        'BAD' => 'baddegama',
        'YAK' => 'yakkalamulla',
        'RAJ' => 'rajgama',
        'AKM' => 'akmeemana',
        'BOP' => 'bopepoddala',
        'IMA' => 'imaduwa',
        'HAB' => 'habaraduwa',
    ];

    public function run(): void
    {
        $passwords = config('library.pradeshiya_sabha_admin_passwords', []);
        $sabhas = PradeshiyaSabha::query()->whereIn('code', array_keys(self::EMAIL_SLUGS))->get()->keyBy('code');
        if ($sabhas->count() !== count(self::EMAIL_SLUGS)) {
            throw new RuntimeException('Run PradeshiyaSabhaSeeder before provisioning Sabha administrators.');
        }

        $this->validateProvisioningPlan($sabhas, $passwords);

        foreach (self::EMAIL_SLUGS as $code => $slug) {
            $sabha = $sabhas->get($code);
            DB::transaction(function () use ($sabha, $code, $slug, $passwords) {
                $assignment = PradeshiyaSabhaAdminAssignment::query()
                    ->where('pradeshiya_sabha_id', $sabha->id)
                    ->lockForUpdate()
                    ->first();

                if ($assignment) {
                    $user = User::query()->findOrFail($assignment->user_id);
                    if ($user->role !== User::ROLE_PRADESHIYA_SABHA_ADMIN
                        || (int) $user->pradeshiya_sabha_id !== (int) $sabha->id) {
                        throw new RuntimeException('Existing Sabha-admin assignment is inconsistent for code '.$code.'.');
                    }

                    if (! $assignment->is_active || ! $user->is_active) {
                        throw new RuntimeException('The existing Sabha-admin assignment is inactive for code '.$code.'; review it manually.');
                    }

                    $this->assertSingleActiveAdmin($sabha, $user);

                    return;
                }

                $existingAdmins = User::query()
                    ->where('role', User::ROLE_PRADESHIYA_SABHA_ADMIN)
                    ->where('pradeshiya_sabha_id', $sabha->id)
                    ->lockForUpdate()
                    ->get();

                if ($existingAdmins->count() > 1) {
                    throw new RuntimeException('Multiple Sabha-admin accounts already exist for code '.$code.'; review them manually.');
                }

                $user = $existingAdmins->first();
                if ($user && ! $user->is_active) {
                    throw new RuntimeException('The existing Sabha-admin account is inactive for code '.$code.'; review it manually.');
                }

                if (! $user) {
                    $email = 'admin.'.$slug.'@library.local';
                    $user = User::query()->where('email', $email)->first();
                    if ($user && ($user->role !== User::ROLE_PRADESHIYA_SABHA_ADMIN
                        || (int) $user->pradeshiya_sabha_id !== (int) $sabha->id)) {
                        throw new RuntimeException('The configured local Sabha-admin email is already assigned elsewhere.');
                    }

                    if ($user && ! $user->is_active) {
                        throw new RuntimeException('The existing Sabha-admin account is inactive for code '.$code.'; review it manually.');
                    }
                }

                if (! $user) {
                    $password = $passwords[$code] ?? null;
                    if (! is_string($password) || strlen($password) < 12) {
                        throw new RuntimeException('Set a unique Sabha-admin password of at least 12 characters for code '.$code.'.');
                    }

                    $user = User::create([
                        'name' => str_replace(' Pradeshiya Sabha', '', $sabha->name).' Library Administrator',
                        'email' => 'admin.'.$slug.'@library.local',
                        'password' => Hash::make($password),
                        'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
                        'is_active' => true,
                        'pradeshiya_sabha_id' => $sabha->id,
                    ]);
                }

                PradeshiyaSabhaAdminAssignment::create([
                    'pradeshiya_sabha_id' => $sabha->id,
                    'user_id' => $user->id,
                    'is_active' => true,
                ]);

                $this->assertSingleActiveAdmin($sabha, $user);
            });
        }

        $activeAssignments = PradeshiyaSabhaAdminAssignment::query()
            ->whereIn('pradeshiya_sabha_id', $sabhas->pluck('id'))
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_PRADESHIYA_SABHA_ADMIN)->where('is_active', true))
            ->count();

        if ($activeAssignments !== count(self::EMAIL_SLUGS)) {
            throw new RuntimeException('Sabha administrator verification failed.');
        }

        $this->command?->info('Verified one active Sabha administrator for each official Pradeshiya Sabha.');
    }

    private function assertSingleActiveAdmin(PradeshiyaSabha $sabha, User $assignedUser): void
    {
        $activeAdmins = User::query()
            ->where('role', User::ROLE_PRADESHIYA_SABHA_ADMIN)
            ->where('pradeshiya_sabha_id', $sabha->id)
            ->where('is_active', true)
            ->get();

        if ($activeAdmins->count() !== 1 || (int) $activeAdmins->first()->id !== (int) $assignedUser->id) {
            throw new RuntimeException('Expected exactly one active Sabha administrator for code '.$sabha->code.'.');
        }
    }

    private function validateProvisioningPlan($sabhas, array $passwords): void
    {
        $missingCodes = [];

        foreach (self::EMAIL_SLUGS as $code => $slug) {
            $sabha = $sabhas->get($code);
            $assignment = PradeshiyaSabhaAdminAssignment::query()
                ->where('pradeshiya_sabha_id', $sabha->id)
                ->first();

            if ($assignment) {
                $user = User::query()->findOrFail($assignment->user_id);
                if ($user->role !== User::ROLE_PRADESHIYA_SABHA_ADMIN
                    || (int) $user->pradeshiya_sabha_id !== (int) $sabha->id) {
                    throw new RuntimeException('Existing Sabha-admin assignment is inconsistent for code '.$code.'.');
                }
                if (! $assignment->is_active || ! $user->is_active) {
                    throw new RuntimeException('The existing Sabha-admin assignment is inactive for code '.$code.'; review it manually.');
                }
                $this->assertSingleActiveAdmin($sabha, $user);

                continue;
            }

            $existingAdmins = User::query()
                ->where('role', User::ROLE_PRADESHIYA_SABHA_ADMIN)
                ->where('pradeshiya_sabha_id', $sabha->id)
                ->get();

            if ($existingAdmins->count() > 1) {
                throw new RuntimeException('Multiple Sabha-admin accounts already exist for code '.$code.'; review them manually.');
            }
            if ($existingAdmins->isNotEmpty()) {
                $admin = $existingAdmins->first();
                if (! $admin->is_active) {
                    throw new RuntimeException('The existing Sabha-admin account is inactive for code '.$code.'; review it manually.');
                }
                $this->assertSingleActiveAdmin($sabha, $admin);

                continue;
            }

            $email = 'admin.'.$slug.'@library.local';
            $user = User::query()->where('email', $email)->first();
            if ($user && ($user->role !== User::ROLE_PRADESHIYA_SABHA_ADMIN
                || (int) $user->pradeshiya_sabha_id !== (int) $sabha->id)) {
                throw new RuntimeException('The configured local Sabha-admin email is already assigned elsewhere.');
            }
            if ($user) {
                throw new RuntimeException('The configured local Sabha-admin email already exists without a designated assignment.');
            }

            $password = $passwords[$code] ?? null;
            if (! is_string($password) || strlen($password) < 12) {
                $missingCodes[] = $code;
            }
        }

        if ($missingCodes !== []) {
            throw new RuntimeException('Set local Sabha-admin passwords of at least 12 characters for codes: '.implode(', ', $missingCodes));
        }
    }
}