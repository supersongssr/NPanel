<?php
/**
 * Test Clash YAML Output - Empty Node List
 *
 * This script tests what happens when there are no nodes available
 * Run with: php tests/test_clash_empty.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

putenv('APP_ENV=test');
if (getenv('APP_ENV') !== 'test') {
    echo "ERROR: APP_ENV must be set to 'test'\n";
    exit(1);
}

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Clash Empty Nodes Test ===\n\n";

class YamlTester
{
    private function isAssociativeArray($arr)
    {
        if (!is_array($arr)) return false;
        if (empty($arr)) return false; // 空数组视为索引数组
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function yamlValue($value)
    {
        if (is_bool($value)) return $value ? 'true' : 'false';
        elseif (is_null($value)) return 'null';
        elseif (is_numeric($value)) return (string)$value;
        else {
            $str = (string)$value;
            $needsQuotes = false;
            if ($str === '') $needsQuotes = true;
            elseif (in_array($str, ['true', 'false', 'null', 'y', 'n', 'yes', 'no', 'on', 'off'])) $needsQuotes = true;
            elseif (strpos($str, ':') !== false || strpos($str, '#') !== false) $needsQuotes = true;
            elseif (preg_match('/^[\s\[\]{},|]/', $str)) $needsQuotes = true;
            elseif (preg_match('/\s$/', $str)) $needsQuotes = true;

            if ($needsQuotes) return '"' . addslashes($str) . '"';
            return $str;
        }
    }

    private function convertArrayToYaml($data, $indent = 0)
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    $yaml .= $indentStr . $key . ':' . "\n";
                    $yaml .= $this->convertArrayToYaml($value, $indent + 1);
                } else {
                    if (empty($value)) {
                        $yaml .= $indentStr . $key . ": []\n";
                    } else {
                        $yaml .= $indentStr . $key . ':' . "\n";
                        foreach ($value as $item) {
                            if (is_array($item)) {
                                if ($this->isAssociativeArray($item)) {
                                    $firstKey = array_key_first($item);
                                    $firstValue = $item[$firstKey];
                                    unset($item[$firstKey]);

                                    $yaml .= $indentStr . '  - ' . $firstKey . ': ' . $this->yamlValue($firstValue) . "\n";

                                    if (!empty($item)) {
                                        $temp = [];
                                        foreach ($item as $k => $v) {
                                            if (is_array($v)) {
                                                if ($this->isAssociativeArray($v)) {
                                                    $temp[$k] = $v;
                                                } else {
                                                    $temp[$k] = $v;
                                                }
                                            } else {
                                                $temp[$k] = $v;
                                            }
                                        }
                                        if (!empty($temp)) {
                                            $yaml .= $this->convertArrayToYaml($temp, $indent + 2);
                                        }
                                    }
                                } else {
                                    $yaml .= $indentStr . '  - ' . "\n";
                                    $yaml .= $this->convertArrayToYaml($item, $indent + 2);
                                }
                            } else {
                                $yaml .= $indentStr . '  - ' . $this->yamlValue($item) . "\n";
                            }
                        }
                    }
                }
            } else {
                $yaml .= $indentStr . $key . ': ' . $this->yamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    private function toYaml($data)
    {
        return $this->convertArrayToYaml($data, 0);
    }

    public function testEmptyConfig()
    {
        // Simulating what happens when there are no nodes
        $proxies = [];
        $proxyNames = [];

        $config = [
            'mixed-port' => 7890,
            'allow-lan' => true,
            'mode' => 'rule',
            'log-level' => 'info',
            'proxies' => $proxies,
            'proxy-groups' => [
                [
                    'name' => 'Proxy',
                    'type' => 'select',
                    'proxies' => array_merge(['auto'], $proxyNames, ['DIRECT'])
                ],
                [
                    'name' => 'auto',
                    'type' => 'url-test',
                    'proxies' => $proxyNames,
                    'url' => 'http://www.gstatic.com/generate_204',
                    'interval' => 300
                ]
            ],
            'rules' => [
                'DOMAIN-SUFFIX,local,DIRECT',
                'MATCH,Proxy'
            ]
        ];

        $yaml = $this->toYaml($config);

        echo "Generated YAML (Empty Proxies):\n";
        echo str_repeat("=", 70) . "\n";
        echo $yaml;
        echo str_repeat("=", 70) . "\n\n";

        return true;
    }
}

$tester = new YamlTester();
$tester->testEmptyConfig();
echo "✅ Test completed - Check output above\n";
