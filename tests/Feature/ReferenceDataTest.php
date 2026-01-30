<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use App\Models\CountryReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataTest extends TestCase
{
    // We don't use RefreshDatabase because we want to test against the actual seeded data
    // use RefreshDatabase;

    public function test_admin_can_view_country_reference_data()
    {
        // 1. Authenticate as admin
        $admin = User::where('email', 'admin@bvicustoms.com')->first();
        if (!$admin) {
            // Create a dummy admin if not exists (though we expect it to exist in dev)
            $admin = User::factory()->create(['email' => 'admin@test.com', 'is_admin' => true]);
        }

        // 2. Get the country (ID 1)
        $country = Country::find(1);
        $this->assertNotNull($country, 'Country ID 1 not found');

        // 3. Verify reference data exists in DB
        $count = CountryReferenceData::where('country_id', 1)->count();
        $this->assertGreaterThan(0, $count, "No reference data found for Country ID 1. Found: $count");
        echo "\nFound $count reference data records in DB.\n";

        // 4. Visit the page
        $response = $this->actingAs($admin)->get("/admin/countries/{$country->id}");

        // 5. Assert successful response
        $response->assertStatus(200);

        // 6. Assert view has the data
        $response->assertViewHas('referenceData');
        $response->assertViewHas('stats');
        
        // 7. Check content in the HTML
        $content = $response->getContent();
        
        // Check for "Reference Data" tab label with count
        // The view generates: Reference Data <span class="...">944</span>
        // We look for the text "Reference Data" and the count
        $this->assertStringContainsString('Reference Data', $content);
        
        // This is the specific part we want to see populated
        // <h2 class="mb-0">944</h2>
        // Since number_format might add commas, let's look loosely
        $formattedCount = number_format($count);
        $this->assertStringContainsString($formattedCount, $content, "The reference data count ($formattedCount) was not found in the HTML response.");

        // Check for specific known data points
        // Carriers
        $this->assertStringContainsString('Carrier', $content);
        // "AA" code for American Airlines
        $this->assertStringContainsString('AA', $content);
        $this->assertStringContainsString('AMERICAN AIRLINES', $content);

        echo "Test passed! The page contains the reference data count and sample records.\n";
    }
}
