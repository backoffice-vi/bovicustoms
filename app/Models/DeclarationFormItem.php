<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclarationFormItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'declaration_form_id',
        'invoice_id',
        'organization_id',
        'user_id',
        'country_id',
        'line_number',
        'sku',
        'item_number',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'currency',
        'hs_code',
        'hs_description',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'meta' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($builder) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();
            if ($user->organization_id) {
                // Use table-qualified column name to avoid ambiguity in joins
                $builder->where('declaration_form_items.organization_id', $user->organization_id);
            } elseif ($user->is_individual) {
                $builder->where('declaration_form_items.user_id', $user->id);
            }
        });
    }

    public function declarationForm()
    {
        return $this->belongsTo(DeclarationForm::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

