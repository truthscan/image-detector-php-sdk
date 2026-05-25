# How to Run the PHP Client

This guide provides step-by-step instructions to run the AI Image Detection PHP client.

## Step 1: Install Prerequisites

### Install PHP

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install php php-curl php-cli
```

**macOS (Homebrew):**
```bash
brew install php
```

**Windows:**
Download from [php.net](https://www.php.net/downloads.php) and follow the installer.

**Verify PHP installation:**
```bash
php -v
# Should show PHP 7.4 or higher

php -m | grep curl
# Should show "curl" if extension is loaded
```

### Install Composer

**Linux/macOS:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Windows:**
Download and run [Composer Windows Installer](https://getcomposer.org/Composer-Setup.exe)

**Verify Composer:**
```bash
composer --version
```

## Step 2: Set Up the Client

### Option A: Using the Client from This Repository

1. Navigate to the PHP client directory:
```bash
cd /path/to/AI-Image-Detection/clients/php
```

2. The client is ready to use! No additional installation needed.

### Option B: Using Composer (If Published)

```bash
composer require truthscan/image-detector-client
```

## Step 3: Create Your Script

Create a new PHP file (e.g., `test-client.php`):

```php
<?php

// Include the client
require_once __DIR__ . '/src/index.php';

use Truthscan\ImageDetection\Client;

// Your API key
$apiKey = 'your-api-key-here';

// Initialize the client
$client = new Client($apiKey);

// Path to your image file
$imagePath = '/path/to/your/image.jpg';

try {
    // Detect AI-generated content
    echo "Starting detection...\n";
    $result = $client->detect($imagePath);
    
    // Display results
    echo "\n=== Detection Results ===\n";
    echo "ID: {$result->id}\n";
    echo "Status: {$result->status}\n";
    echo "Result: " . ($result->result ?? 'N/A') . "\n";
    
    if ($result->result_details) {
        echo "Confidence: " . ($result->result_details->confidence ?? 'N/A') . "\n";
        echo "Final Result: " . ($result->result_details->final_result ?? 'N/A') . "\n";
    }
    
    if ($result->preview_url) {
        echo "Preview URL: {$result->preview_url}\n";
    }
    
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
```

## Step 4: Run Your Script

```bash
php test-client.php
```

## Complete Example

Here's a complete working example:

```php
<?php

require_once __DIR__ . '/src/index.php';

use Truthscan\ImageDetection\Client;
use Truthscan\ImageDetection\DefaultConsoleLogger;

// Configuration
$apiKey = 'your-api-key-here';
$imagePath = __DIR__ . '/sample-image.jpg';  // Change to your image path

// Optional: Create a logger with debug level for more output
$logger = new DefaultConsoleLogger('info');

// Initialize client
$client = new Client($apiKey, null, null, $logger);

try {
    echo "=== AI Image Detection Client ===\n\n";
    
    // Check credits first
    echo "Checking credits...\n";
    $credits = $client->checkUserCredits();
    echo "Base Credits: {$credits->baseCredits}\n";
    echo "Boost Credits: {$credits->boostCredits}\n";
    echo "Total Credits: {$credits->credits}\n\n";
    
    // Detect image
    echo "Detecting image: {$imagePath}\n";
    echo "This may take a few moments...\n\n";
    
    $result = $client->detect(
        $imagePath,
        null,        // email (optional)
        false,       // generatePreview (optional)
        60,          // maxPollAttempts (optional)
        0.5          // pollIntervalSeconds (optional)
    );
    
    // Display results
    echo "\n=== Detection Complete ===\n";
    echo "Detection ID: {$result->id}\n";
    echo "Status: {$result->status}\n";
    echo "Result: " . ($result->result ?? 'N/A') . "\n";
    
    if ($result->result_details) {
        $details = $result->result_details;
        echo "\n--- Detailed Results ---\n";
        if ($details->confidence !== null) {
            echo "Confidence: {$details->confidence}\n";
        }
        if ($details->final_result) {
            echo "Final Result: {$details->final_result}\n";
        }
        if ($details->is_valid !== null) {
            echo "Is Valid: " . ($details->is_valid ? 'Yes' : 'No') . "\n";
        }
        if ($details->heatmap_url) {
            echo "Heatmap URL: {$details->heatmap_url}\n";
        }
    }
    
    if ($result->preview_url) {
        echo "\nPreview URL: {$result->preview_url}\n";
    }
    
    echo "\n=== Done ===\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo "Error Type: " . get_class($e) . "\n";
    exit(1);
}
```

## Running with Different Options

### With Email and Preview Generation

```php
$result = $client->detect(
    '/path/to/image.jpg',
    'user@example.com',  // Email
    true,                 // Generate preview
    120,                  // Max polling attempts (2 minutes)
    1.0                   // Poll every 1 second
);
```

### With Custom Timeout

```php
$client = new Client(
    $apiKey,
    'https://ai-image-detect.undetectable.ai',  // Base URL
    180,  // 3 minute timeout
    null  // Default logger
);
```

### With Debug Logging

```php
use Truthscan\ImageDetection\DefaultConsoleLogger;

$logger = new DefaultConsoleLogger('debug');  // Shows all log messages
$client = new Client($apiKey, null, null, $logger);
```

## Testing with Sample Image

1. Place a test image in the client directory:
```bash
cp /path/to/test-image.jpg /path/to/AI-Image-Detection/clients/php/sample-image.jpg
```

2. Update the script to use the sample image:
```php
$imagePath = __DIR__ . '/sample-image.jpg';
```

3. Run the script:
```bash
php test-client.php
```

## Troubleshooting

### PHP Not Found
```bash
# Ubuntu/Debian
sudo apt install php-cli

# Verify
which php
php -v
```

### cURL Extension Not Loaded
```bash
# Ubuntu/Debian
sudo apt install php-curl

# Restart web server if using one
sudo systemctl restart apache2  # or nginx, php-fpm, etc.

# Verify
php -m | grep curl
```

### File Not Found Error
- Check the image path is correct
- Use absolute paths or paths relative to the script
- Verify file permissions: `ls -l /path/to/image.jpg`

### API Key Error
- Verify your API key is correct
- Check API key has proper permissions
- Ensure API key is not expired

### Timeout Errors
- Increase timeout in client constructor: `new Client($apiKey, null, 300)`
- Check network connectivity
- Verify API endpoint is accessible

### Memory Issues
- Increase PHP memory limit in `php.ini`: `memory_limit = 256M`
- Or run with: `php -d memory_limit=256M test-client.php`

## Quick Test Command

Test if everything is set up correctly:

```bash
cd /path/to/AI-Image-Detection/clients/php
php -r "require_once 'src/index.php'; echo 'Client loaded successfully!' . PHP_EOL;"
```

## Next Steps

- Check [test/test-api-client.php](test/test-api-client.php) for batch processing test
- Review error handling in the main project documentation for production use

