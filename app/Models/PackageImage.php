<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PackageImage extends Model
{
    use HasFactory;
    
    protected $fillable = ['package_id', 'image_path'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function getImageUrlAttribute()
    {
        return Storage::url($this->image_path);
    }
}
