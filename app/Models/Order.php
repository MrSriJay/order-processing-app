<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'quantity',
        'total',
        'status',
        'refund_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function generateRefundId()
    {
        if (!$this->refund_id) {
            $this->refund_id = Str::uuid();
            $this->save();
        }
        return $this->refund_id;
    }
}
