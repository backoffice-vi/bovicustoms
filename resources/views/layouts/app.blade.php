<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BoVi Customs - Customs Automation Platform')</title>
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
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('invoices.create') }}">Upload Invoice</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('declaration-forms.index') }}">Declaration Forms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('legacy-clearances.index') }}">
                            <i class="fas fa-archive me-1"></i>Legacy Clearances
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('classification.index') }}">
                            <i class="fas fa-robot me-1"></i>Classify Item
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('trade-contacts.index') }}">
                            <i class="fas fa-address-book me-1"></i>Trade Contacts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('shipments.index') }}">
                            <i class="fas fa-ship me-1"></i>Shipments
                        </a>
                    </li>
                    @if(auth()->user()->organization)
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('subscription.index') }}">Subscription</a>
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
                            <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}">
                                <i class="fas fa-cog me-1"></i>AI Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.countries.index') }}">Countries</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.customs-codes.index') }}">Customs Codes</a></li>
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
                            <li><a class="dropdown-item" href="#">Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('settings.classification-rules') }}">
                                <i class="fas fa-cogs me-1"></i>Classification Rules
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Logout</button>
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
