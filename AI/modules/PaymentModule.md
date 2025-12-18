# PaymentModule - 支付系统模块

## 概述
PaymentModule 处理多种支付方式的支付请求和回调，包括支付宝、微信支付、Stripe、PayPal以及多种第三方支付平台，提供完整的支付流程管理和订单处理功能。

## 技术信息

### 控制器
- `PaymentController` - 主要支付控制器
- `Api/AlipayController` - 支付宝支付
- `Api/YzyController` - 易支付
- `Api/TrimepayController` - Trime支付
- `Api/F2fpayController` - F2F支付
- 位置：`app/Http/Controllers/PaymentController.php`, `app/Http/Controllers/Api/`

### 数据模型
- `Payback` - 支付回调记录
- `Order` - 订单模型
- `UserBalanceLog` - 用户余额日志
- `UserSubscribeLog` - 用户订阅记录
- `Coupon` - 优惠券使用记录

## 支持的支付方式

### 1. 支付宝 (Alipay)
- **类型**: 网页支付、手机支付、扫码支付
- **SDK**: 官方SDK或第三方集成
- **特点**: 用户基数大，支付体验好

### 2. 微信支付 (WeChat Pay)
- **类型**: 公众号支付、扫码支付、APP支付
- **SDK**: 微信支付SDK
- **特点**: 移动端支付便捷

### 3. Stripe
- **类型**: 信用卡支付
- **SDK**: Stripe PHP SDK
- **特点**: 国际化，支持多种货币

### 4. PayPal
- **类型**: 国际支付
- **SDK**: PayPal SDK
- **特点**: 全球通用

### 5. 第三方聚合支付
- 易支付 (YzyPay)
- TrimePay
- F2F支付
- 特点：接入简单，费率较低

## API 接口

### 1. 创建支付订单
```http
POST /payment/create
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "order_id": "ORD20241218001",
    "amount": 9.99,
    "gateway": "alipay",
    "return_url": "https://example.com/payment/return",
    "notify_url": "https://example.com/payment/notify",
    "client_type": "web"
}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "payment_id": "PAY20241218001",
        "payment_url": "https://openapi.alipay.com/gateway.do?...",
        "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
        "order_id": "ORD20241218001",
        "amount": 9.99,
        "gateway": "alipay",
        "expire_time": "2024-12-18 23:00:00"
    }
}
```

### 2. 查询支付状态
```http
GET /payment/status/ORD20241218001
Authorization: Bearer {user_token}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "order_id": "ORD20241218001",
        "payment_status": "paid",
        "paid_amount": 9.99,
        "paid_time": "2024-12-18 22:45:30",
        "gateway": "alipay",
        "gateway_trade_no": "2024121822001234567890"
    }
}
```

### 3. 取消支付
```http
POST /payment/cancel/ORD20241218001
Authorization: Bearer {user_token}
```

### 4. 支付回调接口
```http
POST /api/payment/callback/alipay
Content-Type: application/x-www-form-urlencoded

gmt_create=2024-12-18+22%3A45%3A30&charset=UTF-8&...
```

### 5. 获取支付方式列表
```http
GET /payment/methods
Authorization: Bearer {user_token}
```

### 6. 余额支付
```http
POST /payment/balance/use
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "order_id": "ORD20241218001",
    "amount": 9.99,
    "password": "user_password"
}
```

## 支付流程

### 1. 标准支付流程
```
用户选择支付方式
       ↓
系统创建支付请求
       ↓
调用支付网关API
       ↓
获取支付链接/二维码
       ↓
用户完成支付
       ↓
接收支付回调
       ↓
验证支付结果
       ↓
更新订单状态
       ↓
激活用户服务
```

### 2. 余额支付流程
```
用户选择余额支付
       ↓
验证用户密码
       ↓
检查余额是否充足
       ↓
扣除账户余额
       ↓
更新订单状态
       ↓
激活用户服务
```

## 使用方法

