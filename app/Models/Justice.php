<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Justice extends Model
{
    protected $fillable = [
        'oyez_id',
        'identifier',
        'first_name',
        'last_name',
        'name',
        'thumbnail_url',
        'length_of_service',
        'href',
        'view_count',
        'roles',
    ];

    protected $casts = [
        'roles' => 'array',
    ];

    public function opinions()
    {
        return $this->hasMany(Opinion::class);
    }

    public function getAppointingPresidentsAttribute()
    {
        return collect($this->roles)->pluck('appointing_president')->unique()->values();
    }

    public function getMajorityOpinionsCountAttribute()
    {
        return $this->opinions()->where('opinion_type', 'majority')->count();
    }

    public function getConCurringOpinionsCountAttribute()
    {
        return $this->opinions()->where('opinion_type', 'concurrence')->count();
    }

    public function getDissentingOpinionsCountAttribute()
    {
        return $this->opinions()->where('opinion_type', 'dissent')->count();
    }
}
