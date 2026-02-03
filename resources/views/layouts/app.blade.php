<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- Primary Meta Tags --}}
    <title>@yield('title', 'BoVi Customs - Customs Automation Platform')</title>
    <meta name="description" content="@yield('meta_description', 'BoVi Customs - AI-powered customs automation platform for the British Virgin Islands. Streamline import/export declarations, HS code classification, and duty calculations.')">
    <meta name="keywords" content="@yield('meta_keywords', 'customs automation, BVI customs, British Virgin Islands, import declaration, export declaration, HS codes, tariff classification, duty calculator, customs clearance, trade compliance')">
    <meta name="author" content="BoVi Customs">
    <meta name="robots" content="index, follow">
    
    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ url()->current() }}">
    
    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('og_title', 'BoVi Customs - Customs Automation Platform')">
    <meta property="og:description" content="@yield('og_description', 'AI-powered customs automation for the British Virgin Islands. Streamline your import/export declarations with intelligent HS code classification.')">
    <meta property="og:image" content="@yield('og_image', 'https://bovicustoms.com/images/bovilogo-og.jpg')">
    <meta property="og:site_name" content="BoVi Customs">
    <meta property="og:locale" content="en_US">
    
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="@yield('twitter_title', 'BoVi Customs - Customs Automation Platform')">
    <meta name="twitter:description" content="@yield('twitter_description', 'AI-powered customs automation for the British Virgin Islands. Streamline your import/export declarations.')">
    <meta name="twitter:image" content="@yield('twitter_image', 'https://bovicustoms.com/images/bovilogo-og.jpg')">
    
    {{-- Favicon --}}
    <link rel="icon" type="image/jpeg" href="{{ asset('favicon.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.jpg') }}">
    
    {{-- Structured Data --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "BoVi Customs",
        "description": "AI-powered customs automation platform for the British Virgin Islands",
        "url": "https://bovicustoms.com",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "publisher": {
            "@type": "Organization",
            "name": "BoVi Customs",
            "url": "https://bovicustoms.com"
        }
    }
    </script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="fas fa-ship me-2"></i>
                @auth
                    @if(auth()->user()->organization)
                        {{ auth()->user()->organization->name }}
                    @else
                        BoVi Customs
                    @endif
                @else
                    BoVi Customs
                @endauth
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    @auth
                    @if(auth()->user()->isAgent())
                    {{-- Agent Navigation --}}
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('agent.dashboard') }}">
                            <i class="fas fa-tachometer-alt me-1"></i>Agent Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('agent.clients.index') }}">
                            <i class="fas fa-building me-1"></i>My Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('agent.declarations.index') }}">
                            <i class="fas fa-file-alt me-1"></i>Declarations
                        </a>
                    </li>
                    @else
                    {{-- Regular User Navigation --}}
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    
                    {{-- Classify Dropdown --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Classify
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('classification.index') }}">
                                <i class="fas fa-search me-2"></i>Lookup
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('settings.classification-rules') }}">
                                <i class="fas fa-cogs me-2"></i>Classification Rules
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('legacy-clearances.index') }}">
                                <i class="fas fa-archive me-2"></i>Legacy Clearances
                            </a></li>
                        </ul>
                    </li>
                    
                    {{-- Invoices Dropdown --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Invoices
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('invoices.create') }}">
                                <i class="fas fa-upload me-2"></i>Upload
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('invoices.index') }}">
                                <i class="fas fa-list me-2"></i>View
                            </a></li>
                        </ul>
                    </li>
                    
                    {{-- Declarations Dropdown --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Declarations
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('shipments.index') }}">
                                <i class="fas fa-ship me-2"></i>Shipments
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('declaration-forms.index') }}">
                                <i class="fas fa-file-alt me-2"></i>Declarations
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('trade-contacts.index') }}">
                                <i class="fas fa-address-book me-2"></i>Trade Contacts
                            </a></li>
                        </ul>
                    </li>
                    @endif
                    @if(auth()->user()->isAdmin())
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.users.index') }}">
                                <i class="fas fa-users me-1"></i>User Management
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.waitlist.index') }}">
                                <i class="fas fa-clipboard-list me-1"></i>Waitlist Signups
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.classification-logs.index') }}">
                                <i class="fas fa-search me-1"></i>Classification Logs
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}">
                                <i class="fas fa-cog me-1"></i>AI Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.countries.index') }}">Countries</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.customs-codes.index') }}">Customs Codes</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.tariff-database.index') }}">
                                <i class="fas fa-database me-1"></i>Tariff Database
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.law-documents.index') }}">
                                <i class="fas fa-gavel me-1"></i>Law Documents
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.country-documents.index') }}">
                                <i class="fas fa-file-alt me-1"></i>Country Documents
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.country-levies.index') }}">
                                <i class="fas fa-percentage me-1"></i>Country Levies
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.web-form-targets.index') }}">
                                <i class="fas fa-globe me-1"></i>Web Form Targets
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.ftp-test.index') }}">
                                <i class="fas fa-upload me-1"></i>FTP Submission Test
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.analytics.index') }}">
                                <i class="fas fa-chart-line me-1"></i>Site Analytics
                            </a></li>
                        </ul>
                    </li>
                    @endif
                    @endauth
                </ul>
                @auth
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            @if(auth()->user()->organization)
                            <li><a class="dropdown-item" href="{{ route('subscription.index') }}">
                                <i class="fas fa-credit-card me-2"></i>Subscription
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('settings.submission-credentials') }}">
                                <i class="fas fa-key me-2"></i>Submission Credentials
                            </a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('settings.help-center') }}">
                                <i class="fas fa-question-circle me-2"></i>Help Center
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
                @endauth
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">© {{ date('Y') }} BoVi Customs - Multi-Country Customs Automation Platform</span>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="{{ asset('js/custom.js') }}"></script>
    @stack('scripts')
</body>
</html>
