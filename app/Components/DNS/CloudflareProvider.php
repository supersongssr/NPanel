<?php

namespace App\Components\DNS;

use App\Components\Curl;
use Illuminate\Support\Facades\Log;

class CloudflareProvider implements DnsProviderInterface
{
    protected $token;
    protected $email;
    protected $apiKey;

    public function __construct()
    {
        $this->token = env('CLOUDFLARE_TOKEN');
        $this->email = env('CLOUDFLARE_EMAIL');
        $this->apiKey = env('CLOUDFLARE_API_KEY');
    }

    /**
     * 构造一个使用指定 Token 的 CloudflareProvider 实例.
     *
     * 用于域名池中配置了独立 cf_token 的域名 (与全局 CLOUDFLARE_TOKEN 隔离).
     * cf_token 与 cdn 正交: 跨账号托管的普通域名 (非 CDN) 同样需要独立 Token,
     * 防止 CDN 域名被封号 / 跨账号鉴权失败影响 DNS 记录增删.
     *
     * @param  string $token 独立的 Cloudflare API Token
     * @return static
     */
    public static function withToken($token)
    {
        $instance = new static();
        if (!empty($token)) {
            $instance->token = $token;
            // 独立 Token 优先, 清除全局 KEY 凭据, 避免混用
            $instance->email = null;
            $instance->apiKey = null;
        }
        return $instance;
    }

    public function updateRecord($domain, $host, $value, $type = 'A', $zoneId = null, $proxied = false)
    {
        if (empty($this->token) && (empty($this->email) || empty($this->apiKey))) {
            Log::error('Cloudflare credentials missing in .env (Need CLOUDFLARE_TOKEN or EMAIL+API_KEY)');
            return false;
        }

        if (empty($zoneId)) {
            Log::error('Cloudflare updateRecord: zone_id is required');
            return false;
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records";

        $searchUrl = $url . "?name={$host}.{$domain}&type={$type}";
        $response = $this->sendRequest($searchUrl, 'GET');

        if ($response && $response['success'] && !empty($response['result'])) {
            $recordId = $response['result'][0]['id'];
            $updateUrl = $url . "/{$recordId}";
            $data = [
                'type' => $type,
                'name' => "{$host}.{$domain}",
                'content' => $value,
                'ttl' => 120,
                'proxied' => $proxied,
            ];
            $res = $this->sendRequest($updateUrl, 'PUT', $data);
            return $res && $res['success'];
        } else {
            $data = [
                'type' => $type,
                'name' => "{$host}.{$domain}",
                'content' => $value,
                'ttl' => 120,
                'proxied' => $proxied,
            ];
            $res = $this->sendRequest($url, 'POST', $data);
            return $res && $res['success'];
        }
    }

    public function createRecord($domain, $host, $value, $type = 'A', $zoneId = null, $proxied = false)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records";
        $data = [
            'type' => $type,
            'name' => "{$host}.{$domain}",
            'content' => $value,
            'ttl' => 120,
            'proxied' => $proxied,
        ];
        $res = $this->sendRequest($url, 'POST', $data);
        if ($res && $res['success']) {
            return $res['result'];
        }
        Log::error("CF createRecord failed: " . json_encode($res));
        return false;
    }

