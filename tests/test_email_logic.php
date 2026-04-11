<?php
/**
 * 测试邮箱域名黑名单逻辑
 *
 * 用于验证修复后的邮箱限制逻辑是否正确
 */

// 模拟 sensitiveWords() 方法返回的黑名单
$sensitiveWords = ['ssmail.win', 'spam.com']; // 示例黑名单

// 测试用例
$testCases = [
    'test@gmail.com' => ['should_pass' => true, 'reason' => 'Gmail - 主流邮箱'],
    'test@qq.com' => ['should_pass' => true, 'reason' => 'QQ邮箱 - 主流邮箱'],
    'test@163.com' => ['should_pass' => true, 'reason' => '163邮箱 - 主流邮箱'],
    'test@outlook.com' => ['should_pass' => true, 'reason' => 'Outlook - 主流邮箱'],
    'test@hotmail.com' => ['should_pass' => true, 'reason' => 'Hotmail - 主流邮箱'],
    'test@yahoo.com' => ['should_pass' => true, 'reason' => 'Yahoo - 主流邮箱'],
    'test@ssmail.win' => ['should_pass' => false, 'reason' => '在黑名单中'],
    'test@spam.com' => ['should_pass' => false, 'reason' => '在黑名单中'],
    'test@custom-domain.com' => ['should_pass' => true, 'reason' => '自定义域名 - 不在黑名单'],
    'test@126.com' => ['should_pass' => true, 'reason' => '126邮箱 - 主流邮箱'],
    'test@foxmail.com' => ['should_pass' => true, 'reason' => 'Foxmail - 主流邮箱'],
];

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       邮箱域名黑名单逻辑测试 - 黑名单模式                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "当前黑名单: " . implode(', ', $sensitiveWords) . "\n\n";
echo str_repeat('=', 80) . "\n\n";

$passCount = 0;
$failCount = 0;

foreach ($testCases as $email => $expected) {
    // 提取邮箱后缀（模拟代码逻辑）
    $usernameSuffix = explode('@', $email);
    $domain = strtolower($usernameSuffix[1]);

    // 黑名单逻辑：如果在黑名单中则拒绝
    $isBlocked = in_array($domain, $sensitiveWords);
    $actualPass = !$isBlocked;

    // 检查结果
    $testPassed = ($actualPass === $expected['should_pass']);

    $status = $testPassed ? '✅ PASS' : '❌ FAIL';
    $result = $actualPass ? '允许注册' : '拒绝注册';
    $expectedResult = $expected['should_pass'] ? '允许注册' : '拒绝注册';

    if ($testPassed) {
        $passCount++;
    } else {
        $failCount++;
    }

    printf("%-20s %-15s %-15s | %s\n", $email, $result, $expectedResult, $expected['reason']);
    if (!$testPassed) {
        echo "                                                    ⚠️ 测试失败!\n";
    }
    echo "\n";
}

echo str_repeat('=', 80) . "\n";
echo "测试结果统计:\n";
echo "✅ 通过: $passCount\n";
echo "❌ 失败: $failCount\n";
echo "📊 总计: " . ($passCount + $failCount) . "\n";

if ($failCount === 0) {
    echo "\n🎉 所有测试通过!逻辑正确!\n";
} else {
    echo "\n⚠️ 存在失败的测试,请检查逻辑!\n";
}

// 对比新旧逻辑
echo "\n" . str_repeat('=', 80) . "\n";
echo "逻辑对比:\n\n";

echo "【旧逻辑 - 白名单模式】\n";
echo "if (!in_array(strtolower(\$usernameSuffix[1]), \$sensitiveWords)) {\n";
echo "    return '邮箱不常见，请联系管理员';\n";
echo "}\n";
echo "❌ 问题: 只有在白名单中的邮箱才能注册!\n";
echo "❌ 影响: Gmail、QQ、163等主流邮箱全部被拦截!\n\n";

echo "【新逻辑 - 黑名单模式】\n";
echo "if (in_array(strtolower(\$usernameSuffix[1]), \$sensitiveWords)) {\n";
echo "    return '该邮箱域名暂时不支持注册，请更换主流邮箱';\n";
echo "}\n";
echo "✅ 优点: 所有邮箱都能注册,只拦截黑名单中的域名!\n";
echo "✅ 影响: 只有 ssmail.win、spam.com 等被拦截!\n\n";

echo str_repeat('=', 80) . "\n";
