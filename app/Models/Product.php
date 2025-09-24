<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function stock()
    {
        return $this->hasOne(Stock::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
