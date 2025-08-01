<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalRecord extends Model
{
    protected $fillable = [
        'title',
        'description', 
        'year',
        'category',
        'region',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
