<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customs Automation Platform - Streamline International Trade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
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
            <a class="navbar-brand" href="/">
                <i class="fas fa-ship"></i> BoVi Customs
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2" href="/register">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Automate Customs Declarations Across Multiple Countries</h1>
                    <p class="hero-subtitle">Save time, reduce errors, and streamline your international trade operations with AI-powered customs code matching and automated form generation.</p>
                    <a href="/register" class="btn btn-light btn-lg cta-button">
                        Start Free Trial <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    <p class="mt-3"><small>14-day free trial. No credit card required.</small></p>
                </div>
                <div class="col-lg-5">
                    <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'><rect fill='%23ffffff' opacity='0.1' width='400' height='300' rx='10'/><text x='200' y='150' text-anchor='middle' fill='white' font-size='24' font-family='Arial'>Dashboard Preview</text></svg>" alt="Dashboard Preview" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Powerful Features for Modern Customs Brokers</h2>
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

    <!-- Pricing Section -->
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

    <!-- Testimonials Section -->
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

    <!-- CTA Section -->
    <section class="hero-section">
        <div class="container text-center hero-content">
            <h2 class="hero-title">Ready to Transform Your Customs Process?</h2>
            <p class="hero-subtitle">Join hundreds of customs professionals who trust our platform</p>
            <a href="/register" class="btn btn-light btn-lg cta-button">
                Start Your Free Trial Today
            </a>
            <p class="mt-3"><small>No credit card required. 14-day free trial. Cancel anytime.</small></p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="mb-3"><i class="fas fa-ship"></i> BoVi Customs</h5>
                    <p>The modern platform for customs automation across multiple countries.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#features" class="text-white-50">Features</a></li>
                        <li><a href="#pricing" class="text-white-50">Pricing</a></li>
                        <li><a href="/login" class="text-white-50">Login</a></li>
                        <li><a href="/register" class="text-white-50">Sign Up</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Contact</h5>
                    <p class="text-white-50">
                        Email: support@customspro.com<br>
                        Phone: +1 (555) 123-4567
                    </p>
                </div>
            </div>
            <hr class="my-4 bg-white">
            <div class="text-center text-white-50">
                <p>&copy; 2026 BoVi Customs. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
