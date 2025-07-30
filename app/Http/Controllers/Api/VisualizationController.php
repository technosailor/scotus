<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HistoricalRecord;
use App\Services\LlmAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VisualizationController extends Controller
{
    /**
     * @var LlmAnalysisService
     */
    private LlmAnalysisService $llmService;

    /**
     * Constructor.
     *
     * @param LlmAnalysisService $llmService
     */
    public function __construct(LlmAnalysisService $llmService)
    {
        $this->llmService = $llmService;
    }

    /**
     * Get data for visualization.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_year' => 'nullable|integer|min:1824|max:2024',
            'end_year' => 'nullable|integer|min:1824|max:2024',
            'category' => 'nullable|string',
            'region' => 'nullable|string',
            'type' => 'nullable|string|in:line,bar,scatter,heatmap,tree',
            'aggregate' => 'nullable|string|in:year,decade,category,region',
        ]);

        $data = HistoricalRecord::getVisualizationData($validated);

        // Transform data based on visualization type
        $transformed = $this->transformDataForVisualization($data, $validated['type'] ?? 'line');

        return response()->json([
            'data' => $transformed,
            'metadata' => [
                'total_records' => $data->count(),
                'start_year' => $data->min('year'),
                'end_year' => $data->max('year'),
                'categories' => $data->pluck('category')->unique()->values(),
                'regions' => $data->pluck('region')->unique()->values(),
            ],
        ]);
    }

    /**
     * Get AI insights for the data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getInsights(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_year' => 'nullable|integer|min:1824|max:2024',
            'end_year' => 'nullable|integer|min:1824|max:2024',
            'category' => 'nullable|string',
            'region' => 'nullable|string',
            'question' => 'nullable|string|max:500',
        ]);

        $insights = $this->llmService->generateInsights($validated);

        return response()->json($insights);
    }

    /**
     * Get available categories and regions.
     *
     * @return JsonResponse
     */
    public function getFilters(): JsonResponse
    {
        $categories = HistoricalRecord::distinct('category')->pluck('category');
        $regions = HistoricalRecord::distinct('region')->pluck('region');
        $years = HistoricalRecord::selectRaw('MIN(year) as min_year, MAX(year) as max_year')->first();

        return response()->json([
            'categories' => $categories,
            'regions' => $regions,
            'year_range' => [
                'min' => $years->min_year,
                'max' => $years->max_year,
            ],
        ]);
    }

    /**
     * Transform data for specific visualization types.
     *
     * @param \Illuminate\Support\Collection $data
     * @param string $type
     * @return array
     */
    private function transformDataForVisualization($data, string $type): array
    {
        switch ($type) {
            case 'line':
                return $this->transformForLineChart($data);

            case 'bar':
                return $this->transformForBarChart($data);

            case 'scatter':
                return $this->transformForScatterPlot($data);

            case 'heatmap':
                return $this->transformForHeatmap($data);

            case 'tree':
                return $this->transformForTreeMap($data);

            default:
                return $data->toArray();
        }
    }

    /**
     * Transform data for line chart.
     *
     * @param \Illuminate\Support\Collection $data
     * @return array
     */
    private function transformForLineChart($data): array
    {
        return $data->groupBy('category')->map(function ($categoryData, $category) {
            return [
                'id' => $category,
                'data' => $categoryData->map(function ($record) {
                    return [
                        'x' => $record['year'],
                        'y' => $record['value'],
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Transform data for bar chart.
     *
     * @param \Illuminate\Support\Collection $data
     * @return array
     */
    private function transformForBarChart($data): array
    {
        return $data->groupBy('year')->map(function ($yearData, $year) {
            $categories = $yearData->pluck('value', 'category');
            return array_merge(['year' => $year], $categories->toArray());
        })->values()->toArray();
    }

    /**
     * Transform data for scatter plot.
     *
     * @param \Illuminate\Support\Collection $data
     * @return array
     */
    private function transformForScatterPlot($data): array
    {
        return $data->groupBy('category')->map(function ($categoryData, $category) {
            return [
                'id' => $category,
                'data' => $categoryData->map(function ($record) {
                    return [
                        'x' => $record['year'],
                        'y' => $record['value'],
                        'region' => $record['region'],
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Transform data for heatmap.
     *
     * @param \Illuminate\Support\Collection $data
     * @return array
     */
    private function transformForHeatmap($data): array
    {
        $years = $data->pluck('year')->unique()->sort()->values();
        $categories = $data->pluck('category')->unique()->sort()->values();

        $matrix = [];
        foreach ($categories as $catIndex => $category) {
            foreach ($years as $yearIndex => $year) {
                $record = $data->where('year', $year)->where('category', $category)->first();
                $matrix[] = [
                    'x' => $year,
                    'y' => $category,
                    'value' => $record ? $record['value'] : 0,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Transform data for tree map.
     *
     * @param \Illuminate\Support\Collection $data
     * @return array
     */
    private function transformForTreeMap($data): array
    {
        $grouped = $data->groupBy('category')->map(function ($categoryData, $category) {
            return [
                'name' => $category,
                'children' => $categoryData->groupBy('region')->map(function ($regionData, $region) {
                    return [
                        'name' => $region,
                        'value' => $regionData->sum('value'),
                    ];
                })->values()->toArray(),
            ];
        });

        return [
            'name' => 'root',
            'children' => $grouped->values()->toArray(),
        ];
    }
}