### 1. 创建支付宝支付
```php
public function createAlipayPayment(Request $request)
{
    $order = Order::where('order_id', $request->order_id)
                  ->where('user_id', Auth::id())
                  ->where('status', 'pending')
                  ->firstOrFail();

    // 创建支付配置
    $config = [
        'app_id' => config('payment.alipay.app_id'),
        'private_key' => config('payment.alipay.private_key'),
        'public_key' => config('payment.alipay.public_key'),
        'notify_url' => route('payment.callback', 'alipay'),
        'return_url' => $request->return_url,
    ];

    // 构建支付参数
    $params = [
        'out_trade_no' => $order->order_id,
        'total_amount' => $order->total_amount,
        'subject' => '套餐购买 - ' . $order->plan->name,
        'product_code' => 'FAST_INSTANT_TRADE_PAY',
    ];

    // 生成支付链接
    $alipay = new Alipay($config);
    $result = $alipay->pagePay($params);

    // 记录支付请求
    PaymentLog::create([
        'order_id' => $order->id,
        'gateway' => 'alipay',
        'amount' => $order->total_amount,
        'status' => 'created',
        'request_data' => json_encode($params),
        'response_data' => json_encode($result),
    ]);

    return response()->json([
        'status' => 'success',
        'data' => [
            'payment_url' => $result,
            'order_id' => $order->order_id,
            'amount' => $order->total_amount,
            'expire_time' => $order->expire_at,
        ]
    ]);
}
```

### 2. 处理支付回调
```php
public function handleAlipayCallback(Request $request)
{
    $config = [
        'app_id' => config('payment.alipay.app_id'),
        'private_key' => config('payment.alipay.private_key'),
        'public_key' => config('payment.alipay.public_key'),
    ];

    $alipay = new Alipay($config);

    // 验证签名
    if (!$alipay->verify($request->all())) {
        Log::error('Alipay callback verification failed', $request->all());
        return 'fail';
    }

    // 查找订单
    $order = Order::where('order_id', $request->out_trade_no)->first();
    if (!$order || $order->status != 'pending') {
        return 'success'; // 避免重复处理
    }

    // 验证金额
    if (bccomp($request->total_amount, $order->total_amount, 2) !== 0) {
        Log::error('Alipay amount mismatch', [
            'order_amount' => $order->total_amount,
            'callback_amount' => $request->total_amount
        ]);
        return 'fail';
    }

    // 处理支付成功
    DB::transaction(function() use ($order, $request) {
        // 更新订单状态
        $order->status = 'paid';
        $order->paid_at = Carbon::now();
        $order->gateway_trade_no = $request->trade_no;
        $order->save();

        // 激活用户服务
        $this->activateUserService($order);

        // 记录支付日志
        Payback::create([
            'total_amount' => $request->total_amount,
            'tradeno' => $request->out_trade_no,
            'trade_no' => $request->trade_no,
            'gateway' => 'alipay',
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'content' => json_encode($request->all()),
        ]);

        // 发送支付成功通知
        $this->sendPaymentSuccessNotification($order);
    });

    return 'success';
}
```

### 3. 余额支付处理
```php
public function balancePayment(Request $request)
{
    $user = Auth::user();

    // 验证密码
    if (!Hash::check($request->password, $user->password)) {
        return response()->json(['message' => '密码错误'], 400);
    }

    $order = Order::where('order_id', $request->order_id)
                  ->where('user_id', $user->id)
                  ->where('status', 'pending')
                  ->firstOrFail();

    // 检查余额
    if ($user->balance < $order->total_amount) {
        return response()->json(['message' => '余额不足'], 400);
    }

    DB::transaction(function() use ($user, $order) {
        // 扣除余额
        $user->decrement('balance', $order->total_amount);

        // 记录余额变动
        UserBalanceLog::create([
            'user_id' => $user->id,
            'amount' => -$order->total_amount,
            'description' => '套餐购买 - ' . $order->plan->name,
            'type' => 'purchase',
            'order_id' => $order->id,
        ]);

        // 更新订单状态
        $order->status = 'paid';
        $order->paid_at = Carbon::now();
        $order->gateway = 'balance';
        $order->save();

        // 激活用户服务
        $this->activateUserService($order);
    });

    return response()->json(['status' => 'success']);
}
```

