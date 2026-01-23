<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExemptionCategory;
use App\Models\ExemptionCondition;
use App\Models\Country;
use Illuminate\Http\Request;

class ExemptionController extends Controller
{
    /**
     * Display exemption categories list
     */
    public function index(Request $request)
    {
        $countryId = $request->get('country_id');

        $query = ExemptionCategory::with(['country', 'conditions'])
            ->withCount('conditions')
            ->orderBy('name');

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $exemptions = $query->paginate(25);
        $countries = Country::where('is_active', true)->orderBy('name')->get();

        return view('admin.exemptions.index', compact('exemptions', 'countries', 'countryId'));
    }

    /**
     * Show form for creating an exemption
     */
    public function create()
    {
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        $conditionTypes = ExemptionCondition::getTypes();

        return view('admin.exemptions.create', compact('countries', 'conditionTypes'));
    }

    /**
     * Store a new exemption
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'legal_reference' => 'nullable|string|max:100',
            'applies_to_patterns' => 'nullable|string',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'conditions' => 'nullable|array',
            'conditions.*.type' => 'required|string',
            'conditions.*.description' => 'required|string',
            'conditions.*.requirement' => 'nullable|string',
            'conditions.*.mandatory' => 'boolean',
        ]);

        // Convert patterns string to array
        $patterns = null;
        if (!empty($validated['applies_to_patterns'])) {
            $patterns = array_map('trim', explode(',', $validated['applies_to_patterns']));
        }

        $exemption = ExemptionCategory::create([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'legal_reference' => $validated['legal_reference'] ?? null,
            'applies_to_patterns' => $patterns,
            'is_active' => $validated['is_active'] ?? true,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
        ]);

        // Create conditions
        foreach ($validated['conditions'] ?? [] as $condData) {
            ExemptionCondition::create([
                'exemption_category_id' => $exemption->id,
                'condition_type' => $condData['type'],
                'description' => $condData['description'],
                'requirement_text' => $condData['requirement'] ?? null,
                'is_mandatory' => $condData['mandatory'] ?? true,
            ]);
        }

        return redirect()->route('admin.exemptions.index')
            ->with('success', 'Exemption category created successfully.');
    }

    /**
     * Show exemption details
     */
    public function show(ExemptionCategory $exemption)
    {
        $exemption->load(['country', 'conditions']);
        
        return view('admin.exemptions.show', compact('exemption'));
    }

    /**
     * Show form for editing an exemption
     */
    public function edit(ExemptionCategory $exemption)
    {
        $exemption->load('conditions');
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        $conditionTypes = ExemptionCondition::getTypes();

        return view('admin.exemptions.edit', compact('exemption', 'countries', 'conditionTypes'));
    }

    /**
     * Update an exemption
     */
    public function update(Request $request, ExemptionCategory $exemption)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'legal_reference' => 'nullable|string|max:100',
            'applies_to_patterns' => 'nullable|string',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
        ]);

        // Convert patterns string to array
        $patterns = null;
        if (!empty($validated['applies_to_patterns'])) {
            $patterns = array_map('trim', explode(',', $validated['applies_to_patterns']));
        }

        $exemption->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'legal_reference' => $validated['legal_reference'] ?? null,
            'applies_to_patterns' => $patterns,
            'is_active' => $validated['is_active'] ?? false,
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
        ]);

        return redirect()->route('admin.exemptions.index')
            ->with('success', 'Exemption category updated successfully.');
    }

    /**
     * Delete an exemption
     */
    public function destroy(ExemptionCategory $exemption)
    {
        $exemption->delete();

        return redirect()->route('admin.exemptions.index')
            ->with('success', 'Exemption category deleted successfully.');
    }
}
