@extends('layouts.app')

@section('title', 'View Contact - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-user me-2"></i>{{ $tradeContact->company_name }}</h2>
                    <span class="badge bg-{{ $tradeContact->contact_type === 'shipper' ? 'info' : ($tradeContact->contact_type === 'consignee' ? 'success' : ($tradeContact->contact_type === 'broker' ? 'warning' : 'secondary')) }} fs-6">
                        {{ $tradeContact->contact_type_label }}
                    </span>
                    @if($tradeContact->is_default)
                        <span class="badge bg-success ms-2"><i class="fas fa-star me-1"></i>Default</span>
                    @endif
                </div>
                <div class="btn-group">
                    <a href="{{ route('trade-contacts.edit', $tradeContact) }}" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-2"></i>Edit
                    </a>
                    <a href="{{ route('trade-contacts.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Basic Info -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-id-card me-2"></i>Basic Information
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                <dt>Company Name</dt>
                                <dd>{{ $tradeContact->company_name }}</dd>
                                
                                @if($tradeContact->contact_name)
                                    <dt>Contact Person</dt>
                                    <dd>{{ $tradeContact->contact_name }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Communication -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-phone-alt me-2"></i>Communication
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                @if($tradeContact->phone)
                                    <dt>Phone</dt>
                                    <dd><a href="tel:{{ $tradeContact->phone }}">{{ $tradeContact->phone }}</a></dd>
                                @endif
                                
                                @if($tradeContact->fax)
                                    <dt>Fax</dt>
                                    <dd>{{ $tradeContact->fax }}</dd>
                                @endif
                                
                                @if($tradeContact->email)
                                    <dt>Email</dt>
                                    <dd><a href="mailto:{{ $tradeContact->email }}">{{ $tradeContact->email }}</a></dd>
                                @endif
                                
                                @if(!$tradeContact->phone && !$tradeContact->fax && !$tradeContact->email)
                                    <dd class="text-muted">No contact information provided</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-map-marker-alt me-2"></i>Address
                        </div>
                        <div class="card-body">
                            @if($tradeContact->full_address)
                                <address class="mb-0">
                                    @if($tradeContact->address_line_1){{ $tradeContact->address_line_1 }}<br>@endif
                                    @if($tradeContact->address_line_2){{ $tradeContact->address_line_2 }}<br>@endif
                                    @if($tradeContact->city){{ $tradeContact->city }}, @endif
                                    @if($tradeContact->state_province){{ $tradeContact->state_province }} @endif
                                    @if($tradeContact->postal_code){{ $tradeContact->postal_code }}@endif
                                    @if($tradeContact->city || $tradeContact->state_province || $tradeContact->postal_code)<br>@endif
                                    @if($tradeContact->country){{ $tradeContact->country->name }}@endif
                                </address>
                            @else
                                <p class="text-muted mb-0">No address provided</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Identifiers -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-id-badge me-2"></i>Identifiers
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                @if($tradeContact->tax_id)
                                    <dt>Tax ID / VAT</dt>
                                    <dd>{{ $tradeContact->tax_id }}</dd>
                                @endif
                                
                                @if($tradeContact->license_number)
                                    <dt>License Number</dt>
                                    <dd>{{ $tradeContact->license_number }}</dd>
                                @endif
                                
                                @if(!$tradeContact->tax_id && !$tradeContact->license_number)
                                    <dd class="text-muted">No identifiers provided</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Banking -->
                @if($tradeContact->bank_name || $tradeContact->bank_account || $tradeContact->bank_routing)
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="fas fa-university me-2"></i>Banking
                        </div>
                        <div class="card-body">
                            <dl class="mb-0">
                                @if($tradeContact->bank_name)
                                    <dt>Bank Name</dt>
                                    <dd>{{ $tradeContact->bank_name }}</dd>
                                @endif
                                
                                @if($tradeContact->bank_account)
                                    <dt>Account Number</dt>
                                    <dd>{{ $tradeContact->bank_account }}</dd>
                                @endif
                                
                                @if($tradeContact->bank_routing)
                                    <dt>Routing / SWIFT</dt>
                                    <dd>{{ $tradeContact->bank_routing }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Notes -->
                @if($tradeContact->notes)
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-sticky-note me-2"></i>Notes
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $tradeContact->notes }}</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Metadata -->
            <div class="card bg-light">
                <div class="card-body">
                    <small class="text-muted">
                        Created: {{ $tradeContact->created_at->format('M d, Y H:i') }}
                        @if($tradeContact->updated_at->ne($tradeContact->created_at))
                            | Last updated: {{ $tradeContact->updated_at->format('M d, Y H:i') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
