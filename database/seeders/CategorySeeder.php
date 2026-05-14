<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            'Housing',
            'Transportation',
            'Food & Dining',
            'Healthcare',
            'Insurance',
            'Utilities',
            'Education',
            'Entertainment',
            'Clothing',
            'Personal Care',
            'Savings',
            'Investments',
            'Debt Payments',
            'Taxes',
            'Travel',
            'Gifts & Donations',
            'Subscriptions',
            'Income',
            'Business Expenses',
            'Emergency Fund',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
