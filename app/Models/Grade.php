<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_component_id',
        'raw_score',
        'raw_max',
        'weight',
        'normalized_score'
    ];
}
