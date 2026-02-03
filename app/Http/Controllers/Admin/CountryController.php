<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\TariffChapter;
use App\Models\TariffSection;
use App\Models\CustomsCode;
use App\Models\ExemptionCategory;
use App\Models\ProhibitedGood;
use App\Models\RestrictedGood;
use App\Models\AdditionalLevy;
use App\Models\CountryReferenceData;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Display a listing of countries
     */
    public function index()
    {
        $countries = Country::orderBy('name')->paginate(20);
        return view('admin.countries.index', compact('countries'));
    }

    /**
     * Display the specified country with all tariff data
     */
    public function show(Country $country)
    {
        // Load tariff chapters with their notes
        $chapters = TariffChapter::where('country_id', $country->id)
            ->with(['notes', 'section'])
            ->orderBy('chapter_number')
            ->get();

        // Load tariff sections with their notes
        $sections = TariffSection::where('country_id', $country->id)
            ->with('notes')
            ->orderBy('section_number')
            ->get();

        // Get reference data count
        $referenceCount = $country->referenceData()->count();

        // Get statistics
        $stats = [
            'chapters_count' => $chapters->count(),
            'sections_count' => $sections->count(),
            'chapter_notes_count' => $chapters->sum(fn($c) => $c->notes->count()),
            'section_notes_count' => $sections->sum(fn($s) => $s->notes->count()),
            'codes_count' => CustomsCode::where('country_id', $country->id)->count(),
            'exemptions_count' => ExemptionCategory::where('country_id', $country->id)->count(),
            'prohibited_count' => ProhibitedGood::where('country_id', $country->id)->count(),
            'restricted_count' => RestrictedGood::where('country_id', $country->id)->count(),
            'levies_count' => AdditionalLevy::where('country_id', $country->id)->count(),
            'reference_count' => $referenceCount,
        ];

        // Load exemptions
        $exemptions = ExemptionCategory::where('country_id', $country->id)
            ->with('conditions')
            ->get();

        // Load prohibited/restricted goods
        $prohibitedGoods = ProhibitedGood::where('country_id', $country->id)->get();
        $restrictedGoods = RestrictedGood::where('country_id', $country->id)->get();

        // Load additional levies
        $levies = AdditionalLevy::where('country_id', $country->id)->get();

        // Load reference data grouped by type
        $referenceData = $country->referenceData()
            ->orderBy('reference_type')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy('reference_type');

        return view('admin.countries.show', compact(
            'country',
            'chapters',
            'sections',
            'stats',
            'exemptions',
            'prohibitedGoods',
            'restrictedGoods',
            'levies',
            'referenceData'
        ));
    }

    /**
     * Show the form for creating a new country
     */
    public function create()
    {
        return view('admin.countries.create');
    }

    /**
     * Store a newly created country
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:3|unique:countries',
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:3',
            'flag_emoji' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        Country::create($validated);

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country created successfully');
    }

    /**
     * Show the form for editing a country
     */
    public function edit(Country $country)
    {
        return view('admin.countries.edit', compact('country'));
    }

    /**
     * Update the specified country
     */
    public function update(Request $request, Country $country)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:3|unique:countries,code,' . $country->id,
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|max:3',
            'flag_emoji' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'default_insurance_method' => 'nullable|in:manual,percentage,document',
            'default_insurance_percentage' => 'nullable|numeric|min:0|max:100',
            // FTP settings
            'ftp_enabled' => 'boolean',
            'ftp_host' => 'nullable|string|max:255',
            'ftp_port' => 'nullable|integer|min:1|max:65535',
            'ftp_passive_mode' => 'boolean',
            'ftp_base_path' => 'nullable|string|max:255',
            'ftp_file_format' => 'nullable|string|max:50',
            'ftp_notification_email' => 'nullable|email|max:255',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['ftp_enabled'] = $request->has('ftp_enabled');
        $validated['ftp_passive_mode'] = $request->has('ftp_passive_mode');

        $country->update($validated);

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country updated successfully');
    }

    /**
     * Remove the specified country
     */
    public function destroy(Country $country)
    {
        $country->delete();

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country deleted successfully');
    }
}
