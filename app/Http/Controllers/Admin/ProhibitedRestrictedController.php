<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProhibitedGood;
use App\Models\RestrictedGood;
use App\Models\Country;
use Illuminate\Http\Request;

class ProhibitedRestrictedController extends Controller
{
    /**
     * Display prohibited and restricted goods
     */
    public function index(Request $request)
    {
        $countryId = $request->get('country_id');
        $type = $request->get('type', 'all');

        $prohibited = collect();
        $restricted = collect();

        $query = function ($model) use ($countryId) {
            $q = $model::with('country')->orderBy('name');
            if ($countryId) {
                $q->where('country_id', $countryId);
            }
            return $q;
        };

        if ($type === 'all' || $type === 'prohibited') {
            $prohibited = $query(ProhibitedGood::class)->get();
        }

        if ($type === 'all' || $type === 'restricted') {
            $restricted = $query(RestrictedGood::class)->get();
        }

        $countries = Country::where('is_active', true)->orderBy('name')->get();

        return view('admin.prohibited-restricted.index', compact('prohibited', 'restricted', 'countries', 'countryId', 'type'));
    }

    /**
     * Show form for creating a prohibited good
     */
    public function createProhibited()
    {
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        return view('admin.prohibited-restricted.create-prohibited', compact('countries'));
    }

    /**
     * Store a new prohibited good
     */
    public function storeProhibited(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'legal_reference' => 'nullable|string|max:100',
            'detection_keywords' => 'nullable|string',
        ]);

        $keywords = null;
        if (!empty($validated['detection_keywords'])) {
            $keywords = array_map('trim', explode(',', $validated['detection_keywords']));
        }

        ProhibitedGood::create([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'legal_reference' => $validated['legal_reference'] ?? null,
            'detection_keywords' => $keywords,
        ]);

        return redirect()->route('admin.prohibited-restricted.index', ['type' => 'prohibited'])
            ->with('success', 'Prohibited good added successfully.');
    }

    /**
     * Show form for creating a restricted good
     */
    public function createRestricted()
    {
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        $restrictionTypes = RestrictedGood::getRestrictionTypes();
        return view('admin.prohibited-restricted.create-restricted', compact('countries', 'restrictionTypes'));
    }

    /**
     * Store a new restricted good
     */
    public function storeRestricted(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'restriction_type' => 'nullable|string',
            'permit_authority' => 'nullable|string|max:255',
            'requirements' => 'nullable|string',
            'detection_keywords' => 'nullable|string',
        ]);

        $keywords = null;
        if (!empty($validated['detection_keywords'])) {
            $keywords = array_map('trim', explode(',', $validated['detection_keywords']));
        }

        RestrictedGood::create([
            'country_id' => $validated['country_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'restriction_type' => $validated['restriction_type'] ?? 'permit',
            'permit_authority' => $validated['permit_authority'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'detection_keywords' => $keywords,
        ]);

        return redirect()->route('admin.prohibited-restricted.index', ['type' => 'restricted'])
            ->with('success', 'Restricted good added successfully.');
    }

    /**
     * Delete a prohibited good
     */
    public function destroyProhibited(ProhibitedGood $prohibited)
    {
        $prohibited->delete();

        return redirect()->route('admin.prohibited-restricted.index', ['type' => 'prohibited'])
            ->with('success', 'Prohibited good deleted successfully.');
    }

    /**
     * Delete a restricted good
     */
    public function destroyRestricted(RestrictedGood $restricted)
    {
        $restricted->delete();

        return redirect()->route('admin.prohibited-restricted.index', ['type' => 'restricted'])
            ->with('success', 'Restricted good deleted successfully.');
    }
}
