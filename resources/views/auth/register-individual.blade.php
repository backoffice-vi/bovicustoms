<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Individual Account - BoVi Customs</title>
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
        .register-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center logo">
            <i class="fas fa-ship"></i> BoVi Customs
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="register-card">
                    <h2 class="mb-4">Create Individual Account</h2>
                    <p class="text-muted mb-4">Get started with your free account today.</p>
                    
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.individual.post') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Primary Country</label>
                            <select name="country_id" class="form-select" required>
                                <option value="">Select your country</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                        {{ $country->flag_emoji }} {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                            Create Account
                        </button>

                        <p class="text-center text-muted mb-0">
                            <small>By creating an account, you agree to our Terms of Service and Privacy Policy</small>
                        </p>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <a href="{{ route('register.choice') }}" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i> Back to account type selection
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
