<?php

namespace Aotr\DynamicLevelHelper\Models;

use Illuminate\Database\Eloquent\Model;

class SettingAuditLog extends Model
{
    protected $table = 'setting_audit_logs';

    protected $fillable = [
        'key',
        'group',
        'old_value',
        'new_value',
        'auditable_type',
        'auditable_id',
        'action',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    // Polymorphic relationship (who made the change)
    public function auditable()
    {
        return $this->morphTo();
    }

    // Query scopes
    public function scopeForKey($query, $key)
    {
        return $query->where('key', $key);
    }

    public function scopeInGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByUser($query, $userId)
    {
        return $query
            ->where('auditable_type', 'User')
            ->where('auditable_id', $userId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeFromDate($query, $date)
    {
        return $query->whereDate('created_at', '>=', $date);
    }

    public function scopeToDate($query, $date)
    {
        return $query->whereDate('created_at', '<=', $date);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // Accessors
    public function getChangesSummaryAttribute()
    {
        if ($this->action === 'deleted') {
            return 'Deleted';
        }

        if ($this->action === 'created') {
            return 'Created';
        }

        // For updates, show what changed
        if ($this->old_value === $this->new_value) {
            return 'No changes';
        }

        return "Changed from " . json_encode($this->old_value) . " to " . json_encode($this->new_value);
    }
}
