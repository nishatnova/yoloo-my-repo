<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'job_post_id', 'role', 'applicant_name', 'applicant_email', 'applicant_phone', 'portfolio_link', 'portfolio_description', 'status'
    ];

    protected $casts = [
        'status' => 'string',
    ];


    public function jobPost()
    {
        return $this->belongsTo(JobPost::class, 'job_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    
}
