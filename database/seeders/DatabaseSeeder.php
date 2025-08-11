<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Plan::create([
            'name' => 'Free Plan',
            'price' => 0,
            'currency' => 'NGN',
            'features' => [
                'Monthly downloads up to 10 reports',
                'Only 5 document processing per month',
                'Only 1 document storage',
                'Only 3 document previews per month',
                'Only 1 real-time data analysis',
            ],
            'duration_days' => 0
        ]);

        Plan::create([
            'name' => 'Basic Plan',
            'price' => 2000,
            'currency' => 'NGN',
            'features' => [
                'Up to 30 document uploads per month',
                'AI-driven document search results',
                'Basic document formatting',
                'Automated reference generation',
                'Advanced grammar check',
                'PDF document download',
                'Real-time collaboration on projects',
                '10 GB document storage',
                'Unlimited real-time data analysis',
                'Basic plagiarism detection',
            ],
            'duration_days' => 30
        ]);

        Plan::create([
            'name' => 'Premium Plan',
            'price' => 5000,
            'currency' => 'NGN',
            'features' => [
                'Unlimited document uploads per month',
                'AI-driven document search results',
                'Advanced document formatting tools',
                'Automated reference generation',
                'Advanced grammar & style check',
                'PDF & Word document downloads',
                'Real-time collaboration with version control',
                '50 GB document storage',
                'Unlimited real-time data analysis',
                'Advanced plagiarism detection',
                'Custom citation styles',
                'Priority customer support via phone & email',
                'Access to exclusive templates & tools',
            ],
            'duration_days' => 30
        ]);

    }
}
