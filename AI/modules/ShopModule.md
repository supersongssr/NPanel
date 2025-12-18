# ShopModule - 商店系统模块

## 概述
ShopModule 管理套餐展示、购买流程、库存管理、订单处理等功能，为用户提供套餐选择、价格计算、优惠券使用等完整的购物体验。

## 技术信息

### 控制器
- `ShopController` - 商店系统控制器
- 位置：`app/Http/Controllers/ShopController.php`

### 数据模型
- `SsPlan` - 套餐信息模型
- `Order` - 订单模型
- `UserSubscribeLog` - 用户订阅记录
- `Coupon` - 优惠券模型
- `User` - 用户信息

## 主要功能

### 1. 套餐管理
- 套餐列表展示
- 套餐详情查看
- 套餐库存管理
- 价格体系管理
- 套餐等级限制

### 2. 购买流程
- 套餐选择
- 购买周期选择
- 优惠券使用
- 价格计算
- 订单创建

### 3. 优惠券系统
- 优惠券生成
- 优惠券验证
- 折扣计算
- 使用限制
- 有效期管理

### 4. 订单管理
- 订单创建
- 订单状态管理
- 订单查询
- 支付状态跟踪

## API 接口

### 1. 获取套餐列表
```http
GET /shop/plans?level=2
```

**响应**：
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "基础套餐",
            "content": "100GB流量/月，基础节点访问",
            "month_price": 9.99,
            "quarter_price": 26.97,
            "year_price": 89.99,
            "transfer_enable": 107374182400,
            "stock": 999,
            "is_sale": 1,
            "level": 0,
            "tags": ["热门", "推荐"],
            "discount": 0.8,
            "expire_at": "2025-12-31 23:59:59"
        }
    ]
}
```

### 2. 获取套餐详情
```http
GET /shop/plan/1
```

### 3. 创建购买订单
```http
POST /shop/purchase
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "plan_id": 1,
    "cycle": "month",
    "coupon_code": "SAVE20",
    "payment_method": "alipay"
}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "order_id": "ORD20241218001",
        "plan_id": 1,
        "plan_name": "基础套餐",
        "cycle": "month",
        "original_price": 9.99,
        "discount_amount": 2.00,
        "final_price": 7.99,
        "coupon_code": "SAVE20",
        "payment_method": "alipay",
        "status": "pending",
        "created_at": "2024-12-18 22:30:00"
    }
}
```

### 4. 获取订单列表
```http
GET /shop/orders?status=pending&page=1
Authorization: Bearer {user_token}
```

### 5. 获取订单详情
```http
GET /shop/order/ORD20241218001
Authorization: Bearer {user_token}
```

### 6. 验证优惠券
```http
POST /shop/coupon/validate
Authorization: Bearer {user_token}
Content-Type: application/json

