<?php

/**
 * Comprehensive test client for AI Image Detection API - Batch Processing
 * Similar to the Node.js test-api-client.ts implementation
 */

require_once __DIR__ . '/../src/index.php';

use Truthscan\ImageDetection\Client;
use Truthscan\ImageDetection\DefaultConsoleLogger;
use Truthscan\ImageDetection\QueryError;
use Truthscan\ImageDetection\CreditCheckError;
use Truthscan\ImageDetection\CreditCheckResponse;
use Truthscan\ImageDetection\DetectionResult;

class ProcessResult {
    public string $filename;
    public ?string $detect_id = null;
    public string $status = 'error';
    public ?string $error_message = null;
    public ?DetectionResult $query_result = null;
    public float $total_time = 0.0;
}

function logMessage(string $message): void {
    $timestamp = date('H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

function discoverImageFiles(
    string $folder,
    array $allowedExts,
    int $minBytes,
    int $maxBytes
): array {
    $files = [];

    if (!is_dir($folder)) {
        return $files;
    }

    $entries = scandir($folder);

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $entryPath = $folder . DIRECTORY_SEPARATOR . $entry;

        if (!is_file($entryPath)) {
            continue;
        }

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array('.' . $ext, $allowedExts)) {
            continue;
        }

        $fileSize = filesize($entryPath);
        if ($fileSize < $minBytes || $fileSize > $maxBytes) {
            continue;
        }

        $files[] = $entry;
    }

    return $files;
}

function processFile(
    Client $client,
    string $folder,
    string $fileName
): ProcessResult {
    logMessage("Processing file: {$fileName}");

    $fileStartTime = microtime(true);
    $fullPath = $folder . DIRECTORY_SEPARATOR . $fileName;

    $resultData = new ProcessResult();
    $resultData->filename = $fileName;

    try {
        logMessage("Starting detection for {$fileName}...");
        $finalResult = $client->detect($fullPath);

        $resultData->total_time = microtime(true) - $fileStartTime;
        $resultData->detect_id = $finalResult->id;
        $resultData->query_result = $finalResult;
        $resultData->status = $finalResult->status ?? 'unknown';

        $status = $finalResult->status;
        $resultValue = $finalResult->result ?? 'N/A';
        logMessage("Detection completed. Status: {$status}, Result: {$resultValue}");
        logMessage("Total time: " . number_format($resultData->total_time, 3) . "s");

        return $resultData;
    } catch (\Exception $e) {
        $resultData->total_time = microtime(true) - $fileStartTime;
        $resultData->error_message = $e instanceof QueryError
            ? "Query error: {$e->getMessage()}"
            : "Error: {$e->getMessage()}";
        $resultData->status = $e instanceof QueryError ? 'query_error' : 'error';
        logMessage("Error: {$resultData->error_message}");
        return $resultData;
    }
}

function printSummary(array $results): void {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  PROCESSING SUMMARY\n";
    echo str_repeat('=', 70) . "\n";

    $totalFiles = count($results);
    $successfulSubmissions = count(array_filter($results, fn($r) => $r->detect_id !== null));
    $errors = count(array_filter($results, fn($r) => $r->status === 'error'));
    $completed = count(array_filter($results, fn($r) => in_array($r->status, ['done', 'failed'])));
    $pending = count(array_filter($results, fn($r) => in_array($r->status, ['submitted', 'pending'])));

    echo "\nTotal files processed: {$totalFiles}\n";
    echo "Successful submissions: {$successfulSubmissions}\n";
    echo "Errors: {$errors}\n";
    echo "Completed (done/failed): {$completed}\n";
    echo "Pending/Submitted: {$pending}\n";

    if ($successfulSubmissions > 0) {
        $totalProcessingTime = array_sum(array_map(fn($r) => $r->total_time, $results));

        echo "\n" . str_repeat('-', 70) . "\n";
        echo "Timing Statistics:\n";
        echo "  Average total time: " . number_format($totalProcessingTime / $successfulSubmissions, 3) . "s\n";
    }

    $failedFiles = array_filter($results, fn($r) => $r->status === 'error' || $r->error_message !== null);
    if (count($failedFiles) > 0) {
        echo "\n" . str_repeat('-', 70) . "\n";
        echo "Failed Files:\n";
        foreach ($failedFiles as $result) {
            $filename = $result->filename ?? 'unknown';
            $error = $result->error_message ?? 'Unknown error';
            echo "  - {$filename}: {$error}\n";
        }
    }

    echo "\n" . str_repeat('=', 70) . "\n";
}

