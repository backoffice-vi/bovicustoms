<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Test controller that simulates an external government customs portal.
 * Used to test Playwright automation in a controlled environment.
 */
class TestExternalFormController extends Controller
{
    /**
     * Show the simulated external form (login or declaration form)
     */
    public function index()
    {
        return view('test.external-form');
    }

    /**
     * Process login
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Simulated credentials check
        if ($validated['username'] === 'testuser' && $validated['password'] === 'testpass123') {
            session(['logged_in' => true, 'submitted' => false]);
            return redirect()->route('test.external-form');
        }

        return redirect()->route('test.external-form')
            ->with('login_error', 'Invalid username or password. Use testuser / testpass123');
    }

    /**
     * Process form submission
     */
    public function submit(Request $request)
    {
        if (!session('logged_in')) {
            return redirect()->route('test.external-form');
        }

        $validated = $request->validate([
            'vessel_name' => 'required|string|max:255',
            'voyage_number' => 'nullable|string|max:100',
            'bill_of_lading' => 'required|string|max:100',
            'manifest_number' => 'nullable|string|max:100',
            'port_of_loading' => 'required|string',
            'arrival_date' => 'required|date',
            'shipper_name' => 'required|string|max:255',
            'shipper_country' => 'required|string|size:2',
            'shipper_address' => 'nullable|string',
            'consignee_name' => 'required|string|max:255',
            'consignee_id' => 'required|string|max:100',
            'hs_code' => 'required|string|max:20',
            'country_of_origin' => 'required|string|size:2',
            'goods_description' => 'required|string',
            'quantity' => 'required|numeric|min:1',
            'gross_weight' => 'required|numeric|min:0',
            'total_packages' => 'required|integer|min:1',
            'fob_value' => 'required|numeric|min:0',
            'freight_value' => 'required|numeric|min:0',
            'insurance_value' => 'nullable|numeric|min:0',
        ]);

        // Generate a reference number (simulating external system response)
        $referenceNumber = 'TD-' . date('Y') . '-' . strtoupper(Str::random(8));

        // Log the submission for testing purposes
        \Log::info('Test External Form Submission', [
            'reference' => $referenceNumber,
            'data' => $validated,
            'submitted_at' => now()->toIso8601String(),
        ]);

        // Store submission result in session
        session([
            'submitted' => true,
            'reference_number' => $referenceNumber,
        ]);

        return redirect()->route('test.external-form');
    }

    /**
     * Logout
     */
    public function logout()
    {
        session()->forget(['logged_in', 'submitted', 'reference_number']);
        return redirect()->route('test.external-form');
    }

    /**
     * API endpoint to check submission result (for Playwright to verify)
     */
    public function checkSubmission(Request $request)
    {
        return response()->json([
            'logged_in' => session('logged_in', false),
            'submitted' => session('submitted', false),
            'reference_number' => session('reference_number'),
        ]);
    }
}
