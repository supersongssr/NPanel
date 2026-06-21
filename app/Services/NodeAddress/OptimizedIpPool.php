<?php

namespace App\Services\NodeAddress;

use Illuminate\Support\Facades\Log;

/**
 * Cloudflare 优选 IP 池 (CSV 来源).
 *
 * 数据源: env('CF_BETTER_IPS_CSV') 指向的 CSV 文件, 默认
 *   storage/cf_ips/cloudflare_better_ips.csv
 *
 * CSV 表头 (兼容 CloudflareSpeedTest 输出):
 *   IP地址, 已发送, 已接收, 丢包率, 平均延迟, 下载速度(MB/s), 地区码, 测速时间
 *
 * 解析策略:
 *   - 自动识别表头, 按 "下载速度" 列降序排序 (快者优先).
 *   - 丢包率超过 MAX_LOSS_PERCENT 的行剔除.
 *   - 文件按 mtime 内存缓存 (进程内 static), CSV 更新后自动重读.
 *
 * 该池是 NodeAddressService (F2 CDN 模式) 的唯一 IP 来源, 与节点注册系统解耦.
 */
class OptimizedIpPool
{
    /** 丢包率阈值 (%), 超过则剔除该 IP. */
    const MAX_LOSS_PERCENT = 5.0;

    /** 内存缓存: filepath => ['mtime' => int, 'ips' => string[]] */
    private static $cache = [];

    /**
     * CSV 文件路径 (env 可自定义).
     *
     * @return string
     */
    public static function csvPath()
    {
        $configured = env('CF_BETTER_IPS_CSV');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        return storage_path('cf_ips/cloudflare_better_ips.csv');
    }

    /**
     * 读取并解析 CSV, 返回按下载速度降序的 IP 列表.
     *
     * 结果按文件 mtime 缓存; 文件未变化时直接返回缓存, 避免重复 IO / 解析.
     *
     * @return string[]  IP 列表 (最快的在前), 文件不存在/不可读时返回空数组
     */
    public static function all()
    {
        $path = self::csvPath();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $mtime = @filemtime($path);
        if ($mtime === false) {
            $mtime = 0;
        }
        if (isset(self::$cache[$path]['mtime']) && self::$cache[$path]['mtime'] === $mtime) {
            return self::$cache[$path]['ips'];
        }

        $ips = self::parseCsv($path);
        self::$cache[$path] = ['mtime' => $mtime, 'ips' => $ips];
        return $ips;
    }

    /**
     * 解析 CSV 文件 → 排序后的 IP 列表.
     *
     * @param  string $path
     * @return string[]
     */
    private static function parseCsv($path)
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            Log::warning('[OptimizedIpPool] 无法打开 CSV: ' . $path);
            return [];
        }

        $headerSeen = false;
        $ipIdx = 0;
        $lossIdx = 3;
        $speedIdx = 5;
        $rows = [];

        while (($cols = fgetcsv($handle, 4096)) !== false) {
            $cols = array_map(function ($c) {
                return is_string($c) ? trim($c) : $c;
            }, $cols);

            // 跳过空行
            if (empty($cols) || !isset($cols[0]) || $cols[0] === '') {
                continue;
            }

            // 首个非空行: 若像表头则解析列索引, 否则当作数据行
            if (!$headerSeen && self::looksLikeHeader($cols)) {
                $ipIdx = self::findCol($cols, ['IP地址', 'ip', 'IP']);
                $lossIdx = self::findCol($cols, ['丢包率', 'loss']);
                $speedIdx = self::findCol($cols, ['下载速度', 'speed', '下载']);
                $headerSeen = true;
                continue;
            }

            $ip = isset($cols[$ipIdx]) ? $cols[$ipIdx] : '';
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }

            $loss = isset($cols[$lossIdx]) ? (float) $cols[$lossIdx] : 0.0;
            if ($loss > self::MAX_LOSS_PERCENT) {
                continue;
            }

            $speed = isset($cols[$speedIdx]) ? (float) $cols[$speedIdx] : 0.0;
            $rows[] = ['ip' => $ip, 'speed' => $speed];
        }
        fclose($handle);

        // 按下载速度降序 (快者优先)
        usort($rows, function ($a, $b) {
            if ($a['speed'] === $b['speed']) {
                return 0;
            }
            return $a['speed'] < $b['speed'] ? 1 : -1;
        });

        return array_values(array_map(function ($r) {
            return $r['ip'];
        }, $rows));
    }

    /**
     * 判断一行是否为表头.
     */
    private static function looksLikeHeader(array $cols)
    {
        $joined = implode('|', $cols);
        return stripos($joined, 'IP') !== false || stripos($joined, '下载') !== false;
    }

    /**
     * 在表头中查找包含任一关键词的列索引.
     *
     * @param  array $cols
     * @param  array $keywords
     * @return int  匹配的列索引 (未匹配返回 0)
     */
    private static function findCol(array $cols, array $keywords)
    {
        foreach ($cols as $i => $name) {
            foreach ($keywords as $kw) {
                if (stripos((string) $name, $kw) !== false) {
                    return $i;
                }
            }
        }
        return 0;
    }

    /**
     * 为节点选取一个 CF 优选 IP.
     *
     * 选择策略 (与节点注册系统解耦, 仅依赖 nodeId + CSV):
     *   - $excludeIp 为空 / 不在列表中: start = nodeId % N (按 nodeId 错峰分散)
     *   - $excludeIp 在列表中 (轮换场景): start = (idx + 1) % N (前进一格, 保证不同)
     *   - 仅一个 IP: 直接返回该 IP
     *
     * @param  int|null    $nodeId    节点 ID (用于错峰)
     * @param  string|null $excludeIp 需排除的当前 IP (轮换时传入)
     * @return string  优选 IP; CSV 为空时返回空串
     */
    public static function pick($nodeId = null, $excludeIp = null)
    {
        $ips = self::all();
        if (empty($ips)) {
            return '';
        }
        $count = count($ips);
        if ($count === 1) {
            return $ips[0];
        }

        $start = abs((int) $nodeId) % $count;
        if ($excludeIp !== null && $excludeIp !== '') {
            $idx = array_search($excludeIp, $ips, true);
            if ($idx !== false) {
                $start = ((int) $idx + 1) % $count;
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $candidate = $ips[($start + $i) % $count];
            if ($excludeIp === null || $excludeIp === '' || $candidate !== $excludeIp) {
                return $candidate;
            }
        }
        return $ips[$start];
    }

    /**
     * 清除内存缓存 (测试 / 强制下次重读 CSV).
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}
