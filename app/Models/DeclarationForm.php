<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclarationForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'country_id',
        'invoice_id',
        'form_number',
        'declaration_date',
        'total_duty',
        'items',
    ];

    protected $casts = [
        'declaration_date' => 'date',
        'total_duty' => 'decimal:2',
        'items' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Apply global scope to filter by tenant
        static::addGlobalScope('tenant', function ($builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->organization_id) {
                    $builder->where('organization_id', $user->organization_id);
                } else if ($user->is_individual) {
                    $builder->whereHas('invoice', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    });
                }
            }
        });
    }

    /**
     * Get the organization this form belongs to
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the country for this form
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the invoice this form is for
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
