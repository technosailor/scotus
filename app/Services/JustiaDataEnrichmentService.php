<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;

class JustiaDataEnrichmentService
{
    private const RATE_LIMIT_SECONDS = 10;
    private const CACHE_TTL = 86400 * 7; // 7 days
    private const LAST_REQUEST_KEY = 'justia_api_last_request';

    public function __construct()
    {
        // Constructor - no Redis ping needed as we use Laravel Cache facade
    }

    /**
     * Fetch and parse case data from Justia URL with rate limiting and caching
     */
    public function enrichCaseData(string $justiaUrl): ?array
    {
        $cacheKey = 'justia_case_' . md5($justiaUrl);
        
        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::info("Cache hit for Justia URL: {$justiaUrl}");
            return $cached;
        }

        // Apply rate limiting
        $this->enforceRateLimit();

        try {
            Log::info("Fetching Justia case data from: {$justiaUrl}");
            
            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->get($justiaUrl);

            if (!$response->successful()) {
                Log::error("Failed to fetch Justia URL: {$justiaUrl}, Status: {$response->status()}");
                return null;
            }

            $html = $response->body();
            $enrichedData = $this->parseJustiaHtml($html);

            if ($enrichedData) {
                // Cache the result
                Cache::put($cacheKey, $enrichedData, self::CACHE_TTL);
                Log::info("Cached enriched data for: {$justiaUrl}");
            }

            return $enrichedData;

        } catch (\Exception $e) {
            Log::error("Error fetching Justia data: {$e->getMessage()}", [
                'url' => $justiaUrl,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Parse HTML content from Justia case page to extract normalized case data
     */
    private function parseJustiaHtml(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $data = [
            'facts_of_the_case' => null,
            'question' => null,
            'conclusion' => null,
            'holding' => null,
            'majority_opinion' => null,
            'dissenting_opinion' => null,
            'concurring_opinion' => null,
            'full_text' => null,
        ];

        // Extract case facts - look for common patterns
        $factsSelectors = [
            "//div[contains(@class, 'facts')]//text()",
            "//section[contains(@class, 'facts')]//text()",
            "//div[contains(text(), 'Facts') or contains(text(), 'FACTS')]//following-sibling::div//text()",
            "//h2[contains(text(), 'Facts') or contains(text(), 'FACTS')]//following-sibling::p//text()",
            "//strong[contains(text(), 'Facts')]//parent::*//following-sibling::*//text()",
        ];

        $data['facts_of_the_case'] = $this->extractContentBySelectors($xpath, $factsSelectors);

        // Extract legal question
        $questionSelectors = [
            "//div[contains(@class, 'question')]//text()",
            "//section[contains(@class, 'question')]//text()",
            "//div[contains(text(), 'Question') or contains(text(), 'QUESTION') or contains(text(), 'Issue')]//following-sibling::div//text()",
            "//h2[contains(text(), 'Question') or contains(text(), 'QUESTION') or contains(text(), 'Issue')]//following-sibling::p//text()",
            "//strong[contains(text(), 'Question') or contains(text(), 'Issue')]//parent::*//following-sibling::*//text()",
        ];

        $data['question'] = $this->extractContentBySelectors($xpath, $questionSelectors);

        // Extract conclusion/holding
        $conclusionSelectors = [
            "//div[contains(@class, 'conclusion') or contains(@class, 'holding')]//text()",
            "//section[contains(@class, 'conclusion') or contains(@class, 'holding')]//text()",
            "//div[contains(text(), 'Conclusion') or contains(text(), 'CONCLUSION') or contains(text(), 'Holding') or contains(text(), 'HOLDING')]//following-sibling::div//text()",
            "//h2[contains(text(), 'Conclusion') or contains(text(), 'CONCLUSION') or contains(text(), 'Holding') or contains(text(), 'HOLDING')]//following-sibling::p//text()",
            "//strong[contains(text(), 'Conclusion') or contains(text(), 'Holding')]//parent::*//following-sibling::*//text()",
        ];

        $data['conclusion'] = $this->extractContentBySelectors($xpath, $conclusionSelectors);

        // Extract majority opinion
        $majoritySelectors = [
            "//div[contains(@class, 'majority') or contains(@class, 'opinion')]//text()",
            "//section[contains(@class, 'majority')]//text()",
            "//div[contains(text(), 'Majority') or contains(text(), 'MAJORITY')]//following-sibling::div//text()",
            "//h2[contains(text(), 'Majority') or contains(text(), 'MAJORITY')]//following-sibling::p//text()",
        ];

        $data['majority_opinion'] = $this->extractContentBySelectors($xpath, $majoritySelectors);

        // Extract full case text as fallback
        $fullTextSelectors = [
            "//div[contains(@class, 'case-text')]//text()",
            "//div[contains(@class, 'opinion-content')]//text()",
            "//main//p//text()",
            "//article//p//text()",
        ];

        $data['full_text'] = $this->extractContentBySelectors($xpath, $fullTextSelectors);

        // Clean up and validate extracted data
        foreach ($data as $key => $value) {
            if ($value) {
                $data[$key] = $this->cleanExtractedText($value);
            }
        }

        return array_filter($data); // Remove null values
    }

    /**
     * Extract content using multiple XPath selectors
     */
    private function extractContentBySelectors(DOMXPath $xpath, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $text = '';
                foreach ($nodes as $node) {
                    $text .= trim($node->textContent) . ' ';
                }
                $cleanText = $this->cleanExtractedText($text);
                if (strlen($cleanText) > 50) { // Only return if we got substantial content
                    return $cleanText;
                }
            }
        }
        return null;
    }

    /**
     * Clean and normalize extracted text
     */
    private function cleanExtractedText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove common HTML artifacts
        $text = str_replace(['&nbsp;', '&amp;', '&lt;', '&gt;', '&#39;', '&quot;'], [' ', '&', '<', '>', "'", '"'], $text);
        
        // Trim and remove leading/trailing punctuation artifacts
        $text = trim($text, " \t\n\r\0\x0B.,;");
        
        return $text;
    }

