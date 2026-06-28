<?php

/**
 * Low-level service for interacting with AI Image Detection API
 */

namespace Truthscan\ImageDetection;

class ImageDetectionService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private Logger $logger;

    /**
     * Initialize the API client interface.
     * 
     * @param string $baseUrl Base URL of the API server
     * @param string $apiKey API key for authentication
     * @param int $timeout Default timeout for requests in seconds (default: 60)
     * @param Logger|null $logger Logger instance (optional)
     */
    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 60,
        ?Logger $logger = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout * 1000; // Convert to milliseconds
        $this->logger = $logger ?? new DefaultConsoleLogger('info');
    }

    /**
     * Guess MIME type from file name.
     * 
     * @param string $fileName Name of the file
     * @return string MIME type string
     */
    public static function guessMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jfif' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];

        return $mimeMap[$extension] ?? 'application/octet-stream';
    }

    /**
     * Create a cURL request with timeout
     */
    private function makeRequest(
        string $url,
        array $options = [],
        int $timeoutMs = null
    ): array {
        $timeoutMs = $timeoutMs ?? $this->timeout;
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min(10000, $timeoutMs), // 10 seconds max for connection
        ]);

        // Apply custom options
        foreach ($options as $key => $value) {
            curl_setopt($ch, $key, $value);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: {$error}");
        }

        return [
            'status' => $httpCode,
            'body' => $response,
        ];
    }

    /**
     * Get a presigned URL for file upload.
     * 
     * @param string $fileName Name of the file to upload
     * @return PresignResponse Presign response
     * @throws PresignError If the presign operation fails
     */
    public function getPresignedURL(string $fileName): PresignResponse
    {
        $this->logger->info("Requesting presigned URL for file: {$fileName}");

        try {
            $nonce = (int) round(microtime(true) * 1000);
            $url = $this->baseUrl . '/get-presigned-url?file_name=' . urlencode($fileName) . '&_t=' . $nonce;

            $response = $this->makeRequest($url, [
                CURLOPT_HTTPHEADER => [
                    "apikey: {$this->apiKey}",
                    'Cache-Control: no-cache, no-store',
                    'Pragma: no-cache',
                ],
            ]);

            $this->logger->debug("Presign response status: {$response['status']}");

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $errorMsg = "Presign failed with status {$response['status']}";
                try {
                    $errorData = json_decode($response['body'], true);
                    if (isset($errorData['error'])) {
                        $errorMsg = $errorData['error'];
                    }
                } catch (\Exception $e) {
                    $errorMsg .= ": {$response['body']}";
                }
                $this->logger->error($errorMsg);
                throw new PresignError($errorMsg);
            }

            $result = json_decode($response['body'], true);
            
            if (!isset($result['presigned_url']) || !isset($result['file_path'])) {
                $errorMsg = 'Invalid presign response: missing required fields';
                $this->logger->error($errorMsg);
                throw new PresignError($errorMsg);
            }

            $this->logger->info("Presigned URL obtained successfully for {$fileName}");
            return new PresignResponse($result);
        } catch (PresignError $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() 
                ? "Network error during presign: {$e->getMessage()}"
                : "Unexpected error during presign: " . get_class($e);
            $this->logger->error($errorMsg);
            throw new PresignError($errorMsg);
        }
    }

    /**
     * Upload a file to the presigned URL.
     * 
     * @param string $presignedUrl Presigned URL obtained from getPresignedURL() method
     * @param string $filePath Local path to the file to upload
     * @param string|null $mimeType MIME type of the file (auto-detected if not provided)
     * @return bool True if upload was successful
     * @throws UploadError If the upload operation fails
     */
    public function upload(string $presignedUrl, string $filePath, ?string $mimeType = null): bool
    {
        if (!file_exists($filePath)) {
            $errorMsg = "File not found: {$filePath}";
            $this->logger->error($errorMsg);
            throw new UploadError($errorMsg);
        }

        if (!$mimeType) {
            $mimeType = self::guessMimeType($filePath);
        }

        $this->logger->info("Uploading file {$filePath} (MIME type: {$mimeType})");

        try {
            $fileContent = file_get_contents($filePath);
            
            if ($fileContent === false) {
                throw new UploadError("Could not read file: {$filePath}");
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $presignedUrl,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $fileContent,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: {$mimeType}",
                    "x-amz-acl: private",
                ],
                CURLOPT_TIMEOUT_MS => 60 * 1000 * 5, // 5 minutes
                CURLOPT_CONNECTTIMEOUT_MS => 10000,
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);

            if ($error) {
                throw new \Exception("cURL error: {$error}");
            }

            $response = [
                'status' => $httpCode,
                'body' => $responseBody,
            ];

            $this->logger->debug("Upload response status: {$response['status']}");
            if ($responseBody) {
                $this->logger->debug("Upload response body: {$responseBody}");
            }

            if ($response['status'] !== 200 && $response['status'] !== 204) {
                $errorMsg = "Upload failed with status {$response['status']}";
                if ($responseBody) {
                    $errorMsg .= " - Response: {$responseBody}";
                }
                $this->logger->error($errorMsg);
                throw new UploadError($errorMsg);
            }

            $this->logger->info("File uploaded successfully: {$filePath}");
            return true;
        } catch (UploadError $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() 
                ? "Network error during upload: {$e->getMessage()}"
                : "Unexpected error during upload: " . get_class($e);
            $this->logger->error($errorMsg);
            throw new UploadError($errorMsg);
        }
    }

    /**
     * Submit an image for AI detection.
     * 
     * @param string $fileUrl URL of the uploaded file
     * @param string|null $email Optional email address for processing
     * @param bool $generatePreview Optional flag to generate preview (default: false)
     * @return DetectResponse Detect response
     * @throws DetectError If the detect operation fails
     */
    public function detect(string $fileUrl, ?string $email = null, bool $generatePreview = false): DetectResponse
    {
        $this->logger->info("Submitting detect request for URL: {$fileUrl}");

        $payload = [
            'key' => $this->apiKey,
            'url' => $fileUrl,
            'document_type' => 'Image',
            'model' => 'generic',
            'generate_preview' => $generatePreview,
        ];

        if ($email) {
            $payload['email'] = $email;
        }

        try {
            $response = $this->makeRequest(
                $this->baseUrl . '/detect',
                [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                    ],
                ],
                60 * 1000 * 2 // 2 minutes for detect requests
            );

            $this->logger->debug("Detect response status: {$response['status']}");

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $errorMsg = "Detect failed with status {$response['status']}";
                try {
                    $errorData = json_decode($response['body'], true);
                    if (isset($errorData['error'])) {
                        $errorMsg = $errorData['error'];
                    }
                } catch (\Exception $e) {
                    $errorMsg .= ": {$response['body']}";
                }
                $this->logger->error($errorMsg);
                throw new DetectError($errorMsg);
            }

            $result = json_decode($response['body'], true);

            if (!isset($result['id'])) {
                $errorMsg = 'Invalid detect response: missing detection ID';
                $this->logger->error($errorMsg);
                throw new DetectError($errorMsg);
            }

            $detectId = $result['id'];
            $this->logger->info("Detect request submitted successfully. ID: {$detectId}");
            return new DetectResponse($result);
        } catch (DetectError $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() 
                ? "Network error during detect: {$e->getMessage()}"
                : "Unexpected error during detect: " . get_class($e);
            $this->logger->error($errorMsg);
            throw new DetectError($errorMsg);
        }
    }

    /**
     * Query the detection status and results.
     * 
     * @param string $detectId Detection ID returned from detect() method
     * @return QueryResponse Query response
     * @throws QueryError If the query operation fails
     */
    public function query(string $detectId): QueryResponse
    {
        $this->logger->debug("Querying detection status for ID: {$detectId}");

        try {
            $response = $this->makeRequest(
                $this->baseUrl . '/query',
                [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['id' => $detectId]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                    ],
                ]
            );

            $this->logger->debug("Query response status: {$response['status']}");

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $errorMsg = "Query failed with status {$response['status']}";
                try {
                    $errorData = json_decode($response['body'], true);
                    if (isset($errorData['error'])) {
                        $errorMsg = $errorData['error'];
                    }
                } catch (\Exception $e) {
                    $errorMsg .= ": {$response['body']}";
                }
                $this->logger->error($errorMsg);
                throw new QueryError($errorMsg);
            }

            $responseText = trim($response['body']);
            if ($responseText === 'null' || empty($responseText)) {
                $this->logger->warn("Document not found for ID: {$detectId}");
                return new QueryResponse(['id' => $detectId, 'status' => 'not_found']);
            }

            $result = json_decode($responseText, true);

            if (!$result || $result === null) {
                $this->logger->warn("Document not found for ID: {$detectId}");
                return new QueryResponse(['id' => $detectId, 'status' => 'not_found']);
            }

            $status = $result['status'] ?? 'unknown';
            $this->logger->debug("Query result status: {$status}");
            return new QueryResponse($result);
        } catch (QueryError $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() 
                ? "Network error during query: {$e->getMessage()}"
                : "Unexpected error during query: " . get_class($e);
            $this->logger->error($errorMsg);
            throw new QueryError($errorMsg);
        }
    }

    /**
     * Poll for detection results until completion.
     * 
     * Continues polling until status is "done" or "failed"
     * or until maxAttempts is reached.
     * 
     * @param string $detectId Detection ID returned from detect() method
     * @param int $maxAttempts Maximum number of polling attempts (default: 60)
     * @param float $sleepSeconds Seconds to sleep between attempts (default: 0.5)
     * @param callable|null $callback Optional callback function called on each attempt
     * @return QueryResponse|null Final result or null if timeout
     */
    public function pollForResult(
        string $detectId,
        int $maxAttempts = 60,
        float $sleepSeconds = 0.5,
        ?callable $callback = null
    ): ?QueryResponse {
        $this->logger->info("Starting to poll for detection ID: {$detectId}");

        $finalResult = null;

        for ($attemptIdx = 1; $attemptIdx <= $maxAttempts; $attemptIdx++) {
            try {
                $result = $this->query($detectId);

                if ($callback !== null) {
                    try {
                        $callback($attemptIdx, $result);
                    } catch (\Exception $e) {
                        $this->logger->warn("Callback error: {$e->getMessage()}");
                    }
                }

                $status = $result->status ?? 'unknown';

                if ($status === 'done' || $status === 'failed') {
                    $finalResult = $result;
                    $this->logger->info(
                        "Polling completed. Status: {$status} (attempt {$attemptIdx}/{$maxAttempts})"
                    );
                    break;
                }

                if ($attemptIdx < $maxAttempts) {
                    usleep((int)($sleepSeconds * 1000000)); // Convert seconds to microseconds
                }
            } catch (QueryError $e) {
                $this->logger->warn("Query error on attempt {$attemptIdx}: {$e->getMessage()}");
                usleep((int)($sleepSeconds * 1000000));
                continue;
            } catch (\Exception $e) {
                $this->logger->warn(
                    "Unexpected error on attempt {$attemptIdx}: {$e->getMessage()}"
                );
                usleep((int)($sleepSeconds * 1000000));
                continue;
            }
        }

        if (!$finalResult) {
            $this->logger->warn(
                "Polling timeout reached for detection ID: {$detectId} (max attempts: {$maxAttempts})"
            );
        }

        return $finalResult;
    }

    /**
     * Check user credits for the API key.
     * 
     * @return CreditCheckResponse Credit check response
     * @throws CreditCheckError If the credit check operation fails
     */
    public function checkUserCredits(): CreditCheckResponse
    {
        // Redact API key for security - only show first 4 and last 4 characters
        $apiKeyMasked = strlen($this->apiKey) > 8 
            ? substr($this->apiKey, 0, 4) . str_repeat('*', strlen($this->apiKey) - 8) . substr($this->apiKey, -4)
            : str_repeat('*', strlen($this->apiKey));
        $this->logger->info("Requesting credit check for API key: {$apiKeyMasked}");

        try {
            $response = $this->makeRequest(
                $this->baseUrl . '/check-user-credits',
                [
                    CURLOPT_HTTPHEADER => [
                        "apikey: {$this->apiKey}",
                    ],
                ]
            );

            $this->logger->debug("Credit check response status: {$response['status']}");

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $errorMsg = "Credit check failed with status {$response['status']}";
                try {
                    $errorData = json_decode($response['body'], true);
                    if (isset($errorData['error'])) {
                        $errorMsg = $errorData['error'];
                    }
                } catch (\Exception $e) {
                    $errorMsg .= ": {$response['body']}";
                }
                $this->logger->error($errorMsg);
                throw new CreditCheckError($errorMsg);
            }

            $result = json_decode($response['body'], true);

            // Check if response has required fields
            if (!is_array($result)) {
                $errorMsg = "Invalid credit check response: unexpected response format. Response: " . json_encode($result);
                $this->logger->error($errorMsg);
                throw new CreditCheckError($errorMsg);
            }

            // Check for required fields (allow 0 values but not undefined/null)
            if (!isset($result['baseCredits']) || !isset($result['boostCredits']) || !isset($result['credits'])) {
                $errorMsg = "Invalid credit check response: missing required fields. Response: " . json_encode($result);
                $this->logger->error($errorMsg);
                throw new CreditCheckError($errorMsg);
            }

            $this->logger->info("Credit check obtained successfully");
            return new CreditCheckResponse($result);
        } catch (CreditCheckError $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage() 
                ? "Network error during credit check: {$e->getMessage()}"
                : "Unexpected error during credit check: " . get_class($e);
            $this->logger->error($errorMsg);
            throw new CreditCheckError($errorMsg);
        }
    }

    /**
     * Build the full file URL for detect operation.
     * 
     * If filePathRemote is already a full URL, returns it as-is.
     * Otherwise, constructs URL from presigned URL origin.
     * 
     * @param string $filePathRemote Remote file path or full URL
     * @param string $presignedUrl Presigned URL to extract origin from
     * @return string Full URL to the file
     */
    public static function buildDetectFileUrl(string $filePathRemote, string $presignedUrl): string
    {
        if (strpos($filePathRemote, 'http://') === 0 || strpos($filePathRemote, 'https://') === 0) {
            return $filePathRemote;
        }

        $urlParts = parse_url($presignedUrl);
        $origin = $urlParts['scheme'] . '://' . $urlParts['host'];
        $filePath = ltrim($filePathRemote, '/');
        return "{$origin}/{$filePath}";
    }
}

