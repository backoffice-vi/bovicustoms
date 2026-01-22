<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Services\ItemClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClassificationController extends Controller
{
    protected ItemClassifier $classifier;

    public function __construct(ItemClassifier $classifier)
    {
        $this->classifier = $classifier;
    }

    /**
     * Show the classification search page
     */
    public function index()
    {
        $countries = Country::active()->orderBy('name')->get();
        return view('classification.search', compact('countries'));
    }

    /**
     * Classify an item via AJAX
     */
    public function classify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item' => 'required|string|min:2|max:500',
            'country_id' => 'nullable|exists:countries,id',
        ]);

        $result = $this->classifier->classify(
            $validated['item'],
            $validated['country_id'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * Classify multiple items (bulk)
     */
    public function classifyBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:20',
            'items.*' => 'required|string|min:2|max:500',
            'country_id' => 'nullable|exists:countries,id',
        ]);

        $results = $this->classifier->classifyBulk(
            $validated['items'],
            $validated['country_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}
