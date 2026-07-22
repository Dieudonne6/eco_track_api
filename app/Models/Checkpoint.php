<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkpoint extends Model
{
    protected $table = 'checkpoints'; 
    protected $fillable = [
        'product_id', 
        'user_id', 
        'status', 
        'latitude', 
        'longitude', 
        'location_name', 
        'metadata', 
        'hash'
    ];

    // Très important : Convertit le JSON de la DB en Array PHP utilisable directement
    protected $casts = [
        'metadata' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

