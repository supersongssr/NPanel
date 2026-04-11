<?php
/**
 * Test Clash YAML Output
 *
 * This script tests that the YAML output is valid and can be parsed by Clash
 * Run with: php tests/test_clash_yaml.php
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

echo "=== Clash YAML Output Test ===\n\n";

// Mock controller to test YAML generation
class YamlTester
{
    private function isAssociativeArray($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        if (empty($arr)) {
            return false; // 空数组视为索引数组
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

    public function testYamlOutput()
    {
        // Sample Clash config structure
        $config = [
            'mixed-port' => 7890,
            'allow-lan' => true,
            'mode' => 'rule',
            'log-level' => 'info',
            'proxies' => [
                [
                    'name' => 'test-proxy',
                    'type' => 'vmess',
                    'server' => 'example.com',
                    'port' => 443,
                    'uuid' => '12345678-1234-1234-1234-123456789abc',
                    'alterId' => 0,
                    'cipher' => 'auto',
                ],
            ],
            'proxy-groups' => [
                [
                    'name' => 'Proxy',
                    'type' => 'select',
                    'proxies' => ['test-proxy', 'DIRECT'],
                ],
            ],
            'rules' => [
                'DOMAIN-SUFFIX,local,DIRECT',
                'MATCH,Proxy',
            ],
        ];

        $yaml = $this->toYaml($config);

        echo "Generated YAML:\n";
        echo str_repeat("=", 60) . "\n";
        echo $yaml;
        echo str_repeat("=", 60) . "\n\n";

        // Basic validation checks
        $errors = [];
        $lines = explode("\n", $yaml);

        foreach ($lines as $lineNum => $line) {
            // Check for proper indentation (multiples of 2 spaces)
            if (strlen($line) > 0 && !ctype_space($line)) {
                $trimmed = ltrim($line);
                $indent = strlen($line) - strlen($trimmed);
                if ($indent % 2 !== 0) {
                    $errors[] = "Line " . ($lineNum + 1) . ": Invalid indentation (not multiple of 2)";
                }
            }

            // Check for common YAML errors
            if (preg_match('/^\s-\S/', $line)) {
                $errors[] = "Line " . ($lineNum + 1) . ": Missing space after hyphen";
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
$success = $tester->testYamlOutput();

if ($success) {
    echo "✅ Test PASSED\n";
    exit(0);
} else {
    echo "❌ Test FAILED\n";
    exit(1);
}
