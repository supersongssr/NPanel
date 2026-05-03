<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== v2_name patterns and their actual protocols ===" . PHP_EOL . PHP_EOL;

// Get unique v2_name values
$v2Names = \App\Http\Models\SsNode::where('status', 1)
    ->where('v2_port', '>', 0)
    ->distinct()
    ->pluck('v2_name')
    ->sort()
    ->values();

echo "Found " . count($v2Names) . " unique v2_name values:" . PHP_EOL;
echo json_encode($v2Names->toArray()) . PHP_EOL . PHP_EOL;

// For each v2_name, show the actual protocol configurations
foreach ($v2Names as $name) {
    echo "=== v2_name = '{$name}' ===" . PHP_EOL;
    $nodes = \App\Http\Models\SsNode::where('v2_name', $name)
        ->where('status', 1)
        ->where('v2_port', '>', 0)
        ->get();
    
    // Group by type
    $byType = [];
    foreach ($nodes as $n) {
        if (!isset($byType[$n->type])) $byType[$n->type] = [];
        $byType[$n->type][] = $n;
    }
    
    foreach ($byType as $type => $typeNodes) {
        $typeNames = [2 => 'Hysteria', 3 => 'VMess', 4 => 'Trojan', 5 => 'Hysteria2'];
        echo "  Type {$type} ({$typeNames[$type]}): " . count($typeNodes) . " nodes" . PHP_EOL;
        
        // Group by protocol config
        $configs = [];
        foreach ($typeNodes as $n) {
            $key = $n->v2_net . '|' . $n->v2_tls . '|' . $n->v2_flow;
            if (!isset($configs[$key])) {
                $configs[$key] = [
                    'v2_net' => $n->v2_net,
                    'v2_tls' => $n->v2_tls,
                    'v2_flow' => $n->v2_flow,
                    'v2_path' => $n->v2_path,
                    'v2_alpn' => $n->v2_alpn,
                    'v2_fp' => $n->v2_fp,
                    'count' => 0
                ];
            }
            $configs[$key]['count']++;
        }
        
        foreach ($configs as $config) {
            echo "    [{$config['count']} nodes] " . PHP_EOL;
            echo "      v2_net={$config['v2_net']} v2_tls={$config['v2_tls']} v2_flow=" . json_encode($config['v2_flow']) . PHP_EOL;
            echo "      v2_path=" . json_encode($config['v2_path']) . " v2_alpn=" . json_encode($config['v2_alpn']) . " v2_fp=" . json_encode($config['v2_fp']) . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

// Show nodes with single-protocol v2_name
echo "=== Nodes where v2_name suggests single protocol ===" . PHP_EOL . PHP_EOL;

$singleProtocolNames = [
    'ws' => 'WebSocket only',
    'grpc' => 'gRPC only', 
    'xhttp' => 'XHTTP only',
    'hy2' => 'Hysteria2 only',
];

foreach ($singleProtocolNames as $pattern => $description) {
    $nodes = \App\Http\Models\SsNode::where('v2_name', 'like', "%{$pattern}%")
        ->where('status', 1)
        ->where('v2_port', '>', 0)
        ->limit(3)
        ->get();
    
    if ($nodes->count() > 0) {
        echo "Pattern '{$pattern}' ({$description}):" . PHP_EOL;
        foreach ($nodes as $n) {
            echo "  Node {$n->id} (v2_name={$n->v2_name}, type={$n->type}):" . PHP_EOL;
            echo "    v2_net={$n->v2_net} v2_tls={$n->v2_tls} v2_port={$n->v2_port}" . PHP_EOL;
        }
        echo PHP_EOL;
    }
}

