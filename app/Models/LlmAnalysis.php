<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmAnalysis extends Model
{
    protected $fillable = [
        'query_hash',
        'query',
        'parameters',
        'response',
        'model',
        'tokens_used',
    ];

    protected $casts = [
        'parameters' => 'array',
    ];
}
