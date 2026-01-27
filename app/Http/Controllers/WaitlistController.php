<?php

namespace App\Http\Controllers;

use App\Models\WaitlistSignup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WaitlistController extends Controller
{
    /**
     * Store a new waitlist signup
     */
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:waitlist_signups,email',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This email is already on our waitlist.',
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $signup = WaitlistSignup::create([
            'email' => $request->email,
            'source' => $request->source ?? 'landing_page',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully added to waitlist!',
                'signup_id' => $signup->id,
            ]);
        }

        return redirect()->route('waitlist.thank-you', ['signup' => $signup->id]);
    }

    /**
     * Show thank you page
     */
    public function thankYou($signupId)
    {
        $signup = WaitlistSignup::findOrFail($signupId);
        return view('waitlist.thank-you', compact('signup'));
    }

    /**
     * Store feedback from thank you page
     */
    public function storeFeedback(Request $request, $signupId)
    {
        $request->validate([
            'features' => 'array',
            'features.*' => 'string|in:bulk_processing,more_countries,api_access',
            'comments' => 'nullable|string|max:1000',
        ]);

        $signup = WaitlistSignup::findOrFail($signupId);
        
        $signup->update([
            'interested_features' => $request->features ?? [],
            'comments' => $request->comments,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!',
            ]);
        }

        return redirect()->route('waitlist.thank-you', ['signup' => $signup->id])
            ->with('feedback_saved', true);
    }
}
