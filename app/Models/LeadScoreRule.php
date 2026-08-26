<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadScoreRule extends Model
{
    protected $fillable = ['org_id', 'user_id', 'rules'];

    protected $casts = [
        'rules' => 'array',
    ];
}