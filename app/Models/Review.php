<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'user_id', 'package_id', 'rating', 'comment', 'status'];

    protected $casts = [
        'status' => 'string',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class); 
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
