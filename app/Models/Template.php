<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = ['name', 'title', 'price'];

    protected $hidden = ['created_at', 'updated_at'];

}
