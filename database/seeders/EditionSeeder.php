<?php

namespace Database\Seeders;

use App\Models\Edition;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class EditionSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach (config('kinderdesk.editions') as $key => $def) {
            $edition = Edition::updateOrCreate(['key' => $key], [
                'name' => $def['name'],
                'tagline' => $def['tagline'],
                'features' => $def['features'],
                'defaults' => $def['defaults'],
                'sort' => ++$sort,
                'is_active' => true,
            ]);

            foreach (config('kinderdesk.plan_months') as $months) {
                $name = match ($months) {
                    1 => '1 oylik',
                    3 => '3 oylik',
                    12 => '1 yillik',
                    default => $months.' oylik',
                };
                // Mavjud narxlarni qayta yozmaymiz (super admin o'zgartirgan bo'lishi mumkin)
                Plan::firstOrCreate(
                    ['edition_id' => $edition->id, 'months' => $months],
                    ['name' => $def['name'].' — '.$name, 'price' => config("kinderdesk.seed_prices.$key.$months"), 'is_active' => true]
                );
            }
        }
    }
}
