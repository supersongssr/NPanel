<?php

namespace App\Components;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Class ProofOfWork
 *
 * 平滑型动态 PoW 工作量证明
 * - 基于服务器负载动态计算难度
 * - HMAC 签名防篡改
 * - Redis setnx 防重放
 */
class ProofOfWork
{
    const EXPIRE_SECONDS = 900; // 15分钟有效期

    /**
     * 生成 PoW Challenge
     *
     * @return array ['timestamp'=>int, 'salt'=>string, 'difficulty'=>int, 'signature'=>string]
     */
    public static function generateChallenge()
    {
        $config = Helpers::systemConfig();
        $baseDifficulty = isset($config['pow_base_difficulty']) ? intval($config['pow_base_difficulty']) : 10000;

        $load = sys_getloadavg()[0];
        $difficulty = (int)($baseDifficulty + $load * 160000);
        if ($difficulty < $baseDifficulty) {
            $difficulty = $baseDifficulty;
        }

        $timestamp = time();
        $salt = bin2hex(random_bytes(8));
        $ip = getClientIP();

        $signature = self::sign($ip, $timestamp, $salt, $difficulty);

        return [
            'timestamp'  => $timestamp,
            'salt'       => $salt,
            'difficulty' => $difficulty,
            'signature'  => $signature,
        ];
    }

    /**
     * 验证 PoW 工作量证明
     *
     * @param string $timestamp
     * @param string $salt
     * @param string $signature
     * @param string $nonce
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public static function verifyProof($timestamp, $salt, $signature, $nonce)
    {
        $now = time();
        $ts = intval($timestamp);

        // 1) 时效校验: 必须严格小于当前时间, 且不超过15分钟
        if ($ts >= $now) {
            return ['ok' => false, 'msg' => '时间戳异常'];
        }
        if (($now - $ts) > self::EXPIRE_SECONDS) {
            return ['ok' => false, 'msg' => '工作量证明已过期，请刷新页面'];
        }

        // 2) nonce 格式校验
        if (!preg_match('/^\d+$/', $nonce)) {
            return ['ok' => false, 'msg' => '工作量证明无效'];
        }

        // 3) 签名校验 — 从签名中反推 difficulty
        //    签名包含了 difficulty, 所以我们先验签再取 difficulty
        $ip = getClientIP();
        $config = Helpers::systemConfig();
        $baseDifficulty = isset($config['pow_base_difficulty']) ? intval($config['pow_base_difficulty']) : 10000;

        // 尝试在合理范围内匹配 difficulty
        $matchedDifficulty = null;
        // difficulty 范围: baseDifficulty 到 baseDifficulty + 160000*10 (极端负载)
        $maxDifficulty = $baseDifficulty + 160000 * 10;
        // 优化: 用签名反推 — 遍历效率太低, 改为前端也提交 difficulty
        // 但需求说只提交 timestamp/salt/signature/nonce, 所以从签名中提取 difficulty
        // 方案: 签名格式为 hmac(ip|timestamp|salt|difficulty), 前端提交 difficulty 参数
        // 但需求没提到提交 difficulty... 那就用另一种方案:
        // 将 difficulty 编码进 salt 的前缀, 或直接让前端也带 difficulty 参数

        // 实际上更好的做法: 前端也提交 difficulty, 后端重新计算签名比对
        // 这里暂时从 request 中获取, 但当前方法签名没有 request 对象
        // 最佳方案: 把 difficulty 也作为参数传入

        // --- 修正: 在签名时把 difficulty 明文传递, 前端原样带回 ---
        // verifyProof 方法需要额外接收 difficulty 参数
        // 这里暂时无法获取, 改由 controller 层传入

        return ['ok' => false, 'msg' => '内部错误'];
    }

    /**
     * 完整的 PoW 验证 (由 Controller 调用)
     *
     * @param array $params ['timestamp','salt','difficulty','signature','nonce']
     * @return array ['ok'=>bool, 'msg'=>string]
     */
    public static function verify($params)
    {
        $timestamp  = isset($params['timestamp'])  ? $params['timestamp']  : '';
        $salt       = isset($params['salt'])       ? $params['salt']       : '';
        $difficulty = isset($params['difficulty']) ? $params['difficulty'] : '';
        $signature  = isset($params['signature'])  ? $params['signature']  : '';
        $nonce      = isset($params['nonce'])      ? $params['nonce']      : '';

        $now = time();
        $ts = intval($timestamp);
        $diff = intval($difficulty);

        // 1) 时效校验
        if ($ts >= $now) {
            return ['ok' => false, 'msg' => '时间戳异常'];
        }
        if (($now - $ts) > self::EXPIRE_SECONDS) {
            return ['ok' => false, 'msg' => '工作量证明已过期，请刷新页面'];
        }

        // 2) nonce 格式
        if (!preg_match('/^\d+$/', $nonce)) {
            return ['ok' => false, 'msg' => '工作量证明无效'];
        }

        // 3) 签名校验
        $ip = getClientIP();
        $expectedSig = self::sign($ip, $ts, $salt, $diff);
        if (!hash_equals($expectedSig, $signature)) {
            return ['ok' => false, 'msg' => '签名校验失败'];
        }

        // 4) 工作量校验: SHA-256(salt + nonce) 前6位十六进制转整数 <= 阈值
        $hash = hash('sha256', $salt . $nonce);
        $prefix6 = substr($hash, 0, 6);
        $prefixVal = hexdec($prefix6);          // 0 ~ 16777215 (0xFFFFFF)
        $threshold = (int)(16777215 / $diff);   // 阈值

        if ($prefixVal > $threshold) {
            return ['ok' => false, 'msg' => '工作量证明不达标'];
        }

        // 5) Redis 防重放: setnx 原子写入
        $redisKey = 'pow:' . $hash;
        try {
            $set = Redis::set($redisKey, '1', 'EX', self::EXPIRE_SECONDS, 'NX');
            if (!$set) {
                return ['ok' => false, 'msg' => '工作量证明已被使用'];
            }
        } catch (\Exception $e) {
            Log::error('PoW Redis error: ' . $e->getMessage());
            return ['ok' => false, 'msg' => '系统繁忙，请重试'];
        }

        return ['ok' => true, 'msg' => 'ok'];
    }

    /**
     * HMAC 签名
     */
    private static function sign($ip, $timestamp, $salt, $difficulty)
    {
        $key = config('app.key');
        $data = $ip . '|' . $timestamp . '|' . $salt . '|' . $difficulty;
        return hash_hmac('sha256', $data, $key);
    }
}
