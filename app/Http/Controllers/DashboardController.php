<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\DeclarationForm;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get pending and processed invoices (already scoped by tenant via global scope)
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

        // Get usage stats
        $monthStart = now()->startOfMonth();
        if ($user->organization_id) {
            $invoicesThisMonth = $user->organization->invoices()->where('created_at', '>=', $monthStart)->count();
            $invoiceLimit = $user->organization->invoice_limit;
            $organization = $user->organization;
        } else {
            $invoicesThisMonth = $user->invoices()->where('created_at', '>=', $monthStart)->count();
            $invoiceLimit = 10;
            $organization = null;
        }

        return view('invoices.dashboard', compact(
            'pendingInvoices', 
            'processedInvoices', 
            'recentForms',
            'invoicesThisMonth',
            'invoiceLimit',
            'organization',
            'user'
        ));
    }
}
