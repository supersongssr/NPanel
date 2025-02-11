<?php
namespace App\Components;
/**
 * Class Trimepay
 *
 * @author  deepbwork
 *
 * @package App\Components
 */
class Trimepay
{
    private $appId;
    private $appSecret;
    /**
     * 签名初始化
     *
     * @param string $appId     appId
     * @param string $appSecret appSecret
     */
    public function __construct($appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        //国内
        // $this->gatewayUri = 'https://api.ecy.es/gateway/pay/go';
        // $this->refundUri = 'https://api.ecy.es/gateway/refund/go';
        //国外
        $this->gatewayUri = 'https://api.paytaro.com/v1/gateway/fetch';
        $this->refundUri = 'https://api.paytaro.com/v1/gateway/fetch';
        $this->preUri = 'https://api.paytaro.com/v1/gateway/fetch';
    }
    /**
     * 准备签名
     *
     * @param array $data 验签字符串
     *
     * @return string
     */
    public function prepareSign($data)
    {
        ksort($data);
        return http_build_query($data);
    }
    /**
     * 生成签名
     *
     * @param string $data 签名数据
     *
     * @return string
     */
    public function sign($data)
    {
        $signature = strtolower(md5($data . $this->appSecret));
        return $signature;
    }
    /**
     * 验证签名
     *
     * @param string $data      签名数据
     * @param string $signature 原数据
     *
     * @return bool
     */
    public function verify($data, $signature)
    {
        // $mySign = $this->sign($data);
        // if ($mySign === $signature) {
        //     return true;
        // } else {
        //     return false;
        // }
        unset($data['sign']);
        $mySign = $this->sign($this->prepareSign($data));
        return $mySign === $signature;
    }
    public function post($data)
    {
        /*if ($url == '') {
            $url = $this->gatewayUri;
        } else {
            $url = $this->preUri;
        }*/
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->gatewayUri);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($curl);
        curl_close($curl);
        return json_decode($data, true);
    }


    public function pay($tradeNo, $totalFee, $notifyUrl, $returnUrl)
    {
        $payData = [
            'app_id' => $this->appId,
            'out_trade_no' => $tradeNo, // 订单号 唯一标志符号
            'total_amount' => $totalFee,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl
        ];
        $signData = $this->prepareSign($payData);
        $payData['sign'] = $this->sign($signData);

        /*if ($type === 'WEPAY_JSAPI'){
            $response = $this->post($payData);
            return $response;
        } else {*/
            $response = $this->post($payData);
            return $response;
        /*}*/
    }
    public function refund($merchantTradeNo)
    {
        $params['merchantTradeNo'] = $merchantTradeNo;
        $params['appId'] = $this->appId;
        $prepareSign = $this->prepareSign($params);
        $params['sign'] = $this->sign($prepareSign);
        return $this->post($params, $this->refundUri);
    }
    public function buildHtml($params, $method = 'post', $target = '_self')
    {
        // var_dump($params);exit;
        $html = "<form id='submit' name='submit' action='" . $this->gatewayUri . "' method='$method' target='$target'>";
        foreach ($params as $key => $value) {
            $html .= "<input type='hidden' name='$key' value='$value'/>";
        }
        $html .= "</form><script>document.forms['submit'].submit();</script>";
        return $html;
    }
}
