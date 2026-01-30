<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = ['customer_id', 'total', 'status','currency','payment_method_id'];

    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            $hasPayments = $order->payments()->withTrashed()->exists();

            if ($hasPayments) {
                throw ValidationException::withMessages([
                    'order' => 'Order cannot be deleted because it has associated payments.',
                ]);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

}