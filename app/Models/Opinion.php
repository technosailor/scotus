<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opinion extends Model
{
    protected $fillable = [
        'case_id',
        'justice_id',
        'opinion_type',
        'vote',
        'opinion_text',
        'sentiment_score',
        'ideology_score',
        'seniority',
        'joining_justices',
        'oyez_href',
        'title',
        'author',
        'judge_full_name',
        'judge_last_name',
        'type',
        'href',
        'justia_opinion_url',
        'justia_opinion_id',
        'oyez_id',
    ];

    protected $casts = [
        'joining_justices' => 'array',
    ];

    public function case()
    {
        return $this->belongsTo(SupremeCourtCase::class, 'case_id');
    }

    public function justice()
    {
        return $this->belongsTo(Justice::class);
    }
}
