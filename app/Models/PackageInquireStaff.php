<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageInquireStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_inquiry_id', 'photographer_application_id', 'decorator_application_id', 'catering_application_id'
    ];

    // Define relationships
    public function packageInquiry()
    {
        return $this->belongsTo(PackageInquiry::class, 'package_inquiry_id');
    }

    public function photographerApplication()
    {
        return $this->belongsTo(JobApplication::class, 'photographer_application_id');
    }

    public function decoratorApplication()
    {
        return $this->belongsTo(JobApplication::class, 'decorator_application_id');
    }

    public function cateringApplication()
    {
        return $this->belongsTo(JobApplication::class, 'catering_application_id');
    }
}
