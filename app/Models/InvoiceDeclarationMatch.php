<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDeclarationMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_item_id',
        'declaration_form_item_id',
        'organization_id',
        'user_id',
        'country_id',
        'confidence',
        'match_method',
        'match_reason',
    ];

    protected $casts = [
        'confidence' => 'integer',
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
                $builder->where('invoice_declaration_matches.organization_id', $user->organization_id);
            } elseif ($user->is_individual) {
                $builder->where('invoice_declaration_matches.user_id', $user->id);
            }
        });
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function declarationFormItem()
    {
        return $this->belongsTo(DeclarationFormItem::class);
    }
}

