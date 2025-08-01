<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupremeCourtCase extends Model
{
    protected $fillable = [
        'unique_hash',
        'oyez_id',
        'case_name',
        'docket_number',
        'decision_date',
        'term_id',
        'href',
        'summary',
        'facts',
        'question',
        'conclusion',
        'sentiment_score',
        'majority_opinion_author',
        'concurring_justices',
        'dissenting_justices',
        'raw_data',
    ];

    protected $casts = [
        'decision_date' => 'date',
        'facts' => 'array',
        'question' => 'array',
        'conclusion' => 'array',
        'concurring_justices' => 'array',
        'dissenting_justices' => 'array',
        'raw_data' => 'array',
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function opinions()
    {
        return $this->hasMany(Opinion::class, 'case_id');
    }

    public function majorityOpinion()
    {
        return $this->hasOne(Opinion::class, 'case_id')->where('opinion_type', 'majority');
    }

    public function concurringOpinions()
    {
        return $this->hasMany(Opinion::class, 'case_id')->where('opinion_type', 'concurrence');
    }

    public function dissentingOpinions()
    {
        return $this->hasMany(Opinion::class, 'case_id')->where('opinion_type', 'dissent');
    }
}
