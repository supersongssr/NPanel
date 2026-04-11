<?php
/**
 * Test Clash YAML Output - Realistic Proxy Config
 *
 * This script tests YAML generation with realistic proxy configurations
 * Run with: php tests/test_clash_realistic.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment
putenv('APP_ENV=test');
if (getenv('APP_ENV') !== 'test') {
    echo "ERROR: APP_ENV must be set to 'test'\n";
    exit(1);
}

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Clash Realistic YAML Test ===\n\n";

// Mock controller to test YAML generation
class YamlTester
{
    private function isAssociativeArray($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        if (empty($arr)) {
            return false; // 空数组视为索引数组，这样会输出为 []
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function yamlValue($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_numeric($value)) {
            return (string)$value;
        } else {
            $str = (string)$value;
            $needsQuotes = false;
            if ($str === '') {
                $needsQuotes = true;
            } elseif (in_array($str, ['true', 'false', 'null', 'y', 'n', 'yes', 'no', 'on', 'off'])) {
                $needsQuotes = true;
            } elseif (strpos($str, ':') !== false || strpos($str, '#') !== false) {
                $needsQuotes = true;
            } elseif (preg_match('/^[\s\[\]{},|]/', $str)) {
                $needsQuotes = true;
            } elseif (preg_match('/\s$/', $str)) {
                $needsQuotes = true;
            }

            if ($needsQuotes) {
                return '"' . addslashes($str) . '"';
            }
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
                    // 关联数组（对象）
                    $yaml .= $indentStr . $key . ':' . "\n";
                    $yaml .= $this->convertArrayToYaml($value, $indent + 1);
                } else {
                    // 索引数组（列表）
                    if (empty($value)) {
                        $yaml .= $indentStr . $key . ": []\n";
                    } else {
                        $yaml .= $indentStr . $key . ':' . "\n";
                        foreach ($value as $item) {
                            if (is_array($item)) {
                                if ($this->isAssociativeArray($item)) {
                                    // 嵌套对象：第一个属性写在同一行
                                    $firstKey = array_key_first($item);
                                    $firstValue = $item[$firstKey];
                                    unset($item[$firstKey]);

                                    $yaml .= $indentStr . '  - ' . $firstKey . ': ' . $this->yamlValue($firstValue) . "\n";

                                    // 处理剩余属性
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
                                    // 嵌套数组 - 简单处理
                                    $yaml .= $indentStr . '  - ' . "\n";
                                    $yaml .= $this->convertArrayToYaml($item, $indent + 2);
                                }
                            } else {
                                // 简单值
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

    public function testRealisticConfig()
    {
        // Realistic Clash config with nested ws-opts and headers
        $config = [
            'mixed-port' => 7890,
            'allow-lan' => true,
            'mode' => 'rule',
            'log-level' => 'info',
            'proxies' => [
                [
                    'name' => 'HK-Node01',
                    'type' => 'vmess',
                    'server' => 'hk.example.com',
                    'port' => 443,
                    'uuid' => '12345678-1234-1234-1234-123456789abc',
                    'alterId' => 0,
                    'cipher' => 'auto',
                    'network' => 'ws',
                    'ws-opts' => [
                        'path' => '/vmess',
                        'headers' => [
                            'Host' => ['hk.example.com']
                        ]
                    ],
                    'tls' => true,
                    'servername' => 'hk.example.com',
                    'alpn' => ['h2', 'http/1.1']
                ],
                [
                    'name' => 'US-Node02',
                    'type' => 'vless',
                    'server' => 'us.example.com',
                    'port' => 443,
                    'uuid' => '87654321-4321-4321-4321-cba987654321',
                    'network' => 'ws',
                    'ws-opts' => [
                        'path' => '/vless',
                        'headers' => [
                            'Host' => ['us.example.com']
                        ]
                    ],
                    'tls' => true,
                    'flow' => 'xtls-rprx-vision'
                ]
            ],
            'proxy-groups' => [
                [
                    'name' => 'Proxy',
                    'type' => 'select',
                    'proxies' => ['HK-Node01', 'US-Node02', 'DIRECT'],
                ],
                [
                    'name' => 'Auto',
                    'type' => 'url-test',
                    'proxies' => ['HK-Node01', 'US-Node02'],
                    'url' => 'http://www.gstatic.com/generate_204',
                    'interval' => 300
                ]
            ],
            'rules' => [
                'DOMAIN-SUFFIX,local,DIRECT',
                'IP-CIDR,127.0.0.0/8,DIRECT',
                'GEOIP,CN,DIRECT',
                'MATCH,Proxy',
            ],
        ];

        $yaml = $this->toYaml($config);

        echo "Generated YAML:\n";
        echo str_repeat("=", 70) . "\n";
        echo $yaml;
        echo str_repeat("=", 70) . "\n\n";

        // Write to file for manual inspection
        file_put_contents('/tmp/test_clash_output.yaml', $yaml);
        echo "YAML saved to /tmp/test_clash_output.yaml\n\n";

        // Validation checks
        $errors = [];
        $lines = explode("\n", $yaml);

        foreach ($lines as $lineNum => $line) {
            $lineNum++; // 1-indexed for error messages

            // Check for proper indentation
            if (strlen($line) > 0 && !ctype_space($line)) {
                $trimmed = ltrim($line);
                $indent = strlen($line) - strlen($trimmed);
                if ($indent % 2 !== 0) {
                    $errors[] = "Line $lineNum: Invalid indentation (not multiple of 2): '$line'";
                }
            }

            // Check for malformed list items
            if (preg_match('/^\s+-\s*$/', $line)) {
                // Hyphen with nothing after it - might be ok if next lines have content
                if (!isset($lines[$lineNum]) || trim($lines[$lineNum] ?? '') === '') {
                    $errors[] = "Line $lineNum: Dangling hyphen with no content";
                }
            }

            // Check for space after hyphen
            if (preg_match('/^\s+-[^\s]/', $line)) {
                $errors[] = "Line $lineNum: Missing space after hyphen: '$line'";
            }
        }

        // Check for invalid characters in unquoted strings
        if (preg_match_all('/^([\s]*)([\w\-]+):\s+([^\s"].*?)$/m', $yaml, $matches)) {
            foreach ($matches[3] as $idx => $value) {
                if ($value === 'true' || $value === 'false' || $value === 'null') {
                    continue; // These are valid
                }
                if (is_numeric($value)) {
                    continue; // Numbers are valid
                }
                // Check if value has special chars and isn't quoted
                if (preg_match('/[:#\[\]{}|,]/', $value) && $value[0] !== '"' && $value[0] !== "'") {
                    $line = explode("\n", $yaml)[$idx] ?? '';
                    $errors[] = "Possible unquoted string with special chars: '$value'";
                }
            }
        }

        if (empty($errors)) {
            echo "✅ YAML format validation PASSED\n\n";
            return true;
        } else {
            echo "❌ YAML format validation FAILED:\n";
            foreach ($errors as $error) {
                echo "  - $error\n";
            }
            echo "\n";
            return false;
        }
    }
}

// Run test
$tester = new YamlTester();
$success = $tester->testRealisticConfig();

if ($success) {
    echo "✅ Test PASSED\n";
    exit(0);
} else {
    echo "❌ Test FAILED\n";
    exit(1);
}