function main(): void {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  Image Detection Client Test Script - Batch Processing\n";
    echo str_repeat('=', 70) . "\n";

    // Configuration
    // For local testing
    // $BASE_URL = 'http://localhost:8080';

    // For development
    // $BASE_URL = 'https://ai-image-detector-dev-api-server-zo6e9.ondigitalocean.app';

    // For production
    // $BASE_URL = 'https://ai-image-detect.undetectable.ai';

    $API_KEY = getenv('API_KEY') ?: '<API KEY HERE>';
    $BASE_URL = getenv('API_BASE_URL') ?: null; // null = production default

    // Folder path
    $FOLDER = __DIR__ . '/../../../api-server/sample_images';

    // Allow folder path as command line argument
    if ($argc > 1) {
        $folderArg = $argv[1];
        if (is_dir($folderArg)) {
            $FOLDER = $folderArg;
        } elseif (is_file($folderArg)) {
            $FOLDER = dirname($folderArg);
        } else {
            echo "❌ Invalid path: {$folderArg}\n";
            exit(1);
        }
    }

    $ALLOWED_EXTS = ['.jpg', '.jpeg', '.png', '.bmp', '.tiff', '.webp', '.heic', '.heif', '.avif', '.jfif', '.tif', '.svg', '.gif'];
    $MIN_BYTES = 1 * 1024;
    $MAX_BYTES = 10 * 1024 * 1024;

    logMessage('Initializing Client...');
    $logger = new DefaultConsoleLogger('info');
    $client = new Client($API_KEY, $BASE_URL, null, $logger);

    $initialCredits = null;
    try {
        logMessage('Checking initial credits...');
        $initialCredits = $client->checkUserCredits();
        logMessage("Initial credits - Base: {$initialCredits->baseCredits}, Boost: {$initialCredits->boostCredits}, Total: {$initialCredits->credits}");
    } catch (\Exception $e) {
        if ($e instanceof CreditCheckError) {
            logMessage("Warning: Could not check initial credits: {$e->getMessage()}");
        } else {
            logMessage("Warning: Could not check initial credits: {$e->getMessage()}");
        }
    }

    logMessage("\nSearching for image files in: {$FOLDER}");
    if (!is_dir($FOLDER)) {
        echo "Folder not found: {$FOLDER}\n";
        echo "\nUsage:\n";
        echo "  php test-api-client.php [folder_path]\n";
        exit(1);
    }

    $files = discoverImageFiles($FOLDER, $ALLOWED_EXTS, $MIN_BYTES, $MAX_BYTES);

    if (count($files) === 0) {
        echo "No valid image files found in: {$FOLDER}\n";
        echo "\nAllowed extensions: " . implode(', ', $ALLOWED_EXTS) . "\n";
        echo "File size limits: {$MIN_BYTES} bytes - {$MAX_BYTES} bytes\n";
        exit(1);
    }

    logMessage("Found " . count($files) . " files to process");

    $results = [];

    foreach ($files as $fileName) {
        $result = processFile($client, $FOLDER, $fileName);
        $results[] = $result;
        echo "\n"; // Empty line between files
    }

    printSummary($results);

    $finalCredits = null;
    try {
        logMessage("\nChecking final credits...");
        $finalCredits = $client->checkUserCredits();
        logMessage("Final credits - Base: {$finalCredits->baseCredits}, Boost: {$finalCredits->boostCredits}, Total: {$finalCredits->credits}");

        if ($initialCredits && $finalCredits) {
            $creditsCharged = $initialCredits->credits - $finalCredits->credits;
            echo "\n" . str_repeat('-', 70) . "\n";
            echo "CREDIT USAGE SUMMARY\n";
            echo str_repeat('-', 70) . "\n";
            echo "Initial Credits: {$initialCredits->credits}\n";
            echo "Final Credits: {$finalCredits->credits}\n";
            echo "Credits Used: {$creditsCharged}\n";
            echo str_repeat('-', 70) . "\n";
        }
    } catch (\Exception $e) {
        if ($e instanceof CreditCheckError) {
            logMessage("Warning: Could not check final credits: {$e->getMessage()}");
        } else {
            logMessage("Warning: Could not check final credits: {$e->getMessage()}");
        }
    }

    // Convert results to array for JSON encoding
    $resultsArray = array_map(function($r) {
        return [
            'filename' => $r->filename,
            'detect_id' => $r->detect_id,
            'status' => $r->status,
            'error_message' => $r->error_message,
            'query_result' => $r->query_result ? [
                'id' => $r->query_result->id,
                'status' => $r->query_result->status,
                'result' => $r->query_result->result,
            ] : null,
            'total_time' => $r->total_time,
        ];
    }, $results);

    $outputFile = 'api_client_test_results.json';
    file_put_contents(
        $outputFile,
        json_encode($resultsArray, JSON_PRETTY_PRINT),
        LOCK_EX
    );
    logMessage("\nResults saved to: {$outputFile}");

    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  All processing completed!\n";
    echo str_repeat('=', 70) . "\n";
}

if (php_sapi_name() === 'cli') {
    try {
        main();
    } catch (\Exception $error) {
        echo "\nUnexpected error: {$error->getMessage()}\n";
        echo $error->getTraceAsString() . "\n";
        exit(1);
    }
}

