<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$v2Fields = ['v2_port','v2_net','v2_type','v2_host','v2_path','v2_tls','v2_sni','v2_flow','v2_encryption','v2_servicename','v2_mode','v2_alter_id','v2_method','v2_alpn','v2_fp'];

echo "=== Type 2 (Hysteria) nodes with different configs ===" . PHP_EOL;
$type2Nodes = \App\Http\Models\SsNode::where('type', 2)
    ->where('status', 1)
    ->where('v2_port', '>', 0)
    ->get();
    
// Group by v2_net
$byNet = [];
foreach ($type2Nodes as $n) {
    $net = $n->v2_net ?? 'null';
    if (!isset($byNet[$net])) $byNet[$net] = [];
    $byNet[$net][] = $n;
}

foreach ($byNet as $net => $nodes) {
    echo "v2_net={$net} (" . count($nodes) . " nodes):" . PHP_EOL;
    foreach (array_slice($nodes, 0, 2) as $n) {
        echo "  Node {$n->id} (v2_name={$n->v2_name}):" . PHP_EOL;
        echo "    v2_net=" . json_encode($n->v2_net) . " v2_tls=" . json_encode($n->v2_tls) . " v2_alpn=" . json_encode($n->v2_alpn) . PHP_EOL;
        echo "    v2_path=" . json_encode($n->v2_path) . " v2_flow=" . json_encode($n->v2_flow) . PHP_EOL;
    }
}

echo PHP_EOL . "=== Type 3 (VMess) nodes with different v2_net ===" . PHP_EOL;
$type3Nodes = \App\Http\Models\SsNode::where('type', 3)
    ->where('status', 1)
    ->where('v2_port', '>', 0)
    ->get();

$byNet = [];
foreach ($type3Nodes as $n) {
    $net = $n->v2_net ?? 'null';
    if (!isset($byNet[$net])) $byNet[$net] = [];
    $byNet[$net][] = $n;
}

foreach ($byNet as $net => $nodes) {
    echo "v2_net={$net} (" . count($nodes) . " nodes):" . PHP_EOL;
    foreach (array_slice($nodes, 0, 2) as $n) {
        echo "  Node {$n->id} (v2_name={$n->v2_name}):" . PHP_EOL;
        echo "    v2_net=" . json_encode($n->v2_net) . " v2_tls=" . json_encode($n->v2_tls) . " v2_alpn=" . json_encode($n->v2_alpn) . PHP_EOL;
        echo "    v2_path=" . json_encode($n->v2_path) . " v2_flow=" . json_encode($n->v2_flow) . " v2_fp=" . json_encode($n->v2_fp) . PHP_EOL;
    }
}

echo PHP_EOL . "=== Type 5 (Hysteria2) nodes ===" . PHP_EOL;
$type5Nodes = \App\Http\Models\SsNode::where('type', 5)
    ->where('status', 1)
    ->where('v2_port', '>', 0)
    ->limit(5)
    ->get();

foreach ($type5Nodes as $n) {
    echo "  Node {$n->id} (v2_name={$n->v2_name}):" . PHP_EOL;
    echo "    v2_net=" . json_encode($n->v2_net) . " v2_tls=" . json_encode($n->v2_tls) . " v2_alpn=" . json_encode($n->v2_alpn) . PHP_EOL;
    echo "    v2_port=" . json_encode($n->v2_port) . " v2_host=" . json_encode($n->v2_host) . PHP_EOL;
}

echo PHP_EOL . "=== Nodes with v2_tls = 2 ===" . PHP_EOL;
$nodesTls2 = \App\Http\Models\SsNode::where('v2_tls', 2)
    ->where('status', 1)
    ->limit(5)
    ->get();

foreach ($nodesTls2 as $n) {
    echo "  Node {$n->id} (type={$n->type}, v2_name={$n->v2_name}):" . PHP_EOL;
    echo "    v2_net=" . json_encode($n->v2_net) . " v2_tls=" . json_encode($n->v2_tls) . " v2_port=" . json_encode($n->v2_port) . PHP_EOL;
}

