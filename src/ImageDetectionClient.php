<?php

/**
 * High-level client for AI Image Detection API
 */

namespace Truthscan\ImageDetection;

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
        $fileName = basename($image);
        $fileName = preg_replace('/\s+/', '', $fileName);

        $presignResult = $this->service->getPresignedURL($fileName);

        $this->service->upload($presignResult->presigned_url, $image);

        $cdnBaseUrl = $this->getCdnBaseUrl();
        $filePathRemoteFull = $cdnBaseUrl . ltrim($presignResult->file_path, '/');
        $fileUrl = ImageDetectionService::buildDetectFileUrl(
            $filePathRemoteFull,
            $presignResult->presigned_url
        );

        $detectResult = $this->service->detect(
            $fileUrl,
            $email,
            $generatePreview
        );

        $finalResult = $this->service->pollForResult(
            $detectResult->id,
            $maxPollAttempts,
            $pollIntervalSeconds
        );

        if (!$finalResult) {
            throw new QueryError('Polling timeout - detection result not available');
        }

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

    private function getCdnBaseUrl(): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        if ($host === 'ai-image-detect.undetectable.ai') {
            return 'https://ai-image-detector-prod.nyc3.digitaloceanspaces.com/';
        }

        return 'https://ai-image-detector-dev.nyc3.digitaloceanspaces.com/';
    }
}
