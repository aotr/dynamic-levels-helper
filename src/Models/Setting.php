<?php

namespace Aotr\DynamicLevelHelper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use SoftDeletes;

    protected $fillable = ['key', 'group', 'display_name', 'value', 'meta', 'type', 'order'];

    protected $casts = [
        'value' => 'array',
        'meta' => 'array',
        'order' => 'integer',
    ];

    protected $table = 'settings';

    // Polymorphic relationship
    public function scope()
    {
        return $this->morphTo();
    }

    // Query scopes
    public function scopeForScope($query, $scope = null)
    {
        if (is_null($scope)) {
            return $query->whereNull('scope_type');
        }

        if (is_string($scope)) {
            [$type, $id] = explode(':', $scope);
            return $query->where('scope_type', $type)->where('scope_id', $id);
        }

        return $query
            ->where('scope_type', class_basename($scope))
            ->where('scope_id', $scope->id);
    }

    public function scopeInGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    // Type casting
    public function getCastedValue()
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'number', 'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            default => $this->value,
        };
    }
}