{
    "coupon_code": "SAVE20",
    "plan_id": 1,
    "cycle": "month"
}
```

**响应**：
```json
{
    "status": "success",
    "data": {
        "valid": true,
        "coupon_info": {
            "id": 1,
            "code": "SAVE20",
            "type": "percentage",
            "value": 20,
            "description": "8折优惠券",
            "expire_at": "2024-12-31 23:59:59"
        },
        "discount_amount": 2.00,
        "final_price": 7.99
    }
}
```

## 套餐配置

### 套餐类型
```php
$planTypes = [
    'monthly' => '月付套餐',
    'quarterly' => '季付套餐',
    'yearly' => '年付套餐',
    'lifetime' => '终身套餐'
];
```

### 价格体系
```php
$plan = [
    'month_price' => 9.99,      // 月付价格
    'quarter_price' => 26.97,   // 季付价格（10%折扣）
    'half_year_price' => 49.99, // 半年价格（17%折扣）
    'year_price' => 89.99,      // 年付价格（25%折扣）
];
```

### 库存管理
- 无限库存：`stock = -1`
- 限制库存：`stock >= 0`
- 缺货状态：`stock = 0`

## 使用方法

### 1. 展示套餐列表
```php
public function getPlans(Request $request)
{
    $query = SsPlan::where('is_sale', 1);

    // 根据用户等级过滤
    $user = Auth::user();
    if ($user) {
        $query->where('level', '<=', $user->level);
    } else {
        $query->where('level', 0);
    }

    // 应用筛选
    if ($request->has('level')) {
        $query->where('level', $request->level);
    }

    if ($request->has('min_price')) {
        $query->where('month_price', '>=', $request->min_price);
    }

    if ($request->has('max_price')) {
        $query->where('month_price', '<=', $request->max_price);
    }

    $plans = $query->orderBy('sort', 'desc')
                   ->orderBy('id', 'desc')
                   ->get();

    // 计算折扣信息
    foreach ($plans as $plan) {
        if ($plan->quarter_price > 0) {
            $plan->quarter_discount = round((1 - $plan->quarter_price / ($plan->month_price * 3)) * 100, 1);
        }
        if ($plan->year_price > 0) {
            $plan->year_discount = round((1 - $plan->year_price / ($plan->month_price * 12)) * 100, 1);
        }
    }

    return response()->json(['status' => 'success', 'data' => $plans]);
}
```

### 2. 创建购买订单
```php
public function createOrder(Request $request)
{
    $user = Auth::user();
    $plan = SsPlan::findOrFail($request->plan_id);
    $cycle = $request->cycle; // month, quarter, year
    $couponCode = $request->coupon_code;

    // 验证用户权限
    if ($user->level < $plan->level) {
        return response()->json(['message' => '您的等级不足'], 403);
    }

    // 检查库存
    if ($plan->stock > 0 && $plan->stock < 1) {
        return response()->json(['message' => '套餐库存不足'], 400);
    }

    // 计算价格
    $price = $this->calculatePrice($plan, $cycle, $user, $couponCode);

    // 检查未完成订单
    $existingOrder = Order::where('user_id', $user->id)
                          ->where('status', 'pending')
                          ->where('created_at', '>=', Carbon::now()->subMinutes(30))
                          ->first();

    if ($existingOrder) {
        return response()->json(['message' => '您有待支付的订单，请先完成支付'], 400);
    }

    // 创建订单
    $order = new Order();
    $order->order_id = $this->generateOrderId();
    $order->user_id = $user->id;
    $order->plan_id = $plan->id;
    $order->cycle = $cycle;
    $order->original_price = $price['original'];
    $order->discount_amount = $price['discount'];
    $order->total_amount = $price['final'];
    $order->coupon_code = $couponCode;
    $order->status = 'pending';
    $order->expire_at = Carbon::now()->addMinutes(30);
    $order->save();

    // 扣减库存（预扣）
    if ($plan->stock > 0) {
        $plan->decrement('stock');
    }

    return response()->json(['status' => 'success', 'data' => $order]);
}
```

### 3. 优惠券验证
```php
public function validateCoupon(Request $request)
{
    $user = Auth::user();
    $couponCode = $request->coupon_code;
    $planId = $request->plan_id;
    $cycle = $request->cycle;

    $coupon = Coupon::where('code', $couponCode)
                    ->where('is_used', 0)
                    ->where('start_at', '<=', Carbon::now())
                    ->where('expire_at', '>=', Carbon::now())
                    ->first();

    if (!$coupon) {
        return response()->json([
            'status' => 'error',
            'message' => '优惠券不存在或已过期'
        ]);
    }

    // 检查使用限制
    if ($coupon->limit_per_user > 0) {
        $usedCount = Order::where('user_id', $user->id)
                         ->where('coupon_code', $couponCode)
                         ->where('status', 'paid')
                         ->count();

        if ($usedCount >= $coupon->limit_per_user) {
            return response()->json([
                'status' => 'error',
                'message' => '该优惠券使用次数已达上限'
            ]);
        }
    }

    // 计算折扣
    $plan = SsPlan::findOrFail($planId);
    $basePrice = $this->getBasePrice($plan, $cycle);
    $discountAmount = $this->calculateCouponDiscount($coupon, $basePrice);
    $finalPrice = $basePrice - $discountAmount;

    return response()->json([
        'status' => 'success',
        'data' => [
            'valid' => true,
            'coupon_info' => $coupon,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice
        ]
    ]);
}
```

### 4. 价格计算
```php
private function calculatePrice($plan, $cycle, $user, $couponCode = null)
{
    // 获取基础价格
    $priceField = $cycle . '_price';
    $basePrice = $plan->$priceField;

    // 用户折扣
    $userDiscount = $this->getUserDiscount($user, $cycle);
    $discountedPrice = $basePrice * $userDiscount;

    // 优惠券折扣
    $couponDiscount = 0;
    if ($couponCode) {
        $coupon = Coupon::where('code', $couponCode)->first();
        if ($coupon) {
            $couponDiscount = $this->calculateCouponDiscount($coupon, $discountedPrice);
        }
    }

    return [
        'original' => $basePrice,
        'user_discount' => $basePrice * (1 - $userDiscount),
        'coupon_discount' => $couponDiscount,
        'final' => $discountedPrice - $couponDiscount
    ];
}

