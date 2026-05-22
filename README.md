# TruthScan Image Detection — PHP Client

PHP client for the [TruthScan AI Image Detection API](https://truthscan.com/truthscan-ai-image-detection-api-documentation).

## Requirements

- PHP 7.4+ with the `curl` extension
- A TruthScan API key ([get one here](https://truthscan.com))

## Installation

```bash
composer require truthscan/image-detector-client
```

## Quick start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use UndetectableAI\ImageDetection\ImageDetectionClient;

$apiKey = getenv('TRUTHSCAN_API_KEY') ?: 'your_api_key_here';
$client = new ImageDetectionClient($apiKey);

$result = $client->detect('/path/to/image.jpg');

echo "Status: {$result->status}\n";
echo "Score: " . ($result->result ?? 'N/A') . "\n";
echo "Final: " . ($result->result_details->final_result ?? '') . "\n";
```

`detect()` runs presign → upload → detect → poll until complete.

## License

MIT
