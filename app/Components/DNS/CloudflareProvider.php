<?php

namespace App\Components\DNS;

use App\Components\Curl;
use Illuminate\Support\Facades\Log;

class CloudflareProvider implements DnsProviderInterface
{
    protected $email;
    protected $apiKey;
    protected $zoneId;

    public function __construct()
    {
        $this->email = env('CLOUDFLARE_EMAIL');
        $this->apiKey = env('CLOUDFLARE_API_KEY');
        $this->zoneId = env('CLOUDFLARE_ZONE_ID');
    }

    public function updateRecord($domain, $host, $value, $type = 'A')
    {
        if (empty($this->email) || empty($this->apiKey) || empty($this->zoneId)) {
            Log::error('Cloudflare credentials missing in .env');
            return false;
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records";

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
    public function createRecord($domain, $host, $value, $type = 'A')
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records";
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
    public function updateRecordById($cfRecordId, $domain, $host, $value, $type = 'A')
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records/{$cfRecordId}";
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
     * Delete a DNS record by CF record ID. Returns true on success.
     */
    public function deleteRecord($cfRecordId)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records/{$cfRecordId}";
        $res = $this->sendRequest($url, 'DELETE');
        if ($res && $res['success']) {
            return true;
        }
        Log::error("CF deleteRecord failed: " . json_encode($res));
        return false;
    }

    private function sendRequest($url, $method, $data = null)
    {
        $headers = [
            "X-Auth-Email: {$this->email}",
            "X-Auth-Key: {$this->apiKey}",
            "Content-Type: application/json"
        ];

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

        Log::error("Cloudflare API error (HTTP {$httpCode}): " . $response);
        return false;
    }
}
