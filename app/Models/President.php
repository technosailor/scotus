<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class President extends Model
{
    protected $fillable = [
        'name',
        'presidency_number',
        'term_start',
        'term_end',
        'party',
    ];

    protected $casts = [
        'term_start' => 'date',
        'term_end' => 'date',
    ];

    public function getAppointedJusticesAttribute()
    {
        return Justice::whereJsonContains('roles', [['appointing_president' => $this->name]])->get();
    }
}
