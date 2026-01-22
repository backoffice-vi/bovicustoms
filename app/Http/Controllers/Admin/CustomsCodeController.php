<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomsCode;
use App\Models\Country;
use Illuminate\Http\Request;

class CustomsCodeController extends Controller
{
    /**
     * Display a listing of customs codes
     */
    public function index(Request $request)
    {
        $query = CustomsCode::with('country');
        
        if ($request->has('country_id') && $request->country_id) {
            $query->where('country_id', $request->country_id);
        }
        
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $codes = $query->orderBy('code')->paginate(50);
        $countries = Country::active()->orderBy('name')->get();
        
        return view('admin.customs-codes.index', compact('codes', 'countries'));
    }

    /**
     * Show the form for creating a new code
     */
    public function create()
    {
        $countries = Country::active()->orderBy('name')->get();
        return view('admin.customs-codes.create', compact('countries'));
    }

    /**
     * Store a newly created code
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'code' => 'required|string|max:20',
            'description' => 'required|string',
            'duty_rate' => 'required|numeric|min:0|max:100',
            'hs_code_version' => 'nullable|string|max:10',
        ]);

        CustomsCode::create($validated);

        return redirect()->route('admin.customs-codes.index')
            ->with('success', 'Customs code created successfully');
    }

    /**
     * Show the form for editing a code
     */
    public function edit(CustomsCode $customsCode)
    {
        $countries = Country::active()->orderBy('name')->get();
        return view('admin.customs-codes.edit', compact('customsCode', 'countries'));
    }

    /**
     * Update the specified code
     */
    public function update(Request $request, CustomsCode $customsCode)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'code' => 'required|string|max:20',
            'description' => 'required|string',
            'duty_rate' => 'required|numeric|min:0|max:100',
            'hs_code_version' => 'nullable|string|max:10',
        ]);

        $customsCode->update($validated);

        return redirect()->route('admin.customs-codes.index')
            ->with('success', 'Customs code updated successfully');
    }

    /**
     * Remove the specified code
     */
    public function destroy(CustomsCode $customsCode)
    {
        $customsCode->delete();

        return redirect()->route('admin.customs-codes.index')
            ->with('success', 'Customs code deleted successfully');
    }
}
