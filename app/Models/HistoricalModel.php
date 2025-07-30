<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class HistoricalRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'year',
        'category',
        'subcategory',
        'region',
        'country',
        'data',
        'primary_value',
        'unit',
        'notes',
        'source',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'primary_value' => 'decimal:6',
    ];

    /**
     * Scope to filter by year range.
     *
     * @param Builder $query
     * @param int $startYear
     * @param int $endYear
     * @return Builder
     */
    public function scopeYearRange(Builder $query, int $startYear, int $endYear): Builder
    {
        return $query->whereBetween('year', [$startYear, $endYear]);
    }

    /**
     * Scope to filter by category.
     *
     * @param Builder $query
     * @param string $category
     * @return Builder
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by region.
     *
     * @param Builder $query
     * @param string $region
     * @return Builder
     */
    public function scopeRegion(Builder $query, string $region): Builder
    {
        return $query->where('region', $region);
    }

    /**
     * Get aggregated data for visualization.
     *
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public static function getVisualizationData(array $filters): \Illuminate\Support\Collection
    {
        $query = self::query();

        if (isset($filters['start_year']) && isset($filters['end_year'])) {
            $query->yearRange($filters['start_year'], $filters['end_year']);
        }

        if (isset($filters['category'])) {
            $query->category($filters['category']);
        }

        if (isset($filters['region'])) {
            $query->region($filters['region']);
        }

        return $query->orderBy('year')
            ->get()
            ->map(function ($record) {
                return [
                    'year' => $record->year,
                    'value' => $record->primary_value,
                    'category' => $record->category,
                    'region' => $record->region,
                    'metadata' => $record->data,
                ];
            });
    }
}
