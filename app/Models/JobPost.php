<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class JobPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_title', 'location', 'budget', 'role','about_job', 'responsibilities', 'requirements', 'application_deadline', 'cover_image', 'status'
    ];

    protected $casts = [
        'responsibilities' => 'array',
        'requirements' => 'array',
        'application_deadline' => 'datetime',
    ];

    public function getCoverImageUrlAttribute()
    {
        return $this->cover_image ? Storage::url($this->cover_image) : null;
    }

}
