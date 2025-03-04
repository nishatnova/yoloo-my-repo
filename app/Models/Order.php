<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'template_id', 'package_id', 'amount', 'status', 'stripe_payment_id', 'service_booked', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array', 
        'created_at' => 'datetime', 
    ];

    public function getOrderDateAttribute()
    {
        return $this->created_at->format('Y-m-d H:i:s'); 
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
