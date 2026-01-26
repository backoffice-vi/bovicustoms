<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomsCode;
use App\Models\TariffChapter;
use App\Models\TariffChapterNote;
use App\Models\TariffSection;
use App\Models\TariffSectionNote;
use App\Models\ClassificationExclusion;
use App\Models\ExemptionCategory;
use App\Models\ExemptionCondition;
use App\Models\ProhibitedGood;
use App\Models\RestrictedGood;
use App\Models\AdditionalLevy;
use App\Models\WarehousingRestriction;
use Illuminate\Http\Request;

class TariffDatabaseController extends Controller
{
    /**
     * Display the tariff database overview
     */
    public function index(Request $request)
    {
        $countryId = $request->get('country_id');
        $countries = Country::orderBy('name')->get();
        
        // Get overall statistics
        $stats = $this->getStats($countryId);
        
        // Get sample data for preview
        $recentCodes = CustomsCode::with('tariffChapter')
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return view('admin.tariff-database.index', compact(
            'countries',
            'countryId',
            'stats',
            'recentCodes'
        ));
    }

    /**
     * View all customs codes
     */
    public function codes(Request $request)
    {
        $countryId = $request->get('country_id');
        $search = $request->get('search');
        $chapter = $request->get('chapter');
        $perPage = $request->get('per_page', 100);
        
        $countries = Country::orderBy('name')->get();
        
        $query = CustomsCode::with(['tariffChapter', 'country'])
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->when($search, function($q) use ($search) {
                $q->where(function($q2) use ($search) {
                    $q2->where('code', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($chapter, function($q) use ($chapter) {
                $q->whereHas('tariffChapter', fn($q2) => $q2->where('chapter_number', $chapter));
            })
            ->orderBy('code');
        
        $codes = $query->paginate($perPage)->withQueryString();
        
        // Get chapter list for filter
        $chapters = TariffChapter::when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('chapter_number')
            ->get();
        
        $stats = $this->getStats($countryId);
        
        return view('admin.tariff-database.codes', compact(
            'codes',
            'countries',
            'chapters',
            'countryId',
            'search',
            'chapter',
            'stats'
        ));
    }

    /**
     * View all chapter notes
     */
    public function notes(Request $request)
    {
        $countryId = $request->get('country_id');
        $chapter = $request->get('chapter');
        $noteType = $request->get('note_type');
        $search = $request->get('search');
        
        $countries = Country::orderBy('name')->get();
        
        // Chapter notes
        $chapterNotesQuery = TariffChapterNote::with(['chapter', 'chapter.country'])
            ->when($countryId, function($q) use ($countryId) {
                $q->whereHas('chapter', fn($q2) => $q2->where('country_id', $countryId));
            })
            ->when($chapter, function($q) use ($chapter) {
                $q->whereHas('chapter', fn($q2) => $q2->where('chapter_number', $chapter));
            })
            ->when($noteType, fn($q) => $q->where('note_type', $noteType))
            ->when($search, fn($q) => $q->where('note_text', 'like', "%{$search}%"))
            ->orderBy('id');
        
        $chapterNotes = $chapterNotesQuery->paginate(50)->withQueryString();
        
        // Section notes
        $sectionNotesQuery = TariffSectionNote::with(['section', 'section.country'])
            ->when($countryId, function($q) use ($countryId) {
                $q->whereHas('section', fn($q2) => $q2->where('country_id', $countryId));
            })
            ->when($noteType, fn($q) => $q->where('note_type', $noteType))
            ->when($search, fn($q) => $q->where('note_text', 'like', "%{$search}%"));
        
        $sectionNotes = $sectionNotesQuery->get();
        
        // Get chapters for filter
        $chapters = TariffChapter::when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('chapter_number')
            ->get();
        
        $stats = $this->getStats($countryId);
        
        return view('admin.tariff-database.notes', compact(
            'chapterNotes',
            'sectionNotes',
            'countries',
            'chapters',
            'countryId',
            'chapter',
            'noteType',
            'search',
            'stats'
        ));
    }

    /**
     * View all chapters and sections
     */
    public function structure(Request $request)
    {
        $countryId = $request->get('country_id');
        
        $countries = Country::orderBy('name')->get();
        
        $chapters = TariffChapter::with(['section', 'notes'])
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('chapter_number')
            ->get();
        
        $sections = TariffSection::with('notes')
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderBy('section_number')
            ->get();
        
        $stats = $this->getStats($countryId);
        
        return view('admin.tariff-database.structure', compact(
            'chapters',
            'sections',
            'countries',
            'countryId',
            'stats'
        ));
    }

    /**
     * View exclusion rules
     */
    public function exclusions(Request $request)
    {
        $countryId = $request->get('country_id');
        $search = $request->get('search');
        
        $countries = Country::orderBy('name')->get();
        
        $exclusions = ClassificationExclusion::with(['sourceChapter', 'targetChapter', 'country'])
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->when($search, function($q) use ($search) {
                $q->where('exclusion_pattern', 'like', "%{$search}%")
                  ->orWhere('rule_text', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
        
        $stats = $this->getStats($countryId);
        
        return view('admin.tariff-database.exclusions', compact(
            'exclusions',
            'countries',
            'countryId',
            'search',
            'stats'
        ));
    }

    /**
     * Get statistics for the database
     */
    protected function getStats(?string $countryId): array
    {
        return [
            'customs_codes' => CustomsCode::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'codes_with_duty' => CustomsCode::when($countryId, fn($q) => $q->where('country_id', $countryId))
                ->where(function($q) {
                    // Ad valorem or compound duty with rate > 0
                    $q->where('duty_rate', '>', 0)
                      // Or specific duty with amount > 0
                      ->orWhere(function($q2) {
                          $q2->where('duty_type', 'specific')
                             ->where('specific_duty_amount', '>', 0);
                      });
                })->count(),
            'codes_duty_free' => CustomsCode::when($countryId, fn($q) => $q->where('country_id', $countryId))
                ->where(function($q) {
                    $q->where(function($q2) {
                        // Ad valorem with 0 or null rate
                        $q2->whereIn('duty_type', ['ad_valorem', ''])
                           ->where(function($q3) {
                               $q3->where('duty_rate', 0)->orWhereNull('duty_rate');
                           });
                    })->orWhere(function($q2) {
                        // Specific duty with 0 or null amount
                        $q2->where('duty_type', 'specific')
                           ->where(function($q3) {
                               $q3->where('specific_duty_amount', 0)->orWhereNull('specific_duty_amount');
                           });
                    });
                })->count(),
            'chapters' => TariffChapter::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'sections' => TariffSection::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'chapter_notes' => TariffChapterNote::when($countryId, function($q) use ($countryId) {
                $q->whereHas('chapter', fn($q2) => $q2->where('country_id', $countryId));
            })->count(),
            'section_notes' => TariffSectionNote::when($countryId, function($q) use ($countryId) {
                $q->whereHas('section', fn($q2) => $q2->where('country_id', $countryId));
            })->count(),
            'exclusion_rules' => ClassificationExclusion::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'exemptions' => ExemptionCategory::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'exemption_conditions' => ExemptionCondition::when($countryId, function($q) use ($countryId) {
                $q->whereHas('exemptionCategory', fn($q2) => $q2->where('country_id', $countryId));
            })->count(),
            'prohibited' => ProhibitedGood::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'restricted' => RestrictedGood::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'levies' => AdditionalLevy::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
            'warehousing_restrictions' => WarehousingRestriction::when($countryId, fn($q) => $q->where('country_id', $countryId))->count(),
        ];
    }
}
