<?php

/**
 * Custom Processor Example
 *
 * This example shows how to add custom request processing logic
 * by implementing your own processor.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use WpNx\Handler\Handler;
use WpNx\Handler\Configuration;
use WpNx\Handler\Processors\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom processor that adds maintenance mode functionality
 */
class MaintenanceModeProcessor implements ProcessorInterface
{
    private bool $maintenanceMode;
    private array $allowedIps;

    public function __construct(bool $maintenanceMode = false, array $allowedIps = [])
    {
        $this->maintenanceMode = $maintenanceMode;
        $this->allowedIps = $allowedIps;
    }

    public function process(Request $request, Configuration $config): Request|Response|null
    {
        // Skip if maintenance mode is off
        if (!$this->maintenanceMode) {
            return null;
        }

        // Allow certain IPs through
        $clientIp = $request->getClientIp();
        if (in_array($clientIp, $this->allowedIps, true)) {
            return null;
        }

        // Return maintenance page
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Mode</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Site Under Maintenance</h1>
    <p>We'll be back soon!</p>
</body>
</html>
HTML;

        return new Response($html, 503, [
            'Content-Type' => 'text/html',
            'Retry-After' => '3600'
        ]);
    }
}

// Configuration
$config = new Configuration([
    'web_root' => __DIR__ . '/public',
]);

// Create handler
$handler = new Handler($config);

// Add custom processor with high priority (runs early)
$handler->addProcessor(
    new MaintenanceModeProcessor(
        maintenanceMode: true,
        allowedIps: ['127.0.0.1', '::1']
    ),
    priority: 1
);

// Run the handler
$result = $handler->run();

// If a file path is returned, require it in global scope
if ($result) {
    require $result;
}
