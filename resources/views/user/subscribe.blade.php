@extends('user.layouts')
@section('css')
    <style>
        /* 多订阅域名：内联单行工具条（弱化辅助功能，突出主体教程） */
        .domain-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            margin: 0 0 16px 0;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 12px;
            color: #999;
        }
        .domain-bar-label {
            color: #32c5d2;
            font-weight: 600;
            white-space: nowrap;
        }
        .domain-bar-hint {
            color: #b0b6bb;
            font-size: 11px;
            white-space: nowrap;
        }
        .domain-bar #domain-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        /* 客户端教程卡片：不同代理软件之间的清晰边界 */
        ol.client-card {
            background: #ffffff;
            border: 1px solid #e4e9ee;
            border-left: 4px solid #32c5d2;
            border-radius: 6px;
            padding: 10px 22px 4px 40px;
            margin: 0 0 18px 0;
            box-shadow: 0 1px 3px rgba(50, 197, 210, 0.06);
        }
        .client-card-title {
            display: block;
            font-size: 16px;
            font-weight: 700;
            color: #32c5d2;
            margin: 6px 0 8px -18px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #dce3e8;
            letter-spacing: 0.3px;
        }
        /* 卡片间的 <hr> 分隔线已由卡片边距取代，隐藏避免双重分隔 */
        .tab-content hr { display: none; }
        /* 4 步标准教程：行内步骤标签（方案 A） */
        ol.client-card > li .step-label {
            color: #32c5d2;
            font-weight: 700;
            margin-right: 2px;
            white-space: nowrap;
        }
        /* 订阅地址：单行（标签 + 输入框 + 复制按钮） */
        .sub-link-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0 14px 0;
            flex-wrap: nowrap;
        }
        .sub-link-row .sub-link-label {
            font-size: 13px;
            font-weight: 600;
            color: #32c5d2;
            letter-spacing: 0.5px;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .sub-link-row input.sub-link-input.form-control {
            flex: 1 1 auto;
            min-width: 60px;
            font-size: 15px;
            font-weight: 600;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            color: #32c5d2;
            background: transparent;
            border: none;
            border-bottom: 2px solid #32c5d2;
            border-radius: 0;
            box-shadow: none;
            padding: 6px 4px;
            margin: 0;
            letter-spacing: 0.3px;
        }
        .sub-link-row input.sub-link-input.form-control:focus {
            border-color: #32c5d2;
            box-shadow: none;
            outline: none;
        }
        .sub-link-copy {
            flex-shrink: 0;
            background: #32c5d2;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background .2s;
        }
        .sub-link-copy:hover { background: #28a8b4; }
        .sub-link-copy:active { background: #1f8e99; }
    </style>
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <div class="row">
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-body">

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-blue bold">使用教程 - {{trans('home.subscribe_address')}}</span>
                              -  <a href="javascript:exchangeSubscribe();" class="btn green">重置订阅码</a>
                        </div>
                        <div class="actions">

                        </div>
                    </div>
                    @if(Auth::user()->subscribe->status)
                            <div class="portlet-body">
                                <div class="mt-clipboard-container">
                                    <!-- Song -->
                                    <div class="alert alert-danger">
                                        <p></p>
                                    </div>

                                    @if(!empty($subscribeDomains))
                                    <div class="domain-bar">
                                        <span class="domain-bar-label">🌐 订阅域名</span>
                                        <div id="domain-selector"></div>
                                        <span class="domain-bar-hint">不可达 ❌ 时点击切换其他域名</span>
                                    </div>
                                    @endif
                                    <div class="tabbable-line">
                                        <ul class="nav nav-tabs ">
                                            <li>
                                                <a href="#tools1" data-toggle="tab"> <i class="fa fa-apple"></i> Mac </a>
                                            </li>
                                            <li class="active">
                                                <a href="#tools2" data-toggle="tab"> <i class="fa fa-windows"></i> Windows </a>
                                            </li>
                                            <li>
                                                <a href="#tools3" data-toggle="tab"> <i class="fa fa-linux"></i> Linux </a>
                                            </li>
                                            <li>
                                                <a href="#tools4" data-toggle="tab"> <i class="fa fa-apple"></i> iOS </a>
                                            </li>
                                            <li>
                                                <a href="#tools5" data-toggle="tab"> <i class="fa fa-android"></i> Android </a>
                                            </li>
                                            <li>
                                                <a href="#tools6" data-toggle="tab"> <i class="fa fa-wifi"></i> Router </a>
                                            </li>
                                        </ul>
                                        <div class="tab-content" style="font-size:16px;">
                                            <div class="tab-pane" id="tools1">
                                                <ol class="client-card"><span class="client-card-title">v2rayN</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：<a href="https://dl.v2rayn.co/apps/v2rayn/7.22.7/v2rayN-macos-arm64.dmg" target="_blank">点此下载 v2rayN（Apple Silicon / M 系列芯片）</a>，打开 dmg，将 <code>v2rayN</code> 拖入「应用程序」文件夹完成安装。<br><strong style="color:#e7505a;">重要</strong>：安装后<strong>首次启动前</strong>，必须先打开「终端」（Terminal）执行以下命令解除 macOS 隔离属性，否则无法启动：<pre style="background:#f5f5f5;padding:8px 12px;border-radius:4px;margin:6px 0;"><code>xattr -cr /Applications/v2rayN.app</code></pre></li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：启动 v2rayN，软件界面 - 订阅 - 订阅设置 - 添加 - 备注随意 - 地址：<strong>复制上方订阅地址</strong> - 确定 - 返回软件界面 - 订阅 - 更新订阅。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：软件界面 - 右键任意节点 - 设为活动的服务器； v2rayN 软件界面 - 代理 - 自动设置系统代理；打开浏览器上网吧。</li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<span style="color:#999;">敬请期待</span></li>
                                                </ol>
                                                <hr>
                                                <ol class="client-card"><span class="client-card-title">v2rayU 不再推荐</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：<a href="/clients/V2rayU-64.dmg" target="_blank">Intel 芯片</a> / <a href="/clients/V2rayU-arm64.dmg" target="_blank">Apple M 芯片</a></li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：v2rayU 图标 - Subscription - 输入订阅 URL</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：v2rayU - Server - 选择节点 - turn v2ray-core on</li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<span style="color:#999;">敬请期待</span></li>
                                                </ol>
                                                <hr>
                                                <ol class="client-card"><span class="client-card-title">v2rayA 暂停使用</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：一键安装 homebrew（如已安装可略过）<a href="/article?id=53" target="_blank">教程</a>，再一键安装 v2rayA <a href="/article?id=55" target="_blank">教程</a></li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：<span style="color:#999;">详见上方教程</span></li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：<span style="color:#999;">详见上方教程</span></li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<a href="/article?id=55" target="_blank">安装 v2rayA 教程</a></li>
                                                </ol>
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksX-NG-R8-1.4.4.dmg')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 点击状态栏纸飞机 -> 服务器 -> 编辑订阅 </li>
                                                    <li> 点击窗口左下角 “+”号 新增订阅，完整复制本页上方“订阅服务”处地址，然后将其粘贴至“订阅地址”栏 </li>
                                                    <li> 点击纸飞机 -> 服务器 -> 手动更新订阅 </li>
                                                    <li> 点击纸飞机 -> 服务器，选定合适服务器 </li>
                                                    <li> 点击纸飞机 -> 打开Shadowsocks </li>
                                                    <li> 点击纸飞机 -> 全局模式 </li>
                                                    <li> 打开系统偏好设置 -> 网络，在窗口左侧选定显示为“已连接”的网络，点击右下角“高级...” </li>
                                                    <li> 切换至“代理”选项卡，勾选“自动代理配置”和“不包括简单主机名”，点击右下角“好”，再次点击右下角“应用” </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane active" id="tools2">
                                                <ol class="client-card"><span class="client-card-title">v2rayN</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：<a href="https://dl.v2rayn.co/apps/v2rayn/7.22.7/v2rayN-windows-64.zip" target="_blank">点此下载 V2rayN</a>，解压缩后右键以管理员身份运行 <code>V2rayN.exe</code>。</li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：双击任务栏右下角 <code>V2rayN</code> 图标 - 软件界面 - 订阅 - 订阅设置 - 添加 - 备注随意 - 地址：<strong>复制上方订阅地址</strong> - 确定 - 返回软件界面 - 订阅 - 更新订阅。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：软件界面 - 右键任意节点 - 设为活动的服务器； v2rayN 软件界面 - 代理 - 自动设置系统代理；打开浏览器上网吧。</li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<a href="/article?id=47">没看懂？点我图文教程</a></li>
                                                </ol>


                                            </div>
                                            <div class="tab-pane" id="tools3">
                                                <ol class="client-card"><span class="client-card-title">Qv2ray</span>
                                                </ol>
                                                <hr>
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/Shadowsocks-qt5-3.0.1.zip')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击状态栏小飞机，找到服务器 -> 编辑订阅，复制黏贴订阅地址 </li>
                                                    <li> 更新订阅设置即可 </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane" id="tools4">
                                                <ol class="client-card"><span class="client-card-title">onexray</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：在<code>非国区</code>苹果商店搜索 <code>onexray</code>，或 <a href="https://apps.apple.com/us/app/onexray/id6745748773" target="_blank">点此下载 onexray</a>（免费），安装。</li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：打开 onexray - 进入订阅/分组设置 - 添加订阅 - 地址：<strong>复制上方订阅地址</strong> - 更新订阅。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：选择任意节点 - 开启主开关 - 打开浏览器上网吧。<small> *第一次使用会提示是否允许添加 VPN 配置，点击允许。</small></li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<span style="color:#999;">敬请期待</span></li>
                                                </ol>
                                                <hr>
                                                <ol class="client-card"><span class="client-card-title">Happ Proxy Utility</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：在<code>非国区</code>苹果商店搜索 <code>Happ</code>，或 <a href="https://apps.apple.com/us/app/happ-proxy-utility/id6504287215" target="_blank">点此下载 Happ Proxy Utility</a>（免费），安装。</li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：打开 Happ - 添加订阅 - 地址：<strong>复制上方订阅地址</strong> - 更新订阅。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：选择任意节点 - 开启主开关 - 打开浏览器上网吧。<small> *第一次使用会提示是否允许添加 VPN 配置，点击允许。</small></li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<span style="color:#999;">敬请期待</span></li>
                                                </ol>
                                                <hr>
                                                <ol class="client-card"><span class="client-card-title">Sing-Box</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}?app=singbox" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：在<code>美区</code>苹果商店搜索 <code>sing-box</code>（免费）安装。</li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：打开 sing-box - Profiles - 新建 - Type 选择 <code>Remote</code> - 填入订阅 URL：<strong>复制上方订阅地址</strong> - 保存 - 返回首页点击更新。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：选中刚添加的 Profile - 在 Dashboard 开启主开关 - 选择可用节点。<small> *第一次使用会提示是否允许添加 VPN 配置，点击允许。</small></li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<span style="color:#999;">敬请期待</span></li>
                                                </ol>
                                                <hr>
                                                <ol class="client-card"><span class="client-card-title">Shadowrocket 3.99$</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：在<code>非国区</code>苹果商店搜索 <code>shadowrocket</code> 购买安装。<small> *在帮助中心页面提供了免费的 Apple Store 账号。</small></li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：打开 <code>Shadowrocket</code> - 点击右上角 <code>＋</code> - 类型：<code>Subscribe</code> - URL：<strong>复制上方订阅地址</strong> - 备注随意 - 完成，此时应已获取节点。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：选择任意节点 - 开启节点 - 打开浏览器上网吧。<small> *第一次使用会提示是否允许 Shadowrocket 使用 VPN，点击 ALLOW。</small></li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<a href="/article?id=48">没看懂？点我查看图文教程</a></li>
                                                </ol>
                                                <hr>
                                                {{-- [PAUSED 2026-06-21] Loon 订阅已停用(配置严谨性待评估, 担心被墙). 恢复: 移除本注释
                                                <ol class="client-card"><span class="client-card-title">Loon 7.99$</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}?app=loon&vless=128&ss=64&vmess=64" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li> 在<code>美区</code>苹果商店 搜索 <code>Loon</code> 7.99$   </li>
                                                </ol>
                                                <hr>
                                                --}}
                                                {{-- [PAUSED 2026-06-21] Quantumult X 订阅已停用(配置严谨性待评估, 担心被墙). 恢复: 移除本注释
                                                <ol class="client-card"><span class="client-card-title">Quantumult X</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}?format=quanx-b64&vless=128&ss=64&vmess=64" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li> 在 App Store 登录<code>非国区</code> Apple ID，搜索 <code>Quantumult X</code> 下载安装</li>
                                                    <li> 点击下方按钮一键导入节点配置，或复制上方链接后在 App 内手动添加 <a id="quanx-import-link" href="quantumult-x:///update-configuration?remote-resource={{ urlencode($link.'?format=quanx-b64&vless=128&ss=64&vmess=64') }}" class="btn green">一键导入</a></li>
                                                    <li> 在 App 首页点击右下角<code>风车</code>图标，展开"节点"列表并选择可用节点</li>
                                                    <li> 开启顶部<code>主开关</code>即可使用</li>
                                                </ol>
                                                <hr>
                                                --}}
                                                <!-- <ol>【SS-R 教程】
                                                    <li> 1 下载软件：推荐使用 shadowrocket </li>
                                                    <li> 2 下载软件：网站帮助中心有提供用于下载 shadowrocket的 苹果商店的账号和密码。请务必注意此商店账号米处吗，只能用于登录苹果商店，不能用户登录设置中的appleid！ </li>
                                                    <li> 3 下载软件：登录苹果商店后，搜索shadowrocket 安装（原价20元，用本站提供的免费账号密码下载免费） </li>
                                                    <li> 打开 Shadowrocket，点击右上角 “+”号 添加节点，类型选择 Subscribe </li>
                                                    <li> 完整复制本页上方 “订阅服务” 处地址，将其粘贴至 “URL”栏，点击右上角 “完成” </li>
                                                    <li> 左划新增的服务器订阅，点击 “更新” </li>
                                                    <li> 选定合适服务器节点，点击右上角连接开关，屏幕上方状态栏出现“VPN”图标 </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane" id="tools5">
                                                <ol class="client-card"><span class="client-card-title">v2rayNG</span>
                                                    <div class="sub-link-row">
                                                        <span class="sub-link-label">📡 订阅地址</span>
                                                        <input type="text" class="form-control sub-link-input" value="{{$link}}" readonly onclick="this.select();" />
                                                        <button type="button" class="sub-link-copy" onclick="copySubLink(this)">复制</button>
                                                    </div>
                                                    <li><strong class="step-label">① 安装软件</strong>：<a href="/public/clients/v2rayNG_arm64-v8a.apk">点此下载 v2rayNG</a> - 安装 - 打开软件。</li>
                                                    <li><strong class="step-label">② 添加订阅</strong>：软件界面 - 右滑 - 订阅设置 - 点击右上角 <code>＋</code> - 备注随意 - 地址：<strong>复制上方订阅地址</strong> - 返回主界面 - 点击右上角打开菜单 - 更新订阅。</li>
                                                    <li><strong class="step-label">③ 使用节点</strong>：选择一个节点 - 点击右下角小飞机 - 开始使用吧。</li>
                                                    <li><strong class="step-label">④ 图文教程</strong>：<a href="/article?id=58">图文教程</a></li>
                                                </ol>
                                                <hr>
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksRR-3.5.1.1.apk')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击左上角的shadowsocksR进入配置文件页，点击右下角的“+”号，点击“添加/升级SSR订阅”，完整复制本页上方“订阅服务”处地址，填入订阅信息并保存 </li>
                                                    <li> 选中任意一个节点，返回软件首页 </li>
                                                    <li> 在软件首页处找到“路由”选项，并将其改为“绕过局域网及中国大陆地址” </li>
                                                    <li> 点击右上角的小飞机图标进行连接，提示是否添加（或创建）VPN连接，点同意（或允许） </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane" id="tools6">
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/SSTap-beta-setup-1.0.9.7.zip')}}" target="_blank">点击此处</a>下载客户端并安装 </li>
                                                    <li> 打开 SSTap，选择 <i class="fa fa-cog"></i> -> SSR订阅 -> SSR订阅管理，添加订阅地址 </li>
                                                    <li> 添加完成后，再次选择 <i class="fa fa-cog"></i> - SSR订阅 - 手动更新SSR订阅，即可同步节点列表。</li>
                                                    <li> 在代理模式中选择游戏或「不代理中国IP」，点击「连接」即可加速。</li>
                                                    <li> 需要注意的是，一旦连接成功，客户端会自动缩小到任务栏，可在设置中关闭。</li>
                                                </ol> -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                    @else
                        <div style="text-align: center;"><h3>{{trans('home.subscribe_baned')}}</h3>
                        <br>您的订阅受到保护，24小时内不同订阅请求IP>32次；旧的订阅链接已失效，请使用新的订阅链接！
                        <br><br><button type="button" class="btn btn-big red btn-outline" onclick="reActiveSubscribe()">解除保护</button><br>*点击 解除保护 软件中的旧的节点会失效，请获取新的节点使用。</div>
                    @endif
                </div>
            </div>
        </div>

                <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script src="/assets/global/plugins/clipboardjs/clipboard.min.js" type="text/javascript"></script>
    <script src="/assets/pages/scripts/components-clipboard.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/jquery-qrcode/jquery.qrcode.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/jquery-ui/jquery-ui.min.js" type="text/javascript"></script>

    <script type="text/javascript">
        // === 订阅域名选择器 ===
        // 重要：域名列表必须用 Blade 的 json 指令直接输出为 JS 字面量，
        // 不能先用花括号输出再用 JSON.parse 包裹。因为 Blade 花括号输出会经过
        // HTML 转义，把 JSON 中的双引号变成 &quot;，JSON.parse 会抛 SyntaxError，
        // 整个自执行函数中断，域名选择器就渲染不出来。
        (function() {
            var extraDomains = @json($subscribeDomains);
            if (!extraDomains || extraDomains.length === 0) return;

            var baseDomain = '{{ $linkBase }}';
            var currentDomain = baseDomain;
            var _selectedDomainIdx = 0;

            // 构建选项列表：主域名 + 多订阅域名
            var allDomains = [baseDomain];
            for (var i = 0; i < extraDomains.length; i++) {
                if (extraDomains[i] !== baseDomain) {
                    allDomains.push(extraDomains[i]);
                }
            }

            // 渲染选择器（紧凑型小按钮，配合 .domain-bar 工具条）
            var html = '';
            for (var i = 0; i < allDomains.length; i++) {
                var d = allDomains[i];
                var selected = (i === 0);
                var isDefault = (d === baseDomain);
                // 简短显示：只取域名部分
                var shortLabel = d.replace(/^https?:\/\//, '');
                var displayLabel = isDefault ? shortLabel + '（默认）' : shortLabel;
                var selectedBg = selected ? '#32c5d2' : 'transparent';
                var selectedColor = selected ? '#fff' : '#666';
                var selectedBorder = selected ? '#32c5d2' : '#dcdfe2';
                var selectedWeight = selected ? '600' : '400';
                html += '<div id="domain-btn-' + i + '" class="domain-btn" data-domain="' + d + '" ';
                html += 'style="display:flex;align-items:center;gap:5px;padding:3px 10px;border:1px solid ' + selectedBorder + ';border-radius:4px;cursor:pointer;background:' + selectedBg + ';color:' + selectedColor + ';font-size:12px;font-weight:' + selectedWeight + ';transition:all .2s;user-select:none;" ';
                html += 'onclick="switchDomain(\'' + d + '\', ' + i + ')" ';
                html += 'onmouseenter="domainHover(' + i + ', true)" onmouseleave="domainHover(' + i + ', false)">';
                html += '<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">' + displayLabel + '</span>';
                html += '<img id="domain-status-' + i + '" src="/check.png" width="12" height="12" style="opacity:0.3;flex-shrink:0;" />';
                html += '</div>';
            }
            document.getElementById('domain-selector').innerHTML = html;

            // 可达性检测
            for (var i = 0; i < allDomains.length; i++) {
                (function(idx, domain) {
                    var img = new Image();
                    img.onload = function() {
                        var el = document.getElementById('domain-status-' + idx);
                        el.src = domain + '/check.png';
                        el.style.opacity = '1';
                        el.style.filter = (idx === _selectedDomainIdx) ? 'brightness(0) invert(1)' : 'none';
                    };
                    img.onerror = function() {
                        var el = document.getElementById('domain-status-' + idx);
                        el.src = '/error.png';
                        el.style.opacity = '1';
                        el.style.filter = (idx === _selectedDomainIdx) ? 'brightness(0) invert(1)' : 'none';
                    };
                    img.src = domain + '/check.png';
                })(i, allDomains[i]);
            }
        })();

        var _allDomains = null; // 延迟赋值

        // 切换域名高亮 + 替换所有 sub-link-input 中的域名
        var _subLinkBaseDomain = '{{ $linkBase }}';
        var _selectedDomainIdx = 0;
        function switchDomain(newDomain, idx) {
            var oldDomain = _subLinkBaseDomain;
            _subLinkBaseDomain = newDomain;

            // 更新所有按钮的高亮状态
            var btns = document.querySelectorAll('.domain-btn');
            for (var j = 0; j < btns.length; j++) {
                var isSelected = (j === idx);
                btns[j].style.background = isSelected ? '#32c5d2' : 'transparent';
                btns[j].style.color = isSelected ? '#fff' : '#666';
                btns[j].style.borderColor = isSelected ? '#32c5d2' : '#dcdfe2';
                btns[j].style.fontWeight = isSelected ? '600' : '400';
                // 选中状态图标变白，未选中恢复原色
                var statusImg = document.getElementById('domain-status-' + j);
                if (statusImg) {
                    statusImg.style.filter = isSelected ? 'brightness(0) invert(1)' : 'none';
                }
            }
            _selectedDomainIdx = idx;

            // 替换所有 input.sub-link-input
            var inputs = document.querySelectorAll('input.sub-link-input');
            for (var i = 0; i < inputs.length; i++) {
                inputs[i].value = inputs[i].value.replace(oldDomain, newDomain);
            }
            // 替换 Quantumult X 一键导入链接
            var importLink = document.getElementById('quanx-import-link');
            if (importLink) {
                importLink.href = importLink.href.replace(encodeURIComponent(oldDomain), encodeURIComponent(newDomain));
            }
        }

        // 鼠标悬停效果
        function domainHover(idx, entering) {
            var btn = document.getElementById('domain-btn-' + idx);
            if (!btn) return;
            if (idx === _selectedDomainIdx) return; // 选中状态不受悬停影响
            if (entering) {
                btn.style.borderColor = '#32c5d2';
                btn.style.background = '#f0fcfd';
            } else {
                btn.style.borderColor = '#dcdfe2';
                btn.style.background = 'transparent';
            }
        }
    </script>

    <script type="text/javascript">
        // 复制订阅地址（配套 .sub-link-copy 按钮）
        function copySubLink(btn) {
            var input = btn.parentNode.querySelector('input.sub-link-input');
            if (!input) return;
            input.removeAttribute('readonly');          // 临时可写，部分浏览器需可编辑才能选中
            input.focus();
            input.select();
            input.setSelectionRange(0, 99999);
            input.setAttribute('readonly', '');
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            // 退路：Clipboard API（HTTPS 或 localhost）
            if (!ok && navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(function () {
                    layer.msg('订阅地址已复制', {time: 1000});
                }, function () {
                    layer.msg('复制失败，请手动选中复制', {time: 1500});
                });
                return;
            }
            layer.msg(ok ? '订阅地址已复制' : '复制失败，请手动选中复制', {time: ok ? 1000 : 1500});
        }
    </script>

    <script type="text/javascript">
        // 在线安装警告提示
        function onlineInstallWarning() {
            layer.msg('仅限在Safari浏览器下有效', {time:1000});
        }
        //
        // 节点流量监控 song
        function nodeMonitor(id) {
            window.location.href = '/nodeMonitor?id=' + id ;
        }
    </script>

    <script type="text/javascript">
        // 更换订阅地址
        function exchangeSubscribe() {
            layer.confirm('更换订阅地址将导致：<br>1.旧地址立即失效；<br>2.节点密码被更改；', {icon: 7, title:'警告'}, function(index) {
                $.post("/exchangeSubscribe", {_token:'{{csrf_token()}}'}, function (ret) {
                    layer.msg(ret.message, {time:1000}, function () {
                        if (ret.status == 'success') {
                            window.location.reload();
                        }
                    });
                });

                layer.close(index);
            });
        }

        // 启用禁用用户的订阅
        function reActiveSubscribe() {
            $.post("/reActiveSubscribe", {_token:'{{csrf_token()}}'}, function(ret) {
                layer.msg(ret.message, {time:1000}, function() {
                    window.location.reload();
                });
            });
        }
    </script>

@endsection
