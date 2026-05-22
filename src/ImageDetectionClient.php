<?php

/**
 * High-level client for AI Image Detection API
 */

namespace UndetectableAI\ImageDetection;

class ImageDetectionClient
{
    private ImageDetectionService $service;
    private string $baseUrl;

    /**
     * Initialize the Image Detection Client.
     * 
     * @param string $apiKey API key for authentication
     * @param string|null $baseUrl Base URL of the API server (optional, default: 'https://ai-image-detect.undetectable.ai')
     * @param int|null $timeout Default timeout for requests in seconds (optional, default: 60)
     * @param Logger|null $logger Logger instance (optional)
     */
    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        ?int $timeout = null,
        ?Logger $logger = null
    ) {
        $this->baseUrl = $baseUrl ?? 'https://ai-image-detect.undetectable.ai';
        $this->service = new ImageDetectionService(
            $this->baseUrl,
            $apiKey,
            $timeout ?? 60,
            $logger
        );
    }

    /**
     * Detect AI-generated content in an image.
     * 
     * This method handles the entire workflow:
     * 1. Presign - Get presigned URL for upload
     * 2. Upload - Upload the image file
     * 3. Detect - Submit the image for detection
     * 4. Poll - Wait for detection results
     * 
     * @param string $image File path to the image to detect
     * @param string|null $email Optional email address for processing
     * @param bool $generatePreview Optional flag to generate preview (default: false)
     * @param int $maxPollAttempts Maximum number of polling attempts (default: 60)
     * @param float $pollIntervalSeconds Seconds to sleep between polling attempts (default: 0.5)
     * @return DetectionResult Detection result
     * @throws QueryError If polling times out or other errors occur
     */
    public function detect(
        string $image,
        ?string $email = null,
        bool $generatePreview = false,
        int $maxPollAttempts = 60,
        float $pollIntervalSeconds = 0.5
    ): DetectionResult {
        // 1. Extract filename from path
        $fileName = basename($image);
        $fileName = preg_replace('/\s+/', '', $fileName);

        // 2. Presign - Get presigned URL
        $presignResult = $this->service->getPresignedURL($fileName);

        // 3. Upload - Upload the file
        $this->service->upload($presignResult->presigned_url, $image);

        // 4. Build file URL for detect operation
        // Determine CDN base URL based on the baseUrl (dev vs prod)
        $cdnBaseUrl = $this->getCdnBaseUrl();
        $filePathRemoteFull = $cdnBaseUrl . ltrim($presignResult->file_path, '/');
        $fileUrl = ImageDetectionService::buildDetectFileUrl(
            $filePathRemoteFull,
            $presignResult->presigned_url
        );

        // 5. Detect - Submit for detection
        $detectResult = $this->service->detect(
            $fileUrl,
            $email,
            $generatePreview
        );

        // 6. Poll for result - Wait for completion
        $finalResult = $this->service->pollForResult(
            $detectResult->id,
            $maxPollAttempts,
            $pollIntervalSeconds
        );

        if (!$finalResult) {
            throw new QueryError('Polling timeout - detection result not available');
        }

        // Convert QueryResponse to DetectionResult using the factory method
        return DetectionResult::fromQueryResponse($finalResult);
    }

    /**
     * Check user credits for the API key.
     * 
     * @return CreditCheckResponse Credit check response
     * @throws CreditCheckError If the credit check operation fails
     */
    public function checkUserCredits(): CreditCheckResponse
    {
        try {
            return $this->service->checkUserCredits();
        } catch (\Exception $e) {
            throw new CreditCheckError($e->getMessage());
        }
    }

    /**
     * Get the CDN base URL based on the configured baseUrl.
     * 
     * @return string CDN base URL string
     */
    private function getCdnBaseUrl(): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        if ($host === 'ai-image-detect.undetectable.ai') {
            return 'https://ai-image-detector-prod.nyc3.digitaloceanspaces.com/';
        } else {
            return 'https://ai-image-detector-dev.nyc3.digitaloceanspaces.com/';
        }
    }
}

