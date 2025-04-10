<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_title', 'location', 'about', 'estate_details',
        'included_services', 'price', 'address', 'email', 'phone', 'capacity', 'cover_image', 'active_status'
    ];

    protected $casts = [
        'estate_details' => 'array',
        'included_services' => 'array',
    ];

    public function images()
    {
        return $this->hasMany(PackageImage::class);
    }

    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? Storage::url($this->cover_image) : null;
    }
}