private function getUserDiscount($user, $cycle)
{
    // VIP用户折扣
    if ($user->level >= 3) {
        return 0.8; // 8折
    } elseif ($user->level >= 2) {
        return 0.9; // 9折
    } elseif ($user->level >= 1) {
        return 0.95; // 95折
    }

    // 老用户折扣
    $regDays = Carbon::parse($user->created_at)->diffInDays(Carbon::now());
    if ($regDays >= 365) {
        return 0.95; // 老用户95折
    }

    return 1.0; // 无折扣
}
```

## 约束条件

1. **业务规则**
   - 用户等级必须满足套餐要求
   - 套餐库存不能为负数
   - 价格计算必须准确
   - 优惠券使用限制

2. **数据完整性**
   - 订单号必须唯一
   - 价格精度保留2位小数
   - 时间记录准确

3. **安全要求**
   - 价格不能被篡改
   - 订单状态变更需要记录
   - 防重复提交

## 最佳实践

### 1. 使用事务保证数据一致性
```php
DB::transaction(function() use ($user, $plan, $orderData) {
    // 创建订单
    $order = Order::create($orderData);

    // 扣减库存
    if ($plan->stock > 0) {
        $plan->decrement('stock');
    }

    // 记录优惠券使用
    if ($orderData['coupon_code']) {
        $coupon = Coupon::where('code', $orderData['coupon_code'])->first();
        if ($coupon) {
            $coupon->increment('used_count');
        }
    }
});
```

### 2. 异步处理订单超时
```php
// 创建订单时设置超时任务
ProcessOrderTimeout::dispatch($order->id)->delay(Carbon::now()->addMinutes(30));
```

### 3. 缓存套餐数据
```php
$plans = Cache::remember('shop_plans', 3600, function() {
    return SsPlan::where('is_sale', 1)
                ->with(['tags'])
                ->orderBy('sort', 'desc')
                ->get();
});
```

## 测试要点

1. **套餐展示**
   - 套餐列表正确性
   - 权限过滤
   - 价格展示

2. **购买流程**
   - 订单创建
   - 价格计算
   - 库存管理

3. **优惠券系统**
   - 优惠券验证
   - 折扣计算
   - 使用限制

4. **异常处理**
   - 库存不足
   - 权限不足
   - 价格异常

## 注意事项

1. 订单需要设置支付超时
2. 库存扣减需要考虑并发
3. 价格计算需要防止篡改
4. 优惠券需要有使用记录
5. 套餐变更需要通知用户
6. 定期清理过期订单
7. 支持批量购买功能