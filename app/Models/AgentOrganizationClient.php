<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AgentOrganizationClient extends Pivot
{
    use HasFactory;

    protected $table = 'agent_organization_clients';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'agent_user_id',
        'organization_id',
        'status',
        'authorized_at',
        'revoked_at',
        'notes',
    ];

    protected $casts = [
        'authorized_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get all statuses with labels
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_REVOKED => 'Revoked',
        ];
    }

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the agent user
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    /**
     * Get the organization
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? 'Unknown';
    }

    /**
     * Get status color for badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_REVOKED => 'danger',
            default => 'secondary',
        };
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Activate the relationship
     */
    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'authorized_at' => now(),
            'revoked_at' => null,
        ]);
    }

    /**
     * Revoke the relationship
     */
    public function revoke(): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Check if relationship is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if relationship is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to get active relationships
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get pending relationships
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get relationships for a specific agent
     */
    public function scopeForAgent($query, int $agentUserId)
    {
        return $query->where('agent_user_id', $agentUserId);
    }

    /**
     * Scope to get relationships for a specific organization
     */
    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
