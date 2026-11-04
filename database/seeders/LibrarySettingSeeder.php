<?php

namespace Database\Seeders;

use App\Models\LibrarySetting;
use Illuminate\Database\Seeder;

class LibrarySettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'library_name', 'value' => 'City Central Library', 'description' => 'Name of the library.'],
            ['key' => 'library_address', 'value' => '123 Knowledge Street, Downtown', 'description' => 'Physical address of the library.'],
            ['key' => 'phone', 'value' => '+1234567890', 'description' => 'Primary contact phone number.'],
            ['key' => 'email', 'value' => 'library@example.com', 'description' => 'Library contact email.'],
            ['key' => 'loan_days', 'value' => '14', 'description' => 'Default number of days a book may be borrowed.'],
            ['key' => 'fine_per_day', 'value' => '5.00', 'description' => 'Late return fine charged per day.'],
            ['key' => 'max_renewals', 'value' => '2', 'description' => 'Maximum renewals allowed per borrowing.'],
            ['key' => 'registration_fee', 'value' => '220.00', 'description' => 'One-time member registration fee.'],
            ['key' => 'monthly_membership_fee', 'value' => '120.00', 'description' => 'Monthly membership fee.'],
            ['key' => 'late_renewal_charge', 'value' => '60.00', 'description' => 'Additional late renewal charge.'],
        ];

        foreach ($settings as $setting) {
            LibrarySetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
