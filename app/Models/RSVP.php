<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class RSVP extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'template_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'bring_guests',
        'attendance',
        
    ];

    protected $casts = [
        'bring_guests' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
