<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperInquiry extends Model
{
    protected $fillable = [
        'name',
        'email',
        'message',
        'company',
        'portfolio_url',
        'linkedin_url',
    ];
}
