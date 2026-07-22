<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $table = 'companies';
    protected $fillable = ['name', 'type', 'address'];

    // Un acteur (Producteur/Transporteur) possède plusieurs produits
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Les employés ou machines rattachés à cette entreprise
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

