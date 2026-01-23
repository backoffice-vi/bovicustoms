@extends('layouts.app')

@section('title', 'Trade Contacts - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-address-book me-2"></i>Trade Contacts</h2>
            <p class="text-muted mb-0">Manage your shippers, consignees, brokers, and other trade partners</p>
        </div>
        <a href="{{ route('trade-contacts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Contact
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filter by Type -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label class="form-label mb-0">Filter by Type</label>
                </div>
                <div class="col-md-6">
                    <div class="btn-group flex-wrap" role="group">
                        <a href="{{ route('trade-contacts.index') }}" 
                           class="btn btn-sm {{ !$selectedType ? 'btn-primary' : 'btn-outline-primary' }}">
                            All
                        </a>
                        @foreach($contactTypes as $type => $label)
                            <a href="{{ route('trade-contacts.index', ['type' => $type]) }}" 
                               class="btn btn-sm {{ $selectedType === $type ? 'btn-primary' : 'btn-outline-primary' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($contacts->count() > 0)
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Phone / Email</th>
                            <th class="text-center">Default</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contacts as $contact)
                        <tr>
                            <td>
                                <strong>{{ $contact->company_name }}</strong>
                                @if($contact->tax_id)
                                    <div class="small text-muted">Tax ID: {{ $contact->tax_id }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $contact->contact_type === 'shipper' ? 'info' : ($contact->contact_type === 'consignee' ? 'success' : ($contact->contact_type === 'broker' ? 'warning' : 'secondary')) }}">
                                    {{ $contact->contact_type_label }}
                                </span>
                            </td>
                            <td>{{ $contact->contact_name ?? '-' }}</td>
                            <td>
                                @if($contact->city || $contact->country)
                                    {{ $contact->city }}{{ $contact->city && $contact->country ? ', ' : '' }}{{ $contact->country?->name }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($contact->phone)
                                    <div><i class="fas fa-phone fa-sm text-muted me-1"></i>{{ $contact->phone }}</div>
                                @endif
                                @if($contact->email)
                                    <div><i class="fas fa-envelope fa-sm text-muted me-1"></i>{{ $contact->email }}</div>
                                @endif
                                @if(!$contact->phone && !$contact->email)
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($contact->is_default)
                                    <span class="badge bg-success"><i class="fas fa-star"></i> Default</span>
                                @else
                                    <form action="{{ route('trade-contacts.toggle-default', $contact) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Set as default">
                                            <i class="far fa-star"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('trade-contacts.show', $contact) }}" class="btn btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('trade-contacts.edit', $contact) }}" class="btn btn-outline-secondary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('trade-contacts.destroy', $contact) }}" method="POST" class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this contact?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $contacts->links() }}
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-address-book fa-4x text-muted mb-3"></i>
                <h4>No Trade Contacts Yet</h4>
                <p class="text-muted mb-4">
                    Save your frequently used shippers, consignees, and brokers to speed up form filling.
                </p>
                <a href="{{ route('trade-contacts.create') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Add Your First Contact
                </a>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-shipping-fast text-info me-2"></i>Shippers
                        </h5>
                        <p class="card-text">Store exporter details including company info, address, and tax IDs.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-warehouse text-success me-2"></i>Consignees
                        </h5>
                        <p class="card-text">Save importer information with license numbers for quick form filling.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-user-tie text-warning me-2"></i>Brokers
                        </h5>
                        <p class="card-text">Keep customs broker details on file for seamless declarations.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
