<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - BoVi Customs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 40px 0;
        }
        .onboarding-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            margin: 0 auto;
        }
        .welcome-icon {
            font-size: 5rem;
            color: #2563eb;
            margin-bottom: 30px;
        }
        .feature-item {
            display: flex;
            align-items: start;
            margin-bottom: 20px;
        }
        .feature-item i {
            color: #10b981;
            font-size: 1.5rem;
            margin-right: 15px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="onboarding-card">
            <div class="text-center">
                <div class="welcome-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="mb-3">Welcome to BoVi Customs, {{ $user->name }}!</h1>
                <p class="lead text-muted mb-4">
                    @if($user->organization)
                        Your organization <strong>{{ $user->organization->name }}</strong> has been created successfully.
                    @else
                        Your individual account has been created successfully.
                    @endif
                </p>
            </div>

            <hr class="my-4">

            <div class="mb-4">
                <h3 class="mb-3">Quick Start Guide</h3>
                <div class="feature-item">
                    <i class="fas fa-upload"></i>
                    <div>
                        <h5>Upload Your First Invoice</h5>
                        <p class="text-muted">Start by uploading an invoice in PDF or image format. Our OCR will extract the data automatically.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <i class="fas fa-check-double"></i>
                    <div>
                        <h5>Review & Assign Customs Codes</h5>
                        <p class="text-muted">Our AI will suggest HS codes for each item. Review and adjust as needed before finalizing.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <i class="fas fa-file-download"></i>
                    <div>
                        <h5>Generate Declaration Forms</h5>
                        <p class="text-muted">Once codes are assigned, generate country-specific declaration forms ready for submission.</p>
                    </div>
                </div>
            </div>

            @if($user->organization && $user->organization->isOnTrial())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Trial Period:</strong> Your 14-day free trial ends on {{ $user->organization->trial_ends_at->format('F j, Y') }}. 
                    You can upgrade anytime from the subscription page.
                </div>
            @endif

            <div class="d-grid gap-2 mt-4">
                <form method="POST" action="{{ route('onboarding.complete') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        Get Started <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>
                <a href="{{ route('onboarding.skip') }}" class="btn btn-link text-muted">
                    I'll explore on my own
                </a>
            </div>

            @if($user->organization)
                <hr class="my-4">
                <div class="text-center">
                    <p class="text-muted mb-2">Need to invite team members?</p>
                    <p class="small">You can invite colleagues from your dashboard settings after getting started.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
