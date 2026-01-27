<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulated External Customs Portal</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #1a365d; 
            margin: 0; 
            padding: 20px;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .header {
            background: #2c5282;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header p { margin: 5px 0 0; opacity: 0.8; }
        .form-body { padding: 30px; }
        .section { margin-bottom: 25px; }
        .section h3 { 
            color: #2c5282; 
            border-bottom: 2px solid #e2e8f0; 
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        .form-group label { 
            display: block; 
            font-weight: bold; 
            margin-bottom: 5px; 
            color: #4a5568;
            font-size: 0.9rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66,153,225,0.2);
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
        }
        .btn-primary { background: #48bb78; color: white; }
        .btn-primary:hover { background: #38a169; }
        .btn-secondary { background: #718096; color: white; }
        .login-form {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
        }
        .login-form h2 { text-align: center; color: #2c5282; }
        .login-form .form-group { margin-bottom: 20px; }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success { background: #c6f6d5; color: #276749; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #c53030; border: 1px solid #fc8181; }
        .submission-result {
            background: #f0fff4;
            border: 2px solid #48bb78;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .submission-result h2 { color: #276749; }
        .reference-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c5282;
            background: #ebf8ff;
            padding: 10px 20px;
            border-radius: 4px;
            display: inline-block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    @if(!session('logged_in'))
        {{-- LOGIN PAGE --}}
        <div class="login-form">
            <h2>🏛️ External Customs Portal</h2>
            <p style="text-align: center; color: #718096; margin-bottom: 30px;">
                Simulated Government System
            </p>
            
            @if(session('login_error'))
                <div class="alert alert-error">{{ session('login_error') }}</div>
            @endif
            
            <form method="POST" action="{{ route('test.external-form.login') }}">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter username" data-testid="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter password" data-testid="password">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;" data-testid="login-btn">
                    Login to Portal
                </button>
            </form>
            <p style="text-align: center; margin-top: 20px; color: #a0aec0; font-size: 0.85rem;">
                Test credentials: testuser / testpass123
            </p>
        </div>
    @elseif(session('submitted'))
        {{-- SUBMISSION SUCCESS PAGE --}}
        <div class="container">
            <div class="header">
                <h1>🏛️ External Customs Portal</h1>
                <p>Trade Declaration System</p>
            </div>
            <div class="form-body">
                <div class="submission-result">
                    <h2>✅ Declaration Submitted Successfully!</h2>
                    <p>Your trade declaration has been received and is being processed.</p>
                    <div class="reference-number" data-testid="reference-number">
                        {{ session('reference_number') }}
                    </div>
                    <p style="color: #718096; margin-top: 15px;">
                        Submitted at: {{ now()->format('Y-m-d H:i:s') }}
                    </p>
                </div>
                <div style="margin-top: 30px; text-align: center;">
                    <a href="{{ route('test.external-form') }}" class="btn btn-secondary">
                        Submit Another Declaration
                    </a>
                    <a href="{{ route('test.external-form.logout') }}" class="btn btn-secondary" style="margin-left: 10px;">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    @else
        {{-- DECLARATION FORM --}}
        <div class="container">
            <div class="header">
                <h1>🏛️ External Customs Portal</h1>
                <p>Trade Declaration Entry Form</p>
            </div>
            <div class="form-body">
                @if(session('error'))
                    <div class="alert alert-error">{{ session('error') }}</div>
                @endif
                
                <form method="POST" action="{{ route('test.external-form.submit') }}" id="declaration-form">
                    @csrf
                    
                    <div class="section">
                        <h3>📦 Shipment Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vessel_name">Vessel / Flight Name *</label>
                                <input type="text" id="vessel_name" name="vessel_name" required
                                       data-testid="vessel_name">
                            </div>
                            <div class="form-group">
                                <label for="voyage_number">Voyage / Flight Number</label>
                                <input type="text" id="voyage_number" name="voyage_number"
                                       data-testid="voyage_number">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bill_of_lading">Bill of Lading / AWB Number *</label>
                                <input type="text" id="bill_of_lading" name="bill_of_lading" required
                                       data-testid="bill_of_lading">
                            </div>
                            <div class="form-group">
                                <label for="manifest_number">Manifest Number</label>
                                <input type="text" id="manifest_number" name="manifest_number"
                                       data-testid="manifest_number">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="port_of_loading">Port of Loading *</label>
                                <select id="port_of_loading" name="port_of_loading" required
                                        data-testid="port_of_loading">
                                    <option value="">-- Select Port --</option>
                                    <option value="USNYC">New York, USA</option>
                                    <option value="USMIA">Miami, USA</option>
                                    <option value="USSJU">San Juan, Puerto Rico</option>
                                    <option value="GBLON">London, UK</option>
                                    <option value="CNSHA">Shanghai, China</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="arrival_date">Arrival Date *</label>
                                <input type="date" id="arrival_date" name="arrival_date" required
                                       data-testid="arrival_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>🏢 Shipper / Exporter</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipper_name">Company Name *</label>
                                <input type="text" id="shipper_name" name="shipper_name" required
                                       data-testid="shipper_name">
                            </div>
                            <div class="form-group">
                                <label for="shipper_country">Country *</label>
                                <select id="shipper_country" name="shipper_country" required
                                        data-testid="shipper_country">
                                    <option value="">-- Select Country --</option>
                                    <option value="US">United States</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="CN">China</option>
                                    <option value="CA">Canada</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="shipper_address">Address</label>
                            <textarea id="shipper_address" name="shipper_address" rows="2"
                                      data-testid="shipper_address"></textarea>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>📋 Consignee / Importer</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="consignee_name">Company Name *</label>
                                <input type="text" id="consignee_name" name="consignee_name" required
                                       data-testid="consignee_name">
                            </div>
                            <div class="form-group">
                                <label for="consignee_id">Customs Registration ID *</label>
                                <input type="text" id="consignee_id" name="consignee_id" required
                                       data-testid="consignee_id">
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>📊 Goods Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="hs_code">HS/Tariff Code *</label>
                                <input type="text" id="hs_code" name="hs_code" required
                                       placeholder="e.g., 8471.30"
                                       data-testid="hs_code">
                            </div>
                            <div class="form-group">
                                <label for="country_of_origin">Country of Origin *</label>
                                <select id="country_of_origin" name="country_of_origin" required
                                        data-testid="country_of_origin">
                                    <option value="">-- Select Country --</option>
                                    <option value="US">United States</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="CN">China</option>
                                    <option value="CA">Canada</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="goods_description">Description of Goods *</label>
                            <textarea id="goods_description" name="goods_description" rows="3" required
                                      data-testid="goods_description"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="quantity">Quantity *</label>
                                <input type="number" id="quantity" name="quantity" required min="1"
                                       data-testid="quantity">
                            </div>
                            <div class="form-group">
                                <label for="gross_weight">Gross Weight (KG) *</label>
                                <input type="number" id="gross_weight" name="gross_weight" required 
                                       step="0.01" min="0"
                                       data-testid="gross_weight">
                            </div>
                            <div class="form-group">
                                <label for="total_packages">Total Packages *</label>
                                <input type="number" id="total_packages" name="total_packages" required min="1"
                                       data-testid="total_packages">
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3>💰 Values</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fob_value">FOB Value (USD) *</label>
                                <input type="number" id="fob_value" name="fob_value" required 
                                       step="0.01" min="0"
                                       data-testid="fob_value">
                            </div>
                            <div class="form-group">
                                <label for="freight_value">Freight (USD) *</label>
                                <input type="number" id="freight_value" name="freight_value" required 
                                       step="0.01" min="0"
                                       data-testid="freight_value">
                            </div>
                            <div class="form-group">
                                <label for="insurance_value">Insurance (USD)</label>
                                <input type="number" id="insurance_value" name="insurance_value" 
                                       step="0.01" min="0" value="0"
                                       data-testid="insurance_value">
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-top: 2px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between;">
                        <a href="{{ route('test.external-form.logout') }}" class="btn btn-secondary">
                            Logout
                        </a>
                        <button type="submit" class="btn btn-primary" data-testid="submit-btn">
                            Submit Declaration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</body>
</html>
