<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageInquiry extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'event_start_date', 'event_end_date', 'guests', 'event_type', 'package_id', 'status'
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
