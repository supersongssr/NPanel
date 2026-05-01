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

    public function updateRecord($domain, $host, $value, $type = 'A', $zoneId = null)
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

        // Check if record exists
        $searchUrl = $url . "?name={$host}.{$domain}&type={$type}";
        $response = $this->sendRequest($searchUrl, 'GET');

        if ($response && $response['success'] && !empty($response['result'])) {
            $recordId = $response['result'][0]['id'];
            // Update
            $updateUrl = $url . "/{$recordId}";
            $data = [
                'type' => $type,
                'name' => "{$host}.{$domain}",
                'content' => $value,
                'ttl' => 120,
                'proxied' => false
            ];
            $res = $this->sendRequest($updateUrl, 'PUT', $data);
            return $res && $res['success'];
        } else {
            // Create
            $data = [
                'type' => $type,
                'name' => "{$host}.{$domain}",
                'content' => $value,
                'ttl' => 120,
                'proxied' => false
            ];
            $res = $this->sendRequest($url, 'POST', $data);
            return $res && $res['success'];
        }
    }

    /**
     * Create a new DNS record via CF API. Returns full response on success, false on failure.
     */
    public function createRecord($domain, $host, $value, $type = 'A', $zoneId = null)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records";
        $data = [
            'type' => $type,
            'name' => "{$host}.{$domain}",
            'content' => $value,
            'ttl' => 120,
            'proxied' => false,
        ];
        $res = $this->sendRequest($url, 'POST', $data);
        if ($res && $res['success']) {
            return $res['result'];
        }
        Log::error("CF createRecord failed: " . json_encode($res));
        return false;
    }

    /**
     * Update an existing DNS record by CF record ID. Returns full response on success, false on failure.
     */
    public function updateRecordById($cfRecordId, $domain, $host, $value, $type = 'A', $zoneId = null)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$cfRecordId}";
        $data = [
            'type' => $type,
            'name' => "{$host}.{$domain}",
            'content' => $value,
            'ttl' => 120,
            'proxied' => false,
        ];
        $res = $this->sendRequest($url, 'PUT', $data);
        if ($res && $res['success']) {
            return $res['result'];
        }
        Log::error("CF updateRecordById failed: " . json_encode($res));
        return false;
    }

    /**
     * List DNS records for a domain, optionally filtered by type.
     * Returns array of CF record objects or false on failure.
     */
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

    /**
     * Delete a DNS record by CF record ID. Returns true on success.
     */
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
     * Search for a Zone ID by domain name.
     */
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
        $headers = ["Content-Type: application/json"];
        
        if (!empty($this->token)) {
            $headers[] = "Authorization: Bearer {$this->token}";
        } elseif (!empty($this->email) && !empty($this->apiKey)) {
            $headers[] = "X-Auth-Email: {$this->email}";
            $headers[] = "X-Auth-Key: {$this->apiKey}";
        } else {
            Log::error('Cloudflare sendRequest: No valid credentials found');
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        $authType = !empty($this->token) ? 'Token' : 'GlobalKey';
        $preview = !empty($this->token) ? substr($this->token, 0, 5) . '...' : substr($this->email, 0, 5) . '...';

        Log::error("Cloudflare API error (HTTP {$httpCode})", [
            'url' => $url,
            'method' => $method,
            'response' => $response,
            'auth_type' => $authType,
            'auth_preview' => $preview
        ]);
        return false;
    }
}
