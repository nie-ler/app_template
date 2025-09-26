<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Nilit\LaraBoilerCore\Models\Plan;
use Nilit\LaraBoilerCore\Models\Feature;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            \Nilit\LaraBoilerCore\Database\Seeders\BoilerplateSeeder::class,
        ]);

        $plan_free = Plan::Create([
            'name' => 'Free Plan',
            'description' => 'Der kostenfreie Plan zum Ausprobieren.',
            'slug' => 'test',
            'stripe_product_id' => 'prod_SpOt0svj5nvvHd',
            'stripe_price_id' => 'price_1Rtk5nRv9XcMoP6ytpwx9O9K',
        ]);
        
        $basic_pro = Plan::Create([
            'name' => 'Basic Plan',
            'description' => 'Der vernünftige Basic-Plan!',
            'slug' => 'basic_year',
            'stripe_product_id' => 'prod_SpNzGnrS4PE0So',
            'stripe_price_id' => 'price_1RtjDTRv9XcMoP6y90gYVLYo',
        ]);
        
        $plan_pro = Plan::Create([
            'name' => 'Pro Plan',
            'description' => 'Der teure Pro-Plan!',
            'slug' => 'pro_year',
            'stripe_product_id' => 'prod_SpO0JRVFtAPxlW',
            'stripe_price_id' => 'price_1RtjEXRv9XcMoP6yBoxZ6BnP',
        ]);


        $feature_1 = Feature::Create([
            'name' => 'Test Feature 1',
            'code' => 'test_feature',
            'description' => 'desciption to Test Feature 1',
        ]);

        $feature_2 = Feature::Create([
            'name' => 'Test Feature 2',
            'code' => 'test_feature_2',
            'description' => 'desciption to Test Feature 2',
        ]);

        $feature_3 = Feature::Create([
            'name' => 'Test Feature 3',
            'code' => 'test_feature_3',
            'description' => 'desciption to Test Feature 3',
        ]);

        $feature_4 = Feature::Create([
            'name' => 'Test Feature 4',
            'code' => 'test_feature_4',
            'description' => 'desciption to Test Feature 4',
        ]);

        $feature_5 = Feature::Create([
            'name' => 'Test Feature 5',
            'code' => 'test_feature_5',
            'description' => 'desciption to Test Feature 5',
        ]);

        $basic_pro->features()->attach($feature_1);
        $basic_pro->features()->attach($feature_2);
        $basic_pro->features()->attach($feature_3);
        $basic_pro->features()->attach($feature_4);

        $plan_pro->features()->attach($feature_1);
        $plan_pro->features()->attach($feature_2);
        $plan_pro->features()->attach($feature_3);
        $plan_pro->features()->attach($feature_4);
        $plan_pro->features()->attach($feature_5);

        $plan_free->features()->attach($feature_1);
        $plan_free->features()->attach($feature_2);
      
    }
}