### 4. 激活用户服务
```php
private function activateUserService($order)
{
    $user = $order->user;
    $plan = $order->plan;

    // 计算新的到期时间
    $now = Carbon::now();
    $expireTime = $user->expire_time > $now ? $user->expire_time : $now;

    switch ($order->cycle) {
        case 'month':
            $newExpireTime = $expireTime->addMonth();
            break;
        case 'quarter':
            $newExpireTime = $expireTime->addMonths(3);
            break;
        case 'year':
            $newExpireTime = $expireTime->addYear();
            break;
        default:
            $newExpireTime = $expireTime->addMonth();
    }

    // 更新用户信息
    $user->update([
        'expire_time' => $newExpireTime,
        'transfer_enable' => $plan->transfer_enable,
        'u' => 0,
        'd' => 0,
        'last_day' => 0,
    ]);

    // 记录订阅日志
    UserSubscribeLog::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'cycle' => $order->cycle,
        'price' => $order->total_amount,
        'ref_time' => $expireTime->timestamp,
        'created_at' => $now,
    ]);

    // 使用优惠券标记
    if ($order->coupon_code) {
        $coupon = Coupon::where('code', $order->coupon_code)->first();
        if ($coupon && $coupon->limit_per_user > 0) {
            $coupon->increment('used_count');
        }
    }
}
```

## 支付配置

### 环境变量配置
```env
# 支付宝配置
ALIPAY_APP_ID=your_app_id
ALIPAY_PRIVATE_KEY=your_private_key
ALIPAY_PUBLIC_KEY=alipay_public_key
ALIPAY_NOTIFY_URL=https://example.com/api/payment/callback/alipay

# 微信支付配置
WECHAT_APP_ID=your_app_id
WECHAT_MCH_ID=your_mch_id
WECHAT_API_KEY=your_api_key
WECHAT_CERT_PATH=/path/to/cert.pem

# Stripe配置
STRIPE_KEY=pk_test_your_key
STRIPE_SECRET=sk_test_your_secret
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# PayPal配置
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
PAYPAL_MODE=sandbox  # sandbox or live
```

## 约束条件

1. **安全性要求**
   - 支付回调必须验证签名
   - 敏感信息加密存储
   - 防止重复支付
   - 支付金额防篡改

2. **数据完整性**
   - 订单状态变更需要记录
   - 支付流水唯一性
   - 余额变动可追溯

3. **业务规则**
   - 订单支付超时处理
   - 优惠券使用限制
   - 退款流程管理

## 最佳实践

### 1. 异步处理支付结果
```php
// 使用队列处理支付成功后的任务
ProcessPaymentSuccess::dispatch([
    'order_id' => $order->id,
    'payment_data' => $request->all()
])->onQueue('payment');
```

### 2. 支付重试机制
```php
// 支付失败自动重试
public function retryPayment($order)
{
    if ($order->retry_count >= 3) {
        $order->status = 'failed';
        $order->save();
        return false;
    }

    $order->increment('retry_count');

    // 重新发起支付
    return $this->createNewPayment($order);
}
```

### 3. 使用事务保证一致性
```php
DB::transaction(function() use ($order) {
    // 更新订单
    $order->update(['status' => 'paid']);

    // 扣减库存
    $order->plan->decrement('stock');

    // 激活服务
    $this->activateUser($order->user_id);

    // 记录日志
    $this->logPayment($order);
});
```

## 测试要点

1. **支付流程**
   - 正常支付流程
   - 支付取消
   - 支付超时
   - 重复支付防护

2. **回调处理**
   - 签名验证
   - 金额验证
   - 重复回调处理
   - 异常回调处理

3. **余额支付**
   - 余额验证
   - 密码验证
   - 扣款准确性
   - 并发处理

4. **安全性**
   - 防篡改
   - 防重放
   - 数据加密
   - 权限控制

## 注意事项

1. 支付回调需要幂等处理
2. 订单状态变更需要同步更新
3. 支付日志需要完整记录
4. 支付异常需要及时通知
5. 退款流程需要完善
6. 定期对账保证数据准确
7. 支付成功率需要监控
8. 支付超时需要自动处理