<?php

namespace App\Http\Controllers;

use App\Models\ClassificationRule;
use App\Models\Country;
use App\Models\CustomsCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassificationRuleController extends Controller
{
    /**
     * Display the classification rules settings page
     */
    public function index()
    {
        $user = Auth::user();
        $organizationId = $user->organization_id;

        $rules = ClassificationRule::forOrganization($organizationId)
            ->byPriority()
            ->with(['country'])
            ->get();

        $countries = Country::orderBy('name')->get();

        return view('settings.classification-rules', compact('rules', 'countries'));
    }

    /**
     * Store a new classification rule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|in:keyword,category,override,instruction',
            'condition' => 'required|string',
            'target_code' => 'nullable|string|max:20',
            'instruction' => 'nullable|string',
            'country_id' => 'nullable|exists:countries,id',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        $user = Auth::user();

        $rule = ClassificationRule::create([
            'organization_id' => $user->organization_id,
            'country_id' => $validated['country_id'] ?? null,
            'name' => $validated['name'],
            'rule_type' => $validated['rule_type'],
            'condition' => $validated['condition'],
            'target_code' => $validated['target_code'] ?? null,
            'instruction' => $validated['instruction'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'is_active' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Classification rule created successfully',
                'rule' => $rule->load('country'),
            ]);
        }

        return redirect()->route('settings.classification-rules')
            ->with('success', 'Classification rule created successfully');
    }

    /**
     * Update a classification rule
     */
    public function update(Request $request, ClassificationRule $rule)
    {
        // Check ownership
        $user = Auth::user();
        if ($rule->organization_id && $rule->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|in:keyword,category,override,instruction',
            'condition' => 'required|string',
            'target_code' => 'nullable|string|max:20',
            'instruction' => 'nullable|string',
            'country_id' => 'nullable|exists:countries,id',
            'priority' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $rule->update([
            'country_id' => $validated['country_id'] ?? null,
            'name' => $validated['name'],
            'rule_type' => $validated['rule_type'],
            'condition' => $validated['condition'],
            'target_code' => $validated['target_code'] ?? null,
            'instruction' => $validated['instruction'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Classification rule updated successfully',
                'rule' => $rule->load('country'),
            ]);
        }

        return redirect()->route('settings.classification-rules')
            ->with('success', 'Classification rule updated successfully');
    }

    /**
     * Toggle rule active status
     */
    public function toggle(ClassificationRule $rule)
    {
        // Check ownership
        $user = Auth::user();
        if ($rule->organization_id && $rule->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized');
        }

        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json([
            'success' => true,
            'message' => $rule->is_active ? 'Rule activated' : 'Rule deactivated',
            'is_active' => $rule->is_active,
        ]);
    }

    /**
     * Delete a classification rule
     */
    public function destroy(ClassificationRule $rule)
    {
        // Check ownership
        $user = Auth::user();
        if ($rule->organization_id && $rule->organization_id !== $user->organization_id) {
            abort(403, 'Unauthorized');
        }

        $rule->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Classification rule deleted successfully',
            ]);
        }

        return redirect()->route('settings.classification-rules')
            ->with('success', 'Classification rule deleted successfully');
    }

    /**
     * Search for customs codes (for autocomplete)
     */
    public function searchCodes(Request $request)
    {
        $query = $request->get('q', '');
        $countryId = $request->get('country_id');

        $codes = CustomsCode::query()
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'code', 'description', 'duty_rate']);

        return response()->json($codes);
    }

    /**
     * Test a rule against sample text
     */
    public function testRule(Request $request)
    {
        $validated = $request->validate([
            'rule_type' => 'required|in:keyword,category,override,instruction',
            'condition' => 'required|string',
            'test_text' => 'required|string',
        ]);

        $rule = new ClassificationRule([
            'rule_type' => $validated['rule_type'],
            'condition' => $validated['condition'],
        ]);

        $matches = $rule->matches($validated['test_text']);

        return response()->json([
            'matches' => $matches,
            'message' => $matches 
                ? 'Rule would match this item description' 
                : 'Rule would NOT match this item description',
        ]);
    }
}
