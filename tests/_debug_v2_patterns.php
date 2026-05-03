<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// For each type (2,3,4,5), show 2-3 representative nodes with all v2_* fields
$types = [2, 3, 5];
$v2Fields = ['v2_port','v2_net','v2_type','v2_host','v2_path','v2_tls','v2_sni','v2_flow','v2_encryption','v2_servicename','v2_mode','v2_alter_id','v2_method','v2_alpn','v2_fp'];

foreach ($types as $type) {
    echo "=== Type {$type} ===" . PHP_EOL;
    $nodes = \App\Http\Models\SsNode::where('type', $type)
        ->where('status', 1)
        ->where('v2_port', '>', 0)  // Only well-configured nodes
        ->limit(3)
        ->get();
    foreach ($nodes as $n) {
        echo "  Node {$n->id} (v2_name={$n->v2_name}):" . PHP_EOL;
        foreach ($v2Fields as $f) {
            echo "    {$f} = " . json_encode($n->$f) . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

// Also check: do clone nodes (is_clone > 0) with v2_name matching a single protocol
// have different v2_* configurations from their parent?
echo "=== Clone vs Parent v2_* comparison ===" . PHP_EOL;
$clones = \App\Http\Models\SsNode::where('is_clone', '>', 0)
    ->where('status', 1)
    ->where('v2_port', '>', 0)
    ->limit(5)
    ->get();
foreach ($clones as $clone) {
    $parent = \App\Http\Models\SsNode::find($clone->is_clone);
    if ($parent) {
        echo "Clone {$clone->id} (v2_name={$clone->v2_name}) vs Parent {$parent->id} (v2_name={$parent->v2_name}):" . PHP_EOL;
        foreach ($v2Fields as $f) {
            $same = $clone->$f === $parent->$f;
            echo "    {$f}: clone=" . json_encode($clone->$f) . " parent=" . json_encode($parent->$f) . ($same ? " (SAME)" : " (DIFF)") . PHP_EOL;
        }
    }
}
