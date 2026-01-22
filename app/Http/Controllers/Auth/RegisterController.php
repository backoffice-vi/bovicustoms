<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Show registration choice page
     */
    public function showChoice()
    {
        return view('auth.register-choice');
    }

    /**
     * Show organization registration form
     */
    public function showOrganizationForm()
    {
        $countries = Country::active()->orderBy('name')->get();
        return view('auth.register-organization', compact('countries'));
    }

    /**
     * Show individual registration form
     */
    public function showIndividualForm()
    {
        $countries = Country::active()->orderBy('name')->get();
        return view('auth.register-individual', compact('countries'));
    }

    /**
     * Register organization account
     */
    public function registerOrganization(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        // Create organization
        $organization = Organization::create([
            'name' => $validated['organization_name'],
            'slug' => Str::slug($validated['organization_name']),
            'country_id' => $validated['country_id'],
            'subscription_plan' => 'free',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(config('app.trial_period_days', 14)),
            'invoice_limit' => 10,
        ]);

        // Create user as organization owner
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $organization->id,
            'role' => 'admin',
            'is_individual' => false,
            'onboarding_completed' => false,
        ]);

        // Add user to organization with owner role
        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        Auth::login($user);

        return redirect()->route('onboarding.index');
    }

    /**
     * Register individual account
     */
    public function registerIndividual(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'country_id' => 'required|exists:countries,id',
        ]);

        // Create user as individual
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'current_country_id' => $validated['country_id'],
            'role' => 'user',
            'is_individual' => true,
            'onboarding_completed' => false,
        ]);

        Auth::login($user);

        return redirect()->route('onboarding.index');
    }
}
