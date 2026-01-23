<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ItemClassifier;
use Illuminate\Http\Request;

class ClassificationTesterController extends Controller
{
    protected ItemClassifier $classifier;

    public function __construct(ItemClassifier $classifier)
    {
        $this->classifier = $classifier;
    }

    /**
     * Show the classification tester form
     */
    public function index()
    {
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        
        return view('admin.classification-tester.index', compact('countries'));
    }

    /**
     * Test classification
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'item_description' => 'required|string|max:500',
            'country_id' => 'required|exists:countries,id',
        ]);

        $result = $this->classifier->testClassification(
            $validated['item_description'],
            $validated['country_id']
        );

        $countries = Country::where('is_active', true)->orderBy('name')->get();

        return view('admin.classification-tester.index', [
            'countries' => $countries,
            'result' => $result,
            'item_description' => $validated['item_description'],
            'country_id' => $validated['country_id'],
        ]);
    }
}
