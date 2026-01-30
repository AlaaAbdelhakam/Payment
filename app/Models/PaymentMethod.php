<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'code',
        'name',
        'gateway',
        'is_active',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}