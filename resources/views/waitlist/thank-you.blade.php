<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Thank You - BoVi Customs</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thank-you-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            margin: 20px;
        }

        .check-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -40px auto 20px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
        }

        .feedback-option {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .feedback-option:hover {
            border-color: var(--primary-color);
            background: #f9fafb;
        }

        .feedback-option input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
        }

        .feedback-option input[type="checkbox"]:checked + label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .btn-primary-custom {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 26, 26, 0.3);
        }

        .btn-skip {
            color: #6b7280;
            background: none;
            border: none;
            padding: 12px 30px;
        }

        .btn-skip:hover {
            color: var(--primary-color);
        }

        .comments-section {
            margin-top: 20px;
            text-align: left;
        }

        .comments-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.3s ease;
        }

        .comments-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 26, 26, 0.1);
        }

        .comments-textarea::placeholder {
            color: #9ca3af;
        }

        .comments-label {
            display: block;
            margin-bottom: 8px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="thank-you-card">
        <div class="check-icon">
            <i class="fas fa-check fa-3x text-white"></i>
        </div>
        
        <div class="p-5 text-center">
            <h1 class="mb-3">✅ Thanks! You're on the list.</h1>
            <p class="lead text-muted mb-4">
                We'll keep you updated as we roll out new features.
            </p>

            @if(session('feedback_saved'))
                <div class="alert alert-success">
                    <i class="fas fa-heart me-2"></i>Thanks for your feedback!
                </div>
            @endif

            @if(!$signup->interested_features)
                <div class="mt-5">
                    <h5 class="mb-4 text-muted">
                        <i class="fas fa-lightbulb me-2"></i>[OPTIONAL] Help us improve
                    </h5>
                    <p class="text-muted mb-3">What would you most want to see next?</p>
                    
                    <form id="feedbackForm" method="POST" action="{{ route('waitlist.feedback', $signup->id) }}">
                        @csrf
                        
                        <div class="feedback-option">
                            <input type="checkbox" name="features[]" value="bulk_processing" id="bulk_processing">
                            <label for="bulk_processing" class="mb-0">
                                <strong>Bulk Invoice Processing</strong>
                                <br><small class="text-muted">Process multiple invoices at once</small>
                            </label>
                        </div>

                        <div class="feedback-option">
                            <input type="checkbox" name="features[]" value="more_countries" id="more_countries">
                            <label for="more_countries" class="mb-0">
                                <strong>More Countries</strong>
                                <br><small class="text-muted">Support for additional customs jurisdictions</small>
                            </label>
                        </div>

                        <div class="feedback-option">
                            <input type="checkbox" name="features[]" value="api_access" id="api_access">
                            <label for="api_access" class="mb-0">
                                <strong>API Access</strong>
                                <br><small class="text-muted">Integrate classification into your systems</small>
                            </label>
                        </div>

                        <div class="comments-section">
                            <label for="comments" class="comments-label">
                                <i class="fas fa-comment-dots me-1"></i>Anything else we should know? (optional)
                            </label>
                            <textarea 
                                name="comments" 
                                id="comments" 
                                class="comments-textarea"
                                placeholder="Feel free to share any other thoughts, feature requests, or feedback..."
                                maxlength="1000"></textarea>
                            <small class="text-muted">Max 1000 characters</small>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary-custom me-2">
                                <i class="fas fa-paper-plane me-2"></i>Send Feedback
                            </button>
                            <a href="/" class="btn btn-skip">
                                Skip
                            </a>
                        </div>
                    </form>
                </div>
            @else
                <div class="mt-4">
                    <a href="/" class="btn btn-primary-custom">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
            @endif

            {{-- Hidden for now
            <div class="mt-5 pt-4 border-top">
                <p class="text-muted small mb-0">
                    <strong>Already convinced?</strong> 
                    <a href="/register" style="color: var(--primary-color);">Start your free trial</a>
                </p>
            </div>
            --}}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
