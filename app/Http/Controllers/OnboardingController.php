<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Show onboarding welcome screen
     */
    public function index()
    {
        $user = auth()->user();
        $countries = Country::active()->orderBy('name')->get();
        
        return view('onboarding.index', compact('user', 'countries'));
    }

    /**
     * Complete onboarding
     */
    public function complete(Request $request)
    {
        $user = auth()->user();
        
        // Mark onboarding as completed
        $user->onboarding_completed = true;
        $user->save();
        
        return redirect()->route('dashboard')->with('success', 'Welcome! Your account is all set up.');
    }

    /**
     * Skip onboarding
     */
    public function skip()
    {
        $user = auth()->user();
        $user->onboarding_completed = true;
        $user->save();
        
        return redirect()->route('dashboard');
    }
}
