<?php

namespace App\Http\Controllers;

use App\Services\RedisOpinionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RedisOpinionController extends Controller
{
    private RedisOpinionService $redisOpinionService;

    public function __construct(RedisOpinionService $redisOpinionService)
    {
        $this->redisOpinionService = $redisOpinionService;
    }

    /**
     * Get paginated opinions with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type'); // dissent, concurring, majority, etc.
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 50), 100); // Max 100 per page
        $search = $request->get('search');

        if ($search) {
            $opinions = $this->redisOpinionService->searchOpinions($search, $type);
            
            // Manual pagination for search results
            $total = $opinions->count();
            $offset = ($page - 1) * $perPage;
            $paginatedOpinions = $opinions->slice($offset, $perPage)->values();
            
            $result = [
                'data' => $paginatedOpinions,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ];
        } else {
            $result = $this->redisOpinionService->getOpinionsPaginated($type, $page, $perPage);
        }

        return response()->json($result);
    }

    /**
     * Get a specific opinion
     */
    public function show(int $opinionId): JsonResponse
    {
        $opinion = $this->redisOpinionService->getOpinion($opinionId);

        if (!$opinion) {
            return response()->json(['error' => 'Opinion not found'], 404);
        }

        return response()->json($opinion);
    }

    /**
     * Get opinions by case
     */
    public function byCase(int $caseId): JsonResponse
    {
        $opinions = $this->redisOpinionService->getOpinionsByCase($caseId);
        
        return response()->json([
            'case_id' => $caseId,
            'opinions' => $opinions,
            'count' => $opinions->count()
        ]);
    }

    /**
     * Get opinions by justice
     */
    public function byJustice(int $justiceId): JsonResponse
    {
        $opinions = $this->redisOpinionService->getOpinionsByJustice($justiceId);
        
        return response()->json([
            'justice_id' => $justiceId,
            'opinions' => $opinions,
            'count' => $opinions->count()
        ]);
    }

    /**
     * Get opinions by type
     */
    public function byType(string $type): JsonResponse
    {
        $validTypes = ['dissent', 'concurring', 'majority', 'plurality', 'none'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid opinion type'], 400);
        }

        $opinions = $this->redisOpinionService->getOpinionsByType($type);
        
        return response()->json([
            'opinion_type' => $type,
            'opinions' => $opinions,
            'count' => $opinions->count()
        ]);
    }

    /**
     * Search opinions
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $type = $request->get('type');

        if (!$query) {
            return response()->json(['error' => 'Search query is required'], 400);
        }

        $opinions = $this->redisOpinionService->searchOpinions($query, $type);
        
        return response()->json([
            'query' => $query,
            'type' => $type,
            'opinions' => $opinions,
            'count' => $opinions->count()
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->redisOpinionService->getStatistics();
        
        return response()->json($stats);
    }
}