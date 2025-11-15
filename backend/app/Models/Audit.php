<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Crypt;

class Audit extends Model
{
    use HasFactory;

    protected $table = 'audits';
    protected $guarded = [];

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

    // Scope para desencriptar valores (solo si autorizado)
    public function scopeWithDecryptedValues($query, bool $authorized = false)
    {
        if ($authorized) {
            return $query->addSelect([
                'old_values_decrypted' => DB::raw("pgp_sym_decrypt(old_values::bytea, '" . config('app.key') . "')::text"),
                'new_values_decrypted' => DB::raw("pgp_sym_decrypt(new_values::bytea, '" . config('app.key') . "')::text"),
            ]);
        }
        return $query; // Sin desencriptar si no autorizado
    }

    // Accessors para JSON parsed (en PHP, post-query)
    public function getOldValuesDecryptedAttribute()
    {
        return $this->old_values ? json_decode(Crypt::decrypt($this->old_values), true) : null;
    }

    public function getNewValuesDecryptedAttribute()
    {
        return $this->new_values ? json_decode(Crypt::decrypt($this->new_values), true) : null;
    }


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
