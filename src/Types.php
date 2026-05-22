<?php

/**
 * Type definitions for AI Image Detection API Client
 */

namespace UndetectableAI\ImageDetection;

/**
 * Presign response structure
 */
class PresignResponse
{
    public string $status;
    public string $presigned_url;
    public string $file_path;
    public ?string $document_id = null;

    public function __construct(array $data)
    {
        $this->status = $data['status'] ?? '';
        $this->presigned_url = $data['presigned_url'] ?? '';
        $this->file_path = $data['file_path'] ?? '';
        $this->document_id = $data['document_id'] ?? null;
    }
}

/**
 * Detect response structure
 */
class DetectResponse
{
    public string $id;
    public string $status;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? '';
        $this->status = $data['status'] ?? '';
    }
}

/**
 * Result details structure
 */
class ResultDetails
{
    public ?bool $is_valid = null;
    public ?int $detection_step = null;
    public ?string $final_result = null;
    public ?array $metadata = null;
    public ?string $metadata_basic_source = null;
    public ?array $ocr = null;
    public ?array $ml_model = null;
    public ?float $confidence = null;
    public ?array $analysis_results = null;
    public ?string $analysis_results_status = null;
    public ?string $heatmap_url = null;
    public ?string $heatmap_status = null;

    public function __construct(array $data = [])
    {
        $this->is_valid = $data['is_valid'] ?? null;
        $this->detection_step = $data['detection_step'] ?? null;
        $this->final_result = $data['final_result'] ?? null;
        $this->metadata = $data['metadata'] ?? null;
        $this->metadata_basic_source = $data['metadata_basic_source'] ?? null;
        $this->ocr = $data['ocr'] ?? null;
        $this->ml_model = $data['ml_model'] ?? null;
        $this->confidence = $data['confidence'] ?? null;
        $this->analysis_results = $data['analysis_results'] ?? null;
        $this->analysis_results_status = $data['analysis_results_status'] ?? null;
        $this->heatmap_url = $data['heatmap_url'] ?? null;
        $this->heatmap_status = $data['heatmap_status'] ?? null;
    }
}

/**
 * Query response structure
 */
class QueryResponse
{
    public string $id;
    public string $status;
    public ?float $result = null;
    public ?ResultDetails $result_details = null;
    public ?string $preview_url = null;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? '';
        $this->status = $data['status'] ?? 'unknown';
        $this->result = $data['result'] ?? null;
        $this->preview_url = $data['preview_url'] ?? null;
        
        if (isset($data['result_details']) && is_array($data['result_details'])) {
            $this->result_details = new ResultDetails($data['result_details']);
        }
    }
}

/**
 * Credit check response structure
 */
class CreditCheckResponse
{
    public int $baseCredits;
    public int $boostCredits;
    public int $credits;

    public function __construct(array $data)
    {
        $this->baseCredits = $data['baseCredits'] ?? 0;
        $this->boostCredits = $data['boostCredits'] ?? 0;
        $this->credits = $data['credits'] ?? 0;
    }
}

/**
 * Log level type
 */
class LogLevel
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARN = 'warn';
    public const ERROR = 'error';
}

/**
 * DetectionResult type - alias for QueryResponse
 */
class DetectionResult extends QueryResponse
{
    /**
     * Create a DetectionResult from a QueryResponse instance.
     * 
     * @param QueryResponse $queryResponse The QueryResponse to convert
     * @return DetectionResult A new DetectionResult instance
     */
    public static function fromQueryResponse(QueryResponse $queryResponse): DetectionResult
    {
        $data = [
            'id' => $queryResponse->id,
            'status' => $queryResponse->status,
            'result' => $queryResponse->result,
            'preview_url' => $queryResponse->preview_url,
        ];
        
        if ($queryResponse->result_details) {
            $data['result_details'] = [
                'is_valid' => $queryResponse->result_details->is_valid,
                'detection_step' => $queryResponse->result_details->detection_step,
                'final_result' => $queryResponse->result_details->final_result,
                'metadata' => $queryResponse->result_details->metadata,
                'metadata_basic_source' => $queryResponse->result_details->metadata_basic_source,
                'ocr' => $queryResponse->result_details->ocr,
                'ml_model' => $queryResponse->result_details->ml_model,
                'confidence' => $queryResponse->result_details->confidence,
                'analysis_results' => $queryResponse->result_details->analysis_results,
                'analysis_results_status' => $queryResponse->result_details->analysis_results_status,
                'heatmap_url' => $queryResponse->result_details->heatmap_url,
                'heatmap_status' => $queryResponse->result_details->heatmap_status,
            ];
        }
        
        return new DetectionResult($data);
    }
}