    /**
     * Enforce rate limiting between API calls
     */
    private function enforceRateLimit(): void
    {
        $lastRequest = Cache::get(self::LAST_REQUEST_KEY);
        
        if ($lastRequest) {
            $timeSinceLastRequest = time() - (int)$lastRequest;
            
            if ($timeSinceLastRequest < self::RATE_LIMIT_SECONDS) {
                $sleepTime = self::RATE_LIMIT_SECONDS - $timeSinceLastRequest;
                Log::info("Rate limiting: sleeping for {$sleepTime} seconds");
                sleep($sleepTime);
            }
        }
        
        Cache::put(self::LAST_REQUEST_KEY, time(), 3600); // Cache for 1 hour
    }

    /**
     * Extract justia_opinion_url from case JSON data
     */
    public function extractJustiaUrl(array $caseData): ?string
    {
        if (!isset($caseData['written_opinion']) || !is_array($caseData['written_opinion'])) {
            return null;
        }

        foreach ($caseData['written_opinion'] as $opinion) {
            if (isset($opinion['title']) && 
                $opinion['title'] === 'View Case' && 
                isset($opinion['justia_opinion_url'])) {
                return $opinion['justia_opinion_url'];
            }
        }

        return null;
    }

    /**
     * Process a single JSON file to enrich case data
     */
    public function processJsonFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            Log::error("JSON file not found: {$filePath}");
            return null;
        }

        $jsonContent = file_get_contents($filePath);
        $caseData = json_decode($jsonContent, true);

        if (!$caseData) {
            Log::error("Invalid JSON in file: {$filePath}");
            return null;
        }

        // Extract decision type from the case data
        $decisionType = $this->extractDecisionType($caseData);
        
        $justiaUrl = $this->extractJustiaUrl($caseData);
        
        if (!$justiaUrl) {
            // For files without Justia URLs (like per curiam cases), still register the decision type
            if ($decisionType) {
                $caseData['decision_type_extracted'] = $decisionType;
                $caseData['enrichment_source'] = 'case_data';
                $caseData['enrichment_timestamp'] = now()->toISOString();
                Log::info("Registered decision type '{$decisionType}' for: {$filePath}");
                return $caseData;
            }
            
            Log::info("No Justia URL found in: {$filePath}");
            return null;
        }

        $enrichedData = $this->enrichCaseData($justiaUrl);
        
        if ($enrichedData) {
            // Merge enriched data with original case data
            $caseData['enriched_data'] = $enrichedData;
            $caseData['decision_type_extracted'] = $decisionType;
            $caseData['enrichment_source'] = 'justia';
            $caseData['enrichment_timestamp'] = now()->toISOString();
            
            Log::info("Successfully enriched case data for: {$filePath}");
            return $caseData;
        }

        return null;
    }

    /**
     * Extract decision type from case data
     */
    public function extractDecisionType(array $caseData): ?string
    {
        // Check in decisions array first
        if (isset($caseData['decisions']) && is_array($caseData['decisions'])) {
            foreach ($caseData['decisions'] as $decision) {
                if (isset($decision['decision_type'])) {
                    return $decision['decision_type'];
                }
            }
        }

        // Check for decision_type at root level (some files may have it there)
        if (isset($caseData['decision_type'])) {
            return $caseData['decision_type'];
        }

        return null;
    }
}