    public function updateRecordById($cfRecordId, $domain, $host, $value, $type = 'A', $zoneId = null, $proxied = false)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$cfRecordId}";
        $data = [
            'type' => $type,
            'name' => "{$host}.{$domain}",
            'content' => $value,
            'ttl' => 120,
            'proxied' => $proxied,
        ];
        $res = $this->sendRequest($url, 'PUT', $data);
        if ($res && $res['success']) {
            return $res['result'];
        }
        Log::error("CF updateRecordById failed: " . json_encode($res));
        return false;
    }

    public function getRecordByNameAndType($domain, $host, $type, $zoneId)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records?name={$host}.{$domain}&type={$type}";
        $res = $this->sendRequest($url, 'GET');
        if ($res && $res['success']) {
            return !empty($res['result']) ? $res['result'][0] : null;
        }
        return false;
    }

    public function listRecords($domain, $type = null, $zoneId = null)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records";
        if ($type) {
            $url .= "?type={$type}";
        }
        $res = $this->sendRequest($url, 'GET');
        if ($res && $res['success']) {
            return $res['result'];
        }
        Log::error("CF listRecords failed: " . json_encode($res));
        return false;
    }

    public function deleteRecord($cfRecordId, $zoneId = null)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$cfRecordId}";
        $res = $this->sendRequest($url, 'DELETE');
        if ($res && $res['success']) {
            return true;
        }
        Log::error("CF deleteRecord failed: " . json_encode($res));
        return false;
    }

    /**
     * Delete a DNS record and return structured status information.
     *
     * Unlike deleteRecord() which returns only bool, this method exposes
     * HTTP status codes so callers can distinguish 404 (already gone)
     * from auth errors, rate limits, network failures, etc.
     *
     * @param string $cfRecordId
     * @param string $zoneId
     * @return array [
     *     'success'      => bool,  // true if CF confirmed deletion
     *     'not_found'    => bool,  // true if record already gone (404)
     *     'rate_limited' => bool,  // true if 429
     *     'http_code'    => int|null,
     *     'error'        => string,
     * ]
     */
    public function deleteRecordWithStatus($cfRecordId, $zoneId = null)
    {
        $result = array(
            'success'      => false,
            'not_found'    => false,
            'rate_limited' => false,
            'http_code'    => null,
            'error'        => '',
        );

        if (empty($cfRecordId) || empty($zoneId)) {
            $result['error'] = 'Missing cf_record_id or zone_id';
            return $result;
        }

        $raw = $this->sendRequestRaw(
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$cfRecordId}",
            'DELETE'
        );

        $result['http_code'] = $raw['http_code'];

        if ($raw['curl_error']) {
            $result['error'] = $raw['curl_error'];
            return $result;
        }

        if ($raw['http_code'] === 404) {
            $result['not_found'] = true;
            $result['success'] = true;
            return $result;
        }

        if ($raw['http_code'] === 429) {
            $result['rate_limited'] = true;
            $result['error'] = 'Rate limited by Cloudflare';
            return $result;
        }

        if ($raw['decoded'] && !empty($raw['decoded']['success'])) {
            $result['success'] = true;
            return $result;
        }

        $result['error'] = 'HTTP ' . $raw['http_code'] . ': ' . substr($raw['body'], 0, 300);
        return $result;
    }

    public function getZoneIdByName($domain)
    {
        $url = "https://api.cloudflare.com/client/v4/zones?name={$domain}";
        $res = $this->sendRequest($url, 'GET');
        if ($res && $res['success'] && !empty($res['result'])) {
            return $res['result'][0]['id'];
        }
        return false;
    }

    private function sendRequest($url, $method, $data = null)
    {
        $raw = $this->sendRequestRaw($url, $method, $data);

        if ($raw['curl_error']) {
            Log::error("Cloudflare API curl error: {$raw['curl_error']}", [
                'url' => $url, 'method' => $method
            ]);
            return false;
        }

        if ($raw['http_code'] >= 200 && $raw['http_code'] < 300) {
            return $raw['decoded'];
        }

        $authType = !empty($this->token) ? 'Token' : 'GlobalKey';
        $preview = !empty($this->token) ? substr($this->token, 0, 5) . '...' : substr($this->email, 0, 5) . '...';

        Log::error("Cloudflare API error (HTTP {$raw['http_code']})", [
            'url' => $url,
            'method' => $method,
            'response' => $raw['body'],
            'auth_type' => $authType,
            'auth_preview' => $preview
        ]);
        return false;
    }

    /**
     * Send an HTTP request and return raw details (http_code, body, curl_error, decoded JSON).
     *
     * @param string $url
     * @param string $method
     * @param array|null $data
     * @return array
     */
    private function sendRequestRaw($url, $method, $data = null)
    {
        $headers = array("Content-Type: application/json");

        if (!empty($this->token)) {
            $headers[] = "Authorization: Bearer {$this->token}";
        } elseif (!empty($this->email) && !empty($this->apiKey)) {
            $headers[] = "X-Auth-Email: {$this->email}";
            $headers[] = "X-Auth-Key: {$this->apiKey}";
        } else {
            return array(
                'http_code'  => null,
                'body'       => '',
                'decoded'    => null,
                'curl_error' => 'No valid Cloudflare credentials found',
            );
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;
        if ($response && !$curlError) {
            $decoded = json_decode($response, true);
        }

        return array(
            'http_code'  => $httpCode,
            'body'       => is_string($response) ? $response : '',
            'decoded'    => $decoded,
            'curl_error' => $curlError,
        );
    }
}
