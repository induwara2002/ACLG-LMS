<?php

namespace Database\Seeders;

use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PradeshiyaSabhaSeeder extends Seeder
{
    private const SABHAS = [
        'BAL' => 'Balapitiya Pradeshiya Sabha',
        'AMB' => 'Ambalangoda Pradeshiya Sabha',
        'WEL' => 'Welivitiya Divithura Pradeshiya Sabha',
        'KAR' => 'Karandeniya Pradeshiya Sabha',
        'BEN' => 'Bentota Pradeshiya Sabha',
        'ELP' => 'Elpitiya Pradeshiya Sabha',
        'NEL' => 'Neluwa Pradeshiya Sabha',
        'THA' => 'Thawalama Pradeshiya Sabha',
        'NAG' => 'Nagoda Pradeshiya Sabha',
        'NIY' => 'Niyagama Pradeshiya Sabha',
        'BAD' => 'Baddegama Pradeshiya Sabha',
        'YAK' => 'Yakkalamulla Pradeshiya Sabha',
        'RAJ' => 'Rajgama Pradeshiya Sabha',
        'AKM' => 'Akmeemana Pradeshiya Sabha',
        'BOP' => 'Bope Poddala Pradeshiya Sabha',
        'IMA' => 'Imaduwa Pradeshiya Sabha',
        'HAB' => 'Habaraduwa Pradeshiya Sabha',
        'GAL-MC' => 'Galle Municipal Council',
        'HIK-MC' => 'Hikkaduwa Municipal Council',
        'AMB-UC' => 'Ambalangoda Urban Council',
    ];

    private const SETTINGS = [
        'registration_fee' => 'Registration fee; configure before accepting registrations.',
        'monthly_membership_fee' => 'Monthly membership fee; configure before renewals.',
        'late_renewal_fee' => 'Late renewal fee; configure before renewals.',
        'late_renewal_penalty' => 'Late renewal penalty; configure before renewals.',
        'membership_duration_months' => 'Membership duration in months.',
        'individual_borrowing_limit' => 'Individual member borrowing limit.',
        'family_borrowing_limit' => 'Family member borrowing limit.',
        'issue_period_days' => 'Borrowing issue period in days.',
        'maximum_renewals' => 'Maximum borrowing renewals.',
        'overdue_fine_amount' => 'Configured overdue fine amount.',
        'currency' => 'Currency code for this library.',
        'timezone' => 'Timezone used by this library.',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::SABHAS as $code => $name) {
                $sabha = PradeshiyaSabha::query()->updateOrCreate(['code' => $code], ['name' => $name]);

                foreach (self::SETTINGS as $key => $description) {
                    PradeshiyaSabhaSetting::query()->firstOrCreate(
                        ['pradeshiya_sabha_id' => $sabha->id, 'key' => $key],
                        ['value' => null, 'description' => $description]
                    );
                }
            }
        });

        $seededCount = PradeshiyaSabha::query()->whereIn('code', array_keys(self::SABHAS))->count();
        if ($seededCount !== count(self::SABHAS)) {
            throw new \RuntimeException('The official Pradeshiya Sabha record count is incomplete.');
        }

        $additionalCount = PradeshiyaSabha::query()->whereNotIn('code', array_keys(self::SABHAS))->count();
        $this->command?->info('Verified '.count(self::SABHAS).' Galle District local-authority libraries; preserved '.$additionalCount.' additional existing authority records.');
    }
}