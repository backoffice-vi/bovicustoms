<?php

namespace App\Http\Controllers;

use App\Models\TradeContact;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TradeContactController extends Controller
{
    /**
     * Display a listing of trade contacts
     */
    public function index(Request $request)
    {
        $contactTypes = TradeContact::getContactTypes();
        $selectedType = $request->get('type');

        $query = TradeContact::with('country')->orderBy('company_name');

        if ($selectedType && array_key_exists($selectedType, $contactTypes)) {
            $query->ofType($selectedType);
        }

        $contacts = $query->paginate(15)->withQueryString();

        return view('trade-contacts.index', compact('contacts', 'contactTypes', 'selectedType'));
    }

    /**
     * Show the form for creating a new contact
     */
    public function create(Request $request)
    {
        $contactTypes = TradeContact::getContactTypes();
        $countries = Country::active()->orderBy('name')->get();
        $preselectedType = $request->get('type');

        return view('trade-contacts.create', compact('contactTypes', 'countries', 'preselectedType'));
    }

    /**
     * Store a newly created contact
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_type' => 'required|in:' . implode(',', array_keys(TradeContact::getContactTypes())),
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tax_id' => 'nullable|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_routing' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'is_default' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        $validated['user_id'] = $user->id;
        $validated['organization_id'] = $user->organization_id;
        $validated['is_default'] = $request->boolean('is_default');

        $contact = TradeContact::create($validated);

        // Set as default if requested
        if ($contact->is_default) {
            $contact->setAsDefault();
        }

        // Check if this is an AJAX request (from modal)
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'contact' => $contact->load('country'),
                'message' => 'Contact created successfully.',
            ]);
        }

        return redirect()->route('trade-contacts.index')
            ->with('success', 'Trade contact created successfully.');
    }

    /**
     * Display the specified contact
     */
    public function show(TradeContact $tradeContact)
    {
        $tradeContact->load('country');
        
        return view('trade-contacts.show', compact('tradeContact'));
    }

    /**
     * Show the form for editing the specified contact
     */
    public function edit(TradeContact $tradeContact)
    {
        $contactTypes = TradeContact::getContactTypes();
        $countries = Country::active()->orderBy('name')->get();

        return view('trade-contacts.edit', compact('tradeContact', 'contactTypes', 'countries'));
    }

    /**
     * Update the specified contact
     */
    public function update(Request $request, TradeContact $tradeContact)
    {
        $validated = $request->validate([
            'contact_type' => 'required|in:' . implode(',', array_keys(TradeContact::getContactTypes())),
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'tax_id' => 'nullable|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_routing' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['is_default'] = $request->boolean('is_default');

        $tradeContact->update($validated);

        // Set as default if requested
        if ($tradeContact->is_default) {
            $tradeContact->setAsDefault();
        }

        return redirect()->route('trade-contacts.index')
            ->with('success', 'Trade contact updated successfully.');
    }

    /**
     * Remove the specified contact
     */
    public function destroy(TradeContact $tradeContact)
    {
        $tradeContact->delete();

        return redirect()->route('trade-contacts.index')
            ->with('success', 'Trade contact deleted successfully.');
    }

    /**
     * Toggle default status for a contact
     */
    public function toggleDefault(TradeContact $tradeContact)
    {
        if ($tradeContact->is_default) {
            $tradeContact->update(['is_default' => false]);
            $message = 'Contact is no longer the default.';
        } else {
            $tradeContact->setAsDefault();
            $message = 'Contact set as default for ' . $tradeContact->contact_type_label . '.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Get contacts by type (for AJAX dropdowns)
     */
    public function byType(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(TradeContact::getContactTypes())),
        ]);

        $contacts = TradeContact::with('country')
            ->ofType($request->type)
            ->orderByDesc('is_default')
            ->orderBy('company_name')
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'company_name' => $contact->company_name,
                    'contact_name' => $contact->contact_name,
                    'display_name' => $contact->display_name,
                    'full_address' => $contact->full_address,
                    'is_default' => $contact->is_default,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'tax_id' => $contact->tax_id,
                    'license_number' => $contact->license_number,
                ];
            });

        return response()->json([
            'success' => true,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Get a single contact's full data (for form filling)
     */
    public function getFormData(TradeContact $tradeContact): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tradeContact->toFormData(),
        ]);
    }
}
