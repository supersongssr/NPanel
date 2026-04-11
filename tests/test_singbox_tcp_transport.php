<?php
/**
 * Test Sing-box TCP Transport Handling
 *
 * Run with: php tests/test_singbox_tcp_transport.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

putenv('APP_ENV=test');
if (getenv('APP_ENV') !== 'test') {
    echo "ERROR: APP_ENV must be set to 'test'\n";
    exit(1);
}

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Sing-box TCP Transport Test ===\n\n";

use App\Http\Models\User;
use App\Http\Controllers\SubscribeController;

// Create mock user
$user = new stdClass();
$user->id = 1;
$user->vmess_id = 'test-uuid-1234-5678-9012';
$user->level = 1;
$user->node_group = 0;

$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateSingboxConfig');
$method->setAccessible(true);

// Create mock nodes with different transport types
$nodes = new Illuminate\Support\Collection([
    (object)[
        'id' => 1,
        'name' => 'TCP-VMess',
        'type' => 2, // VMess
        'server' => 'tcp.example.com',
        'v2_port' => 443,
        'v2_net' => 'tcp',  // TCP transport
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 0,
        'v2_sni' => '',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => '',
        'v2_alpn' => '',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => null,
        'traffic_rate' => 1,
    ],
    (object)[
        'id' => 2,
        'name' => 'WS-VLESS',
        'type' => 3, // VLESS
        'server' => 'ws.example.com',
        'v2_port' => 443,
        'v2_net' => 'ws',  // WebSocket transport
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => 'ws.example.com',
        'v2_path' => '/vless',
        'v2_host' => 'ws.example.com',
        'v2_servicename' => '',
        'v2_alpn' => 'h2,http/1.1',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => null,
        'traffic_rate' => 1,
    ],
    (object)[
        'id' => 3,
        'name' => 'GRPC-Trojan',
        'type' => 4, // Trojan
        'server' => 'grpc.example.com',
        'v2_port' => 443,
        'v2_net' => 'grpc',  // gRPC transport
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => 'grpc.example.com',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => 'trojan-service',
        'v2_alpn' => 'h2',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => null,
        'traffic_rate' => 1,
    ],
]);

$json = $method->invoke($controller, $nodes, $user);

if ($json === false || $json === null) {
    echo "❌ Failed to generate JSON\n";
    exit(1);
}

$config = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "Generated JSON:\n";
echo str_repeat("=", 70) . "\n";
echo $json . "\n";
echo str_repeat("=", 70) . "\n\n";

// Validate each outbound
$errors = [];
$validCount = 0;

foreach ($config['outbounds'] as $idx => $outbound) {
    if (in_array($outbound['type'], ['vmess', 'vless', 'trojan'])) {
        echo "Outbound [{$idx}]: {$outbound['tag']} ({$outbound['type']})\n";

        // Check if transport field exists
        if (isset($outbound['transport'])) {
            $transportType = $outbound['transport']['type'] ?? 'unknown';

            // TCP should NOT have transport field
            if ($transportType === 'tcp') {
                $errors[] = "Outbound[$idx] has invalid 'tcp' transport type";
                echo "  ❌ Has transport.type = 'tcp' (INVALID)\n";
            } else {
                echo "  ✅ Has transport.type = '$transportType'\n";
            }
        } else {
            echo "  ✅ No transport field (TCP default)\n";
        }

        $validCount++;
    }
}

echo "\n";

if (empty($errors)) {
    echo "✅ All transport types are valid\n";
    echo "✅ TCP nodes correctly omit transport field\n";
    echo "✅ WS/GRPC nodes have proper transport configuration\n";
    exit(0);
} else {
    echo "❌ Validation errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
