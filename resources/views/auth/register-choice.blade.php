<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Account Type - BoVi Customs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .choice-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 3px solid transparent;
        }
        .choice-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border-color: #2563eb;
        }
        .choice-icon {
            font-size: 4rem;
            color: #2563eb;
            margin-bottom: 20px;
        }
        .logo {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center logo">
            <i class="fas fa-ship"></i> BoVi Customs
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center text-white mb-5">
                    <h1 class="display-4 mb-3">Welcome! Let's Get Started</h1>
                    <p class="lead">Choose the account type that best fits your needs</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <a href="{{ route('register.organization') }}" class="text-decoration-none">
                            <div class="choice-card">
                                <div class="choice-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h2>Organization Account</h2>
                                <p class="text-muted mb-4">Perfect for customs brokers, freight forwarders, and businesses with teams</p>
                                <ul class="list-unstyled text-start">
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Invite team members</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Collaborate on declarations</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Organization-wide settings</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> 14-day free trial</li>
                                </ul>
                                <div class="btn btn-primary btn-lg mt-3 w-100">Create Organization</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('register.individual') }}" class="text-decoration-none">
                            <div class="choice-card">
                                <div class="choice-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h2>Individual Account</h2>
                                <p class="text-muted mb-4">Perfect for freelancers, small importers, and solo professionals</p>
                                <ul class="list-unstyled text-start">
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Personal workspace</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Manage your own declarations</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Simple and streamlined</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Free tier available</li>
                                </ul>
                                <div class="btn btn-outline-primary btn-lg mt-3 w-100">Create Individual Account</div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="text-center text-white mt-5">
                    <p>Already have an account? <a href="/login" class="text-white fw-bold">Sign in here</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
