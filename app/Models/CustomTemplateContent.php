<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomTemplateContent extends Model
{
    protected $fillable = [
        'order_id', 'template_id', 'welcome_message', 'description', 'rsvp_date', 'personal_name', 'partner_name', 'venue_name', 'venue_address', 'wedding_date', 'wedding_time', 'city'
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
