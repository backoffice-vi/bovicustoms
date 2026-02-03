<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    /**
     * Display the help center with application flow information
     */
    public function index()
    {
        return view('settings.help-center');
    }
}
