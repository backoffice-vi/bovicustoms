<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'billing_period' => 'monthly',
                'invoice_limit' => 10,
                'country_limit' => 1,
                'team_member_limit' => 0,
                'features' => [
                    'Basic support',
                    'Email notifications',
                    'Individual account only',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 49.00,
                'billing_period' => 'monthly',
                'invoice_limit' => null, // unlimited
                'country_limit' => 5,
                'team_member_limit' => 10,
                'features' => [
                    'Priority support',
                    'Custom code preferences',
                    'Advanced analytics',
                    'Team collaboration',
                    'Bulk import',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 199.00,
                'billing_period' => 'monthly',
                'invoice_limit' => null, // unlimited
                'country_limit' => null, // unlimited
                'team_member_limit' => null, // unlimited
                'features' => [
                    'Dedicated support',
                    'API access',
                    'Custom integrations',
                    'SLA guarantee',
                    'White-label option',
                    'Advanced security',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
