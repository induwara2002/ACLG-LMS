<?php

namespace Database\Seeders;

use App\Models\MembershipType;
use Illuminate\Database\Seeder;

class MembershipTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Children', 'max_books' => 2, 'description' => 'Membership for children.'],
            ['name' => 'Elders', 'max_books' => 3, 'description' => 'Membership for elders.'],
        ];

        foreach ($types as $type) {
            MembershipType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
