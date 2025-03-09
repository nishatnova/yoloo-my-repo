<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageInquiry extends Model
{
    protected $fillable = [
        'user_id','package_id', 'name', 'email', 'phone', 'event_start_date', 'event_end_date', 'guests', 'event_type', 'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class); // Assuming each inquiry is linked to one order
    }
    public function packageInquireStaff()
    {
        return $this->hasOne(PackageInquireStaff::class);
    }
}
