<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'job_post_id', 'role', 'user_name', 'user_email', 'user_phone', 'portfolio_link', 'portfolio_description', 'status'
    ];

    // Get status as a readable value
    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case 1:
                return 'Approved';
            case 2:
                return 'Rejected';
            default:
                return 'Pending';
        }
    }

    public function jobPost()
    {
        return $this->belongsTo(JobPost::class, 'job_post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
