<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = [
        'name',
        'year',
        'term_start',
        'term_end',
        'total_cases',
        'total_opinions',
    ];

    protected $casts = [
        'term_start' => 'date',
        'term_end' => 'date',
    ];

    public function cases()
    {
        return $this->hasMany(SupremeCourtCase::class);
    }
}
