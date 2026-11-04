<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PradeshiyaSabha;

class DdcRangeSynchronizer
{
    public function syncAll(): void
    {
        PradeshiyaSabha::query()
            ->orderBy('id')
            ->get()
            ->each(fn (PradeshiyaSabha $sabha) => $this->syncFor($sabha));
    }

    public function syncFor(PradeshiyaSabha $sabha): void
    {
        app(PradeshiyaSabhaContext::class)->runFor($sabha, function () use ($sabha): void {
            foreach (Category::BOOK_REGISTRATION_CATEGORIES as $code => $range) {
                $category = $sabha->categories()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $range['name'],
                        'description' => 'DDC range '.$range['label'],
                    ]
                );

                $category->name = $range['name'];
                $category->description = 'DDC range '.$range['label'];

                if ($category->isDirty(['name', 'description'])) {
                    $category->save();
                }
            }
        });
    }
}
