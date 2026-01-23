<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassificationExclusion;
use App\Models\TariffChapter;
use App\Models\Country;
use App\Services\ExclusionRuleParser;
use Illuminate\Http\Request;

class ExclusionRuleController extends Controller
{
    /**
     * Display exclusion rules list
     */
    public function index(Request $request)
    {
        $countryId = $request->get('country_id');
        $chapterId = $request->get('chapter_id');

        $query = ClassificationExclusion::with(['sourceChapter', 'targetChapter', 'country'])
            ->orderBy('source_chapter_id')
            ->orderBy('priority', 'desc');

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        if ($chapterId) {
            $query->where('source_chapter_id', $chapterId);
        }

        $exclusions = $query->paginate(50);
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        $chapters = TariffChapter::when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('chapter_number')
            ->get();

        return view('admin.exclusion-rules.index', compact('exclusions', 'countries', 'chapters', 'countryId', 'chapterId'));
    }

    /**
     * Show form for creating a new exclusion rule
     */
    public function create()
    {
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        $chapters = TariffChapter::orderBy('chapter_number')->get();

        return view('admin.exclusion-rules.create', compact('countries', 'chapters'));
    }

    /**
     * Store a new exclusion rule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'source_chapter_id' => 'required|exists:tariff_chapters,id',
            'exclusion_pattern' => 'required|string|max:255',
            'target_chapter_id' => 'nullable|exists:tariff_chapters,id',
            'target_heading' => 'nullable|string|max:20',
            'rule_text' => 'required|string|max:500',
            'source_note_reference' => 'nullable|string|max:100',
            'priority' => 'integer|min:0|max:100',
        ]);

        ClassificationExclusion::create($validated);

        return redirect()->route('admin.exclusion-rules.index')
            ->with('success', 'Exclusion rule created successfully.');
    }

    /**
     * Show exclusion rule details
     */
    public function show(ClassificationExclusion $exclusionRule)
    {
        $exclusionRule->load(['sourceChapter', 'targetChapter', 'country']);
        
        return view('admin.exclusion-rules.show', compact('exclusionRule'));
    }

    /**
     * Show form for editing an exclusion rule
     */
    public function edit(ClassificationExclusion $exclusionRule)
    {
        $countries = Country::where('is_active', true)->orderBy('name')->get();
        $chapters = TariffChapter::where('country_id', $exclusionRule->country_id)
            ->orderBy('chapter_number')
            ->get();

        return view('admin.exclusion-rules.edit', compact('exclusionRule', 'countries', 'chapters'));
    }

    /**
     * Update an exclusion rule
     */
    public function update(Request $request, ClassificationExclusion $exclusionRule)
    {
        $validated = $request->validate([
            'exclusion_pattern' => 'required|string|max:255',
            'target_chapter_id' => 'nullable|exists:tariff_chapters,id',
            'target_heading' => 'nullable|string|max:20',
            'rule_text' => 'required|string|max:500',
            'source_note_reference' => 'nullable|string|max:100',
            'priority' => 'integer|min:0|max:100',
        ]);

        $exclusionRule->update($validated);

        return redirect()->route('admin.exclusion-rules.index')
            ->with('success', 'Exclusion rule updated successfully.');
    }

    /**
     * Delete an exclusion rule
     */
    public function destroy(ClassificationExclusion $exclusionRule)
    {
        $exclusionRule->delete();

        return redirect()->route('admin.exclusion-rules.index')
            ->with('success', 'Exclusion rule deleted successfully.');
    }

    /**
     * Parse exclusion rules from notes
     */
    public function parseFromNotes(Request $request, ExclusionRuleParser $parser)
    {
        $countryId = $request->validate(['country_id' => 'required|exists:countries,id'])['country_id'];

        $results = $parser->parseAllForCountry($countryId);

        return redirect()->route('admin.exclusion-rules.index', ['country_id' => $countryId])
            ->with('success', "Parsed {$results['parsed']} notes, created {$results['created']} exclusion rules.");
    }
}
