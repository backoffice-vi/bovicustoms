<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Services\ItemClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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

        try {
            $organizationId = Auth::user()?->organization_id;
            
            $result = $this->classifier->classify(
                $validated['item'],
                $validated['country_id'] ?? null,
                $organizationId
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 422);
            }

            // The view expects 'match' wrapper for the classification data
            return response()->json([
                'success' => true,
                'item' => $result['item'],
                'match' => [
                    'code' => $result['code'],
                    'description' => $result['description'],
                    'duty_rate' => $result['duty_rate'],
                    'unit_of_measurement' => $result['unit_of_measurement'],
                    'special_rate' => $result['special_rate'],
                    'confidence' => $result['confidence'],
                    'explanation' => $result['explanation'],
                    'alternatives' => $result['alternatives'] ?? [],
                    'customs_code_id' => $result['customs_code_id'],
                    'chapter' => $result['chapter'],
                    'restricted' => $result['restricted'],
                    'restricted_items' => $result['restricted_items'] ?? [],
                    'exemptions_available' => $result['exemptions_available'] ?? [],
                    'duty_calculation' => $result['duty_calculation'],
                    'vector_verification' => $result['vector_verification'] ?? null,
                    // Vector-only mode fields
                    'source' => $result['source'] ?? 'database',
                    'vector_score' => $result['vector_score'] ?? null,
                    'is_ambiguous' => $result['is_ambiguous'] ?? false,
                    'ambiguity_note' => $result['ambiguity_note'] ?? null,
                    'all_matches' => $result['all_matches'] ?? [],
                    // Rule-based classification
                    'rule_applied' => $result['rule_applied'] ?? null,
                ],
                'classification_path' => $result['classification_path'] ?? [],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Classification controller error', [
                'item' => $validated['item'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Classification failed: ' . $e->getMessage(),
            ], 500);
        }
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
