<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $table = 'products';
    protected $fillable = ['uuid', 'company_id', 'name', 'sku', 'status', 'is_compromised'];

    // Génération automatique de l'UUID à la création
    protected static function booted()
    {
        static::creating(function ($product) {
            $product->uuid = (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class)->orderBy('created_at', 'desc');
    }

    /**
     * Helper pour vérifier si le dernier scan est cohérent avec le nouveau statut
     */
    public function updateStatus(string $newStatus)
    {
        // Logique métier : par exemple, interdire de repasser à 'created' si déjà 'in_transit'
        $this->update(['status' => $newStatus]);
    }
}

