<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'vat_number',
        'street',
        'postal_code',
        'city',
        'country',
        'billing_email',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
