<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Audit extends Model
{
    use HasFactory;

    protected $table = 'audits';

    protected $fillable = [
        'user_type',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
        'tags',
    ];

    protected $casts = [
        'old_values' => 'array', // JSON old data
        'new_values' => 'array', // JSON new data
    ];

    // Relación polimórfica al modelo auditado (SocialUser, Centro, etc.)
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relación al usuario que hizo la acción (AppUser)
    public function user(): MorphTo
    {
        return $this->morphTo('user');
    }

    // Scope para audits de una entidad específica
    public function scopeForEntity($query, string $modelClass)
    {
        return $query->where('auditable_type', $modelClass);
    }

    // Scope por event
    public function scopeByEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    // Scope por user
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
