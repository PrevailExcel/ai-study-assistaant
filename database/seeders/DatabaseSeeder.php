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
            'slug' => 'free',
            'price' => 0,
            'currency' => 'NGN',
            'interval' => 'monthly',
            'duration_days' => 0,
            'features' => [
                'Monthly downloads up to 10 reports',
                'Only 5 document processing per month',
                'Only 1GB document storage',
                'Only 3 document previews per month',
                'Only 1 real-time data analysis',
            ],
            'limits' => [
                'document_upload' => 5,
                'document_storage' => 1,
                'document_preview' => 3,
                'real_time_analysis' => 1,
            ],
        ]);

        Plan::create([
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'interval' => 'monthly',
            'price' => 2000,
            'paystack_plan_code' => "PLN_ktyqniw0v96tix1",
            'currency' => 'NGN',
            'duration_days' => 30,
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
            'limits' => [
                'document_upload' => 30,
                'document_storage' => 10,
                'document_preview' => 20,
                'real_time_analysis' => -1, // Unlimited
            ]
        ]);

        Plan::create([
            'name' => 'Premium Plan',
            'price' => 5000,
            'paystack_plan_code' => "PLN_ja4mr4dwj48vudp",
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
            'slug' => 'premium',
            'limits' => [
                'document_upload' => -1, // Unlimited
                'document_storage' => 50,
                'document_preview' => -1, // Unlimited
                'real_time_analysis' => -1, // Unlimited
            ],
            'interval' => 'monthly',
            'duration_days' => 30
        ]);
    }
}
