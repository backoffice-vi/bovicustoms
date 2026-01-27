<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- Primary Meta Tags --}}
    <title>BoVi Customs - AI-Powered Customs Automation for the British Virgin Islands</title>
    <meta name="description" content="Streamline your import/export process with BoVi Customs. AI-powered HS code classification, automated duty calculations, and seamless customs declarations for the British Virgin Islands.">
    <meta name="keywords" content="customs automation, BVI customs, British Virgin Islands, import declaration, export declaration, HS codes, tariff classification, duty calculator, customs clearance, trade compliance, AI classification">
    <meta name="author" content="BoVi Customs">
    <meta name="robots" content="index, follow">
    
    {{-- Canonical URL --}}
    <link rel="canonical" href="https://bovicustoms.com">
    
    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://bovicustoms.com">
    <meta property="og:title" content="BoVi Customs - AI-Powered Customs Automation">
    <meta property="og:description" content="Streamline your import/export process with AI-powered HS code classification, automated duty calculations, and seamless customs declarations for the British Virgin Islands.">
    <meta property="og:image" content="https://bovicustoms.com/images/bovilogo-og.jpg">
    <meta property="og:site_name" content="BoVi Customs">
    <meta property="og:locale" content="en_US">
    
    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://bovicustoms.com">
    <meta name="twitter:title" content="BoVi Customs - AI-Powered Customs Automation">
    <meta name="twitter:description" content="Streamline your import/export process with AI-powered customs automation for the British Virgin Islands.">
    <meta name="twitter:image" content="https://bovicustoms.com/images/bovilogo-og.jpg">
    
    {{-- Favicon --}}
    <link rel="icon" type="image/jpeg" href="{{ asset('favicon.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.jpg') }}">
    
    {{-- Structured Data --}}
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "BoVi Customs",
        "description": "AI-powered customs automation platform for the British Virgin Islands. Streamline import/export declarations with intelligent HS code classification.",
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
            "url": "https://bovicustoms.com",
            "logo": "https://bovicustoms.com/images/bovilogo-og.jpg"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "ratingCount": "150"
        }
    }
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8B1A1A;
            --secondary-color: #6B0F0F;
            --accent-color: #A31621;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 100px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="white" opacity="0.1"/></svg>');
            background-size: 50px 50px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            opacity: 0.95;
            margin-bottom: 2rem;
        }

        .cta-button {
            padding: 15px 40px;
            font-size: 1.2rem;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .feature-card {
            padding: 30px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 20px;
        }

        .pricing-card {
            border-radius: 20px;
            padding: 40px 30px;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .pricing-card.featured {
            border-color: var(--primary-color);
            box-shadow: 0 20px 50px rgba(37, 99, 235, 0.2);
            transform: scale(1.05);
        }

        .pricing-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .price {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .price-period {
            font-size: 1.2rem;
            color: #6b7280;
        }

        .feature-check {
            color: #10b981;
            margin-right: 10px;
        }

        .testimonial-card {
            background: #f9fafb;
            border-radius: 15px;
            padding: 30px;
            margin: 20px 0;
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        section {
            padding: 80px 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: #6b7280;
            margin-bottom: 3rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/">
                <img src="{{ asset('images/bovilogo.jpg') }}" alt="BoVi Logo" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
                <span>BoVi Customs</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    {{-- <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li> --}}
                    {{-- <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li> --}}
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Login</a>
                    </li>
                    {{-- <li class="nav-item">
                        <a class="btn btn-primary ms-2" href="/register">Get Started</a>
                    </li> --}}
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <p class="text-uppercase mb-2" style="font-size: 0.9rem; letter-spacing: 1px; opacity: 0.9; font-weight: 600;">
                        For Customs Brokers, Businesses & Importers
                    </p>
                    <h1 class="hero-title">Automate Customs Declarations Across Multiple Countries</h1>
                    <p class="hero-subtitle">Save time, reduce errors, and streamline your international trade operations with AI-powered customs code matching and automated form generation.</p>
                    <a href="#try-demo" class="btn btn-light btn-lg cta-button">
                        Try AI Classifier Free <i class="fas fa-arrow-down ms-2"></i>
                    </a>
                    <p class="mt-3"><small>No signup required. Try it now.</small></p>
                </div>
                <div class="col-lg-5">
                    <img src="{{ asset('images/hero-dashboard-preview.png') }}" alt="AI Classification Demo - iPhone 15 Pro" class="img-fluid rounded shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <!-- Try Classification Demo Section -->
    <section id="try-demo" style="background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%); padding: 60px 0;">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="section-title" style="font-size: 2.2rem;">
                    <i class="fas fa-search-dollar me-2" style="color: var(--primary-color);"></i>Try Our AI Classification — Free
                </h2>
                <p class="section-subtitle mb-4">
                    See how our AI instantly finds the correct customs code for any product. No signup required.
                </p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Search Form Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            <form id="publicClassifyForm">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="publicItemInput" class="form-label fw-semibold">
                                            <i class="fas fa-box text-muted me-1"></i> Item Description
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-lg" 
                                               id="publicItemInput" 
                                               name="item"
                                               placeholder="e.g., laptop, frozen beef, cotton shirt, wine..."
                                               required
                                               minlength="2"
                                               maxlength="500"
                                               autocomplete="off">
                                        <div class="form-text">
                                            Enter any product - our AI understands natural language descriptions
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="publicCountrySelect" class="form-label fw-semibold">
                                            <i class="fas fa-globe text-muted me-1"></i> Country
                                        </label>
                                        <select class="form-select form-select-lg" id="publicCountrySelect" name="country_id">
                                            <option value="" selected>British Virgin Islands</option>
                                        </select>
                                        <div class="form-text">
                                            Using BVI 2010 Tariff Schedule
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 text-center">
                                    <button type="submit" class="btn btn-lg px-5" id="publicClassifyBtn" style="background: var(--primary-color); color: white; border: none;">
                                        <i class="fas fa-robot me-2"></i>Classify Item
                                    </button>
                                </div>
                            </form>

                            <!-- Quick Examples -->
                            <div class="mt-4">
                                <div class="text-center mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>Quick examples:
                                    </small>
                                </div>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary public-example-btn" data-item="iPhone 15 Pro">iPhone</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary public-example-btn" data-item="Frozen chicken breast">Frozen Chicken</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary public-example-btn" data-item="Car">Car</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary public-example-btn" data-item="Wine from France">Wine</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary public-example-btn" data-item="Cotton t-shirt">Cotton Shirt</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary public-example-btn" data-item="LED Television 55 inch">Television</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="publicLoadingState" class="text-center py-5 d-none">
                        <div class="spinner-border mb-3" style="width: 3rem; height: 3rem; color: var(--primary-color);" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="text-muted">AI is analyzing your item...</h5>
                        <p class="text-muted small">This usually takes a few seconds</p>
                    </div>

                    <!-- Error State -->
                    <div id="publicErrorState" class="d-none">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="publicErrorMessage">An error occurred</span>
                        </div>
                    </div>

                    <!-- Result Card -->
                    <div id="publicResultCard" class="d-none">
                        <div class="card shadow-lg border-0">
                            <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-check-circle me-2"></i>Classification Result
                                    </h5>
                                    <span class="badge bg-white fs-6" id="publicConfidenceBadge" style="color: #059669;">
                                        95% Confidence
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-4">
                                            <label class="text-muted small text-uppercase">Item Searched</label>
                                            <h4 id="publicSearchedItem" class="mb-0">-</h4>
                                        </div>
                                        <div class="mb-4">
                                            <label class="text-muted small text-uppercase">Best Matching HS Code</label>
                                            <h3 class="mb-1" style="color: var(--primary-color);">
                                                <span id="publicResultCode">-</span>
                                            </h3>
                                            <p id="publicResultDescription" class="mb-0 text-dark">-</p>
                                        </div>
                                        <div class="mb-4">
                                            <label class="text-muted small text-uppercase">AI Explanation</label>
                                            <p id="publicResultExplanation" class="mb-0 fst-italic text-secondary">-</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-light rounded p-3 text-center mb-3">
                                            <label class="text-muted small text-uppercase d-block mb-2">Duty Rate</label>
                                            <h2 id="publicResultDutyRate" class="mb-0" style="color: #059669;">-</h2>
                                        </div>
                                        <div>
                                            <label class="text-muted small text-uppercase d-block mb-2">Match Score</label>
                                            <div class="progress" style="height: 20px;">
                                                <div id="publicVectorScoreBar" class="progress-bar bg-info" role="progressbar" style="width: 0%">
                                                    <span id="publicVectorScoreText">0%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Alternatives Preview -->
                                <div id="publicAlternativesPreview" class="mt-4 d-none">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Multiple matches found.</strong> Sign up to see all alternatives and save your classifications.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- CTA Card -->
                            <div class="card-footer border-0 p-4" style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);">
                                <div class="text-center">
                                    <h5 class="mb-3">
                                        <i class="fas fa-bell me-2" style="color: var(--primary-color);"></i>Want early access to new features?
                                    </h5>
                                    <p class="text-muted mb-3">
                                        Get notified when we add invoice processing, multi-country support, and API access.
                                    </p>
                                    
                                    <!-- Email Signup Form -->
                                    <form id="waitlistForm" class="mb-3">
                                        @csrf
                                        <div class="row justify-content-center">
                                            <div class="col-md-8">
                                                <div class="input-group input-group-lg">
                                                    <input type="email" 
                                                           class="form-control" 
                                                           id="waitlistEmail" 
                                                           name="email"
                                                           placeholder="Enter your email address"
                                                           required>
                                                    <button type="submit" class="btn" style="background: var(--primary-color); color: white; border: none;">
                                                        <i class="fas fa-arrow-right me-1"></i>Get Notified
                                                    </button>
                                                </div>
                                                <div id="waitlistError" class="text-danger mt-2 d-none"></div>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            ✓ Invoice processing coming soon<br>
                                            ✓ Multi-country support<br>
                                            ✓ API access for developers
                                        </small>
                                    </div>
                                    
                                    {{-- Hidden for now
                                    <p class="small text-muted mb-0">
                                        Already convinced? <a href="/register" style="color: var(--primary-color);">Start your free trial</a>
                                    </p>
                                    --}}
                                </div>
                            </div>
                        </div>

                        <!-- Usage Indicator -->
                        <div class="text-center mt-3">
                            <small class="text-muted" id="publicUsageIndicator">
                                <i class="fas fa-check-circle" style="color: #10b981;"></i> <span id="publicRemainingCount">-</span> free classifications remaining today
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Built for Brokers, Businesses & Importers</h2>
                <p class="section-subtitle">Everything you need to process customs declarations efficiently</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h3>OCR Invoice Extraction</h3>
                        <p>Automatically extract line items, quantities, and values from uploaded invoices using advanced OCR technology. Save hours of manual data entry.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3>AI Code Matching</h3>
                        <p>Let our AI suggest the correct HS customs codes for your products with confidence scores and historical reference data.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-globe-americas"></i>
                        </div>
                        <h3>Multi-Country Support</h3>
                        <p>Process declarations for multiple countries with country-specific customs codes, duty rates, and form templates.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Team Collaboration</h3>
                        <p>Invite team members, assign roles, and collaborate on declarations with built-in organization management.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h3>Auto Form Generation</h3>
                        <p>Generate country-specific declaration forms automatically with all required fields populated and duty calculations complete.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Usage Analytics</h3>
                        <p>Track your monthly usage, monitor team performance, and gain insights into your customs processing workflow.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing Section - Hidden for now
    <section id="pricing">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Simple, Transparent Pricing</h2>
                <p class="section-subtitle">Choose the plan that fits your business needs</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="pricing-card">
                        <h3>Free</h3>
                        <div class="price">$0<span class="price-period">/mo</span></div>
                        <p class="text-muted mb-4">Perfect for trying out the platform</p>
                        <ul class="list-unstyled">
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> 10 invoices per month</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> 1 country</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Individual account only</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Basic support</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Email notifications</li>
                        </ul>
                        <a href="/register" class="btn btn-outline-primary w-100 mt-3">Get Started</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="pricing-card featured">
                        <span class="pricing-badge">Most Popular</span>
                        <h3>Pro</h3>
                        <div class="price">$49<span class="price-period">/mo</span></div>
                        <p class="text-muted mb-4">For growing customs brokers</p>
                        <ul class="list-unstyled">
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Unlimited invoices</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Up to 5 countries</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Up to 10 team members</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Priority support</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Custom code preferences</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Advanced analytics</li>
                        </ul>
                        <a href="/register" class="btn btn-primary w-100 mt-3">Start Free Trial</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="pricing-card">
                        <h3>Enterprise</h3>
                        <div class="price">$199<span class="price-period">/mo</span></div>
                        <p class="text-muted mb-4">For large organizations</p>
                        <ul class="list-unstyled">
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Unlimited everything</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> All countries</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Unlimited team members</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Dedicated support</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> API access</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> Custom integrations</li>
                            <li class="mb-3"><i class="fas fa-check feature-check"></i> SLA guarantee</li>
                        </ul>
                        <a href="/register" class="btn btn-outline-primary w-100 mt-3">Contact Sales</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    --}}

    {{-- Testimonials Section - Hidden for now
    <section class="bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Trusted by Customs Professionals Worldwide</h2>
                <p class="section-subtitle">See what our customers have to say</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p>"This platform has cut our declaration processing time by 70%. The AI code matching is incredibly accurate!"</p>
                        <strong>Sarah Johnson</strong>
                        <p class="text-muted small">Customs Broker, Miami</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p>"Managing multiple countries was a nightmare before. Now it's seamless. Highly recommend!"</p>
                        <strong>Michael Chen</strong>
                        <p class="text-muted small">Import Manager, Hong Kong</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p>"The team collaboration features have transformed how we work. Everyone stays on the same page."</p>
                        <strong>Emma Rodriguez</strong>
                        <p class="text-muted small">Operations Director, Toronto</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    --}}

    <!-- CTA Section with Email Signup -->
    <section id="early-access" class="hero-section">
        <div class="container text-center hero-content">
            <h2 class="hero-title">Like What You See?</h2>
            <p class="hero-subtitle">Get notified when we add more powerful features</p>
            
            <!-- Email Signup Form -->
            <form id="ctaWaitlistForm" class="mb-4">
                @csrf
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-5">
                        <div class="input-group input-group-lg">
                            <input type="email" 
                                   class="form-control" 
                                   id="ctaWaitlistEmail" 
                                   name="email"
                                   placeholder="Enter your email address"
                                   required
                                   style="border-radius: 50px 0 0 50px;">
                            <button type="submit" class="btn btn-light cta-button" style="border-radius: 0 50px 50px 0; padding: 10px 30px;">
                                <i class="fas fa-arrow-right me-1"></i>Get Early Access
                            </button>
                        </div>
                        <div id="ctaWaitlistError" class="text-warning mt-2 d-none"></div>
                        <div id="ctaWaitlistSuccess" class="text-light mt-2 d-none">
                            <i class="fas fa-check-circle me-1"></i>Thanks! We'll be in touch soon.
                        </div>
                    </div>
                </div>
            </form>
            
            <p class="mt-3"><small>Be the first to know about invoice processing, multi-country support, and API access</small></p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row justify-content-between">
                <div class="col-md-6">
                    <h5 class="mb-3"><i class="fas fa-ship"></i> BoVi Customs</h5>
                    <p>The modern platform for customs automation across multiple countries.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Contact</h5>
                    <p class="text-white-50">
                        Email: support@backofficevi.com<br>
                        Phone: 1-284-440-8324
                    </p>
                </div>
            </div>
            <hr class="my-4 bg-white">
            <div class="text-center text-white-50">
                <p>&copy; 2026 BoVi Customs. All rights reserved.</p>
                <p class="small">BoVi Customs — A <a href="https://backofficevi.com" class="text-white-50" target="_blank">Back Office VI</a> Development</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const publicForm = document.getElementById('publicClassifyForm');
        const publicItemInput = document.getElementById('publicItemInput');
        const publicClassifyBtn = document.getElementById('publicClassifyBtn');
        const publicLoadingState = document.getElementById('publicLoadingState');
        const publicErrorState = document.getElementById('publicErrorState');
        const publicErrorMessage = document.getElementById('publicErrorMessage');
        const publicResultCard = document.getElementById('publicResultCard');

        // Example buttons
        document.querySelectorAll('.public-example-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                publicItemInput.value = this.dataset.item;
                publicClassifyItem();
            });
        });

        // Form submission
        publicForm.addEventListener('submit', function(e) {
            e.preventDefault();
            publicClassifyItem();
        });

        function publicClassifyItem() {
            const item = publicItemInput.value.trim();
            if (!item || item.length < 2) {
                showPublicError('Please enter at least 2 characters');
                return;
            }

            // Show loading, hide others
            publicLoadingState.classList.remove('d-none');
            publicErrorState.classList.add('d-none');
            publicResultCard.classList.add('d-none');
            publicClassifyBtn.disabled = true;
            publicClassifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Classifying...';

            // Make API request to public endpoint
            fetch('/api/public-classify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    item: item
                })
            })
            .then(response => response.json())
            .then(data => {
                publicLoadingState.classList.add('d-none');
                publicClassifyBtn.disabled = false;
                publicClassifyBtn.innerHTML = '<i class="fas fa-robot me-2"></i>Classify Item';

                if (data.success && data.match) {
                    showPublicResult(data);
                } else {
                    showPublicError(data.error || 'Failed to classify item');
                }
            })
            .catch(error => {
                publicLoadingState.classList.add('d-none');
                publicClassifyBtn.disabled = false;
                publicClassifyBtn.innerHTML = '<i class="fas fa-robot me-2"></i>Classify Item';
                showPublicError('Network error. Please try again.');
                console.error('Error:', error);
            });
        }

        function showPublicResult(data) {
            const match = data.match;
            
            document.getElementById('publicSearchedItem').textContent = data.item;
            document.getElementById('publicResultCode').textContent = match.code || 'N/A';
            document.getElementById('publicResultDescription').textContent = match.description || 'No description';
            document.getElementById('publicResultExplanation').textContent = match.explanation || 'No explanation provided';
            
            // Confidence badge
            const confidence = match.confidence || 0;
            const badge = document.getElementById('publicConfidenceBadge');
            badge.textContent = confidence + '% Confidence';
            
            if (confidence >= 80) {
                badge.style.color = '#059669';
            } else if (confidence >= 50) {
                badge.style.color = '#d97706';
            } else {
                badge.style.color = '#dc2626';
            }
            
            // Duty rate
            const dutyRate = match.duty_rate;
            document.getElementById('publicResultDutyRate').textContent = 
                dutyRate !== null && dutyRate !== undefined ? dutyRate + '%' : 'N/A';
            
            // Vector score bar
            const vectorScore = match.vector_score || 0;
            const scoreBar = document.getElementById('publicVectorScoreBar');
            const scoreText = document.getElementById('publicVectorScoreText');
            scoreBar.style.width = vectorScore + '%';
            scoreText.textContent = vectorScore.toFixed(1) + '%';
            
            // Color based on score
            if (vectorScore >= 40) {
                scoreBar.className = 'progress-bar bg-success';
            } else if (vectorScore >= 25) {
                scoreBar.className = 'progress-bar bg-info';
            } else {
                scoreBar.className = 'progress-bar bg-warning';
            }

            // Show alternatives preview if available
            const alternativesPreview = document.getElementById('publicAlternativesPreview');
            if (match.alternatives && match.alternatives.length > 0) {
                alternativesPreview.classList.remove('d-none');
            } else {
                alternativesPreview.classList.add('d-none');
            }

            // Update usage indicator
            if (data.remaining !== undefined) {
                const remainingCount = document.getElementById('publicRemainingCount');
                const usageIndicator = document.getElementById('publicUsageIndicator');
                remainingCount.textContent = data.remaining;
                
                if (data.remaining === 0) {
                    usageIndicator.innerHTML = '<i class="fas fa-info-circle" style="color: #d97706;"></i> Sign up for unlimited access';
                }
            }
            
            publicResultCard.classList.remove('d-none');
            
            // Smooth scroll to result
            publicResultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function showPublicError(message) {
            publicErrorMessage.textContent = message;
            publicErrorState.classList.remove('d-none');
        }

        // Waitlist form handler
        const waitlistForm = document.getElementById('waitlistForm');
        const waitlistEmail = document.getElementById('waitlistEmail');
        const waitlistError = document.getElementById('waitlistError');

        if (waitlistForm) {
            waitlistForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const email = waitlistEmail.value.trim();
                if (!email) {
                    waitlistError.textContent = 'Please enter your email address';
                    waitlistError.classList.remove('d-none');
                    return;
                }

                // Hide any previous errors
                waitlistError.classList.add('d-none');

                // Disable submit button
                const submitBtn = waitlistForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

                // Submit to backend
                fetch('{{ route("waitlist.signup") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        source: 'landing_classification_demo'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to thank you page
                        window.location.href = '{{ url("/waitlist/thank-you") }}/' + data.signup_id;
                    } else {
                        // Show error
                        waitlistError.textContent = data.message || 'An error occurred. Please try again.';
                        waitlistError.classList.remove('d-none');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    waitlistError.textContent = 'An error occurred. Please try again.';
                    waitlistError.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });
        }

        // CTA Waitlist form handler
        const ctaWaitlistForm = document.getElementById('ctaWaitlistForm');
        const ctaWaitlistEmail = document.getElementById('ctaWaitlistEmail');
        const ctaWaitlistError = document.getElementById('ctaWaitlistError');
        const ctaWaitlistSuccess = document.getElementById('ctaWaitlistSuccess');

        if (ctaWaitlistForm) {
            ctaWaitlistForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const email = ctaWaitlistEmail.value.trim();
                if (!email) {
                    ctaWaitlistError.textContent = 'Please enter your email address';
                    ctaWaitlistError.classList.remove('d-none');
                    return;
                }

                // Hide any previous errors/success
                ctaWaitlistError.classList.add('d-none');
                ctaWaitlistSuccess.classList.add('d-none');

                // Disable submit button
                const submitBtn = ctaWaitlistForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

                // Submit to backend
                fetch('{{ route("waitlist.signup") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        source: 'landing_cta_section'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message inline
                        ctaWaitlistSuccess.classList.remove('d-none');
                        ctaWaitlistEmail.value = '';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    } else {
                        // Show error
                        ctaWaitlistError.textContent = data.message || 'An error occurred. Please try again.';
                        ctaWaitlistError.classList.remove('d-none');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    ctaWaitlistError.textContent = 'An error occurred. Please try again.';
                    ctaWaitlistError.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });
        }
    });
    </script>
</body>
</html>
