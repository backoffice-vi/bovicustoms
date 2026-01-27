<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\PublicClassificationLog;
use App\Services\ItemClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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

    /**
     * Public classification endpoint for landing page demo
     * Rate limited to prevent abuse
     */
    public function publicClassify(Request $request): JsonResponse
    {
        // Get client identifier (IP address)
        $clientId = $request->ip();
        $rateLimitKey = 'public-classify:' . $clientId;
        $dailyLimit = 5; // 5 classifications per day per IP
        
        // Check rate limit
        $attempts = Cache::get($rateLimitKey, 0);
        
        if ($attempts >= $dailyLimit) {
            return response()->json([
                'success' => false,
                'error' => 'Daily limit reached. Sign up for unlimited classifications.',
                'remaining' => 0,
            ], 429);
        }
        
        $validated = $request->validate([
            'item' => 'required|string|min:2|max:500',
        ]);

        try {
            // Use British Virgin Islands as default (first country or by code)
            $country = Country::where('code', 'VG')->first() ?? Country::active()->first();
            
            $result = $this->classifier->classify(
                $validated['item'],
                $country?->id,
                null // No organization for public access
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'remaining' => $dailyLimit - $attempts,
                ], 422);
            }

            // Increment rate limit counter (expires at end of day)
            $secondsUntilMidnight = now()->endOfDay()->diffInSeconds(now());
            Cache::put($rateLimitKey, $attempts + 1, $secondsUntilMidnight);

            // Log the successful classification
            PublicClassificationLog::create([
                'search_term' => $validated['item'],
                'result_code' => $result['code'],
                'result_description' => Str::limit($result['description'], 500),
                'duty_rate' => $result['duty_rate'],
                'confidence' => $result['confidence'],
                'vector_score' => $result['vector_score'] ?? null,
                'success' => true,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit($request->userAgent(), 500),
                'source' => 'landing_page',
            ]);

            // Return simplified result for public demo
            return response()->json([
                'success' => true,
                'item' => $result['item'],
                'match' => [
                    'code' => $result['code'],
                    'description' => $result['description'],
                    'duty_rate' => $result['duty_rate'],
                    'confidence' => $result['confidence'],
                    'explanation' => $result['explanation'],
                    'alternatives' => $result['alternatives'] ?? [],
                    'vector_score' => $result['vector_score'] ?? null,
                ],
                'remaining' => $dailyLimit - ($attempts + 1),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Public classification error', [
                'item' => $validated['item'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Log the failed classification
            PublicClassificationLog::create([
                'search_term' => $validated['item'],
                'success' => false,
                'error_message' => Str::limit($e->getMessage(), 500),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit($request->userAgent(), 500),
                'source' => 'landing_page',
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Classification failed. Please try again.',
                'remaining' => $dailyLimit - $attempts,
            ], 500);
        }
    }
}
