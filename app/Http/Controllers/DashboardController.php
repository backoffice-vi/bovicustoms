<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\DeclarationForm;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $pendingInvoices = Invoice::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $processedInvoices = Invoice::where('status', 'processed')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $recentForms = DeclarationForm::orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('dashboard', compact('pendingInvoices', 'processedInvoices', 'recentForms'));
    }
}
