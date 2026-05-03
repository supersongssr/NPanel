<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$v2Fields = ['v2_port','v2_net','v2_type','v2_host','v2_path','v2_tls','v2_sni','v2_flow','v2_encryption','v2_servicename','v2_mode','v2_alter_id','v2_method','v2_alpn','v2_fp'];

echo "=== DISTINCT v2_net by type ===" . PHP_EOL;
$types = [2, 3, 4, 5];
foreach ($types as $type) {
    $nets = \App\Http\Models\SsNode::where('type', $type)
        ->where('status', 1)
        ->whereNotNull('v2_net')
        ->distinct()
        ->pluck('v2_net');
    echo "Type {$type}: " . json_encode($nets->toArray()) . PHP_EOL;
}

echo PHP_EOL . "=== DISTINCT v2_tls values by type ===" . PHP_EOL;
foreach ($types as $type) {
    $tls = \App\Http\Models\SsNode::where('type', $type)
        ->where('status', 1)
        ->distinct()
        ->pluck('v2_tls');
    echo "Type {$type}: " . json_encode($tls->toArray()) . PHP_EOL;
}

echo PHP_EOL . "=== DISTINCT v2_flow values by type ===" . PHP_EOL;
foreach ($types as $type) {
    $flows = \App\Http\Models\SsNode::where('type', $type)
        ->where('status', 1)
        ->whereNotNull('v2_flow')
        ->distinct()
        ->pluck('v2_flow');
    echo "Type {$type}: " . json_encode($flows->toArray()) . PHP_EOL;
}

echo PHP_EOL . "=== DISTINCT v2_alpn values by type ===" . PHP_EOL;
foreach ($types as $type) {
    $alpns = \App\Http\Models\SsNode::where('type', $type)
        ->where('status', 1)
        ->whereNotNull('v2_alpn')
        ->distinct()
        ->pluck('v2_alpn');
    echo "Type {$type}: " . json_encode($alpns->toArray()) . PHP_EOL;
}

echo PHP_EOL . "=== Sample nodes with unusual configurations ===" . PHP_EOL;

// Nodes with non-standard v2_net
$unusualNets = \App\Http\Models\SsNode::where('status', 1)
    ->whereNotNull('v2_net')
    ->whereNotIn('v2_net', ['tcp', 'ws', 'hysteria', 'hysteria2'])
    ->limit(3)
    ->get();
echo "Non-standard v2_net values:" . PHP_EOL;
foreach ($unusualNets as $n) {
    echo "  Node {$n->id} (type={$n->type}, v2_name={$n->v2_name}): v2_net=" . json_encode($n->v2_net) . PHP_EOL;
}

// Nodes with v2_path set
$withPath = \App\Http\Models\SsNode::where('status', 1)
    ->whereNotNull('v2_path')
    ->where('v2_path', '!=', '')
    ->limit(3)
    ->get();
echo PHP_EOL . "Nodes with v2_path set:" . PHP_EOL;
foreach ($withPath as $n) {
    echo "  Node {$n->id} (type={$n->type}, v2_name={$n->v2_name}): v2_path=" . json_encode($n->v2_path) . PHP_EOL;
}

// Nodes with v2_tls = 0
$noTls = \App\Http\Models\SsNode::where('status', 1)
    ->where('v2_tls', 0)
    ->limit(3)
    ->get();
echo PHP_EOL . "Nodes with v2_tls = 0:" . PHP_EOL;
foreach ($noTls as $n) {
    echo "  Node {$n->id} (type={$n->type}, v2_name={$n->v2_name})" . PHP_EOL;
}

// Nodes with v2_alter_id > 0
$withAlterId = \App\Http\Models\SsNode::where('status', 1)
    ->where('v2_alter_id', '>', 0)
    ->limit(3)
    ->get();
echo PHP_EOL . "Nodes with v2_alter_id > 0:" . PHP_EOL;
foreach ($withAlterId as $n) {
    echo "  Node {$n->id} (type={$n->type}, v2_name={$n->v2_name}): v2_alter_id=" . $n->v2_alter_id . PHP_EOL;
}

