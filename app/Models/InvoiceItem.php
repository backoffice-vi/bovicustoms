<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
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
        'customs_code',
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
                $builder->where('organization_id', $user->organization_id);
            } elseif ($user->is_individual) {
                $builder->where('user_id', $user->id);
            }
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

