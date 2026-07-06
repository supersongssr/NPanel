@extends('user.layouts')
@section('css')
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
                                        <p>支持技术：ss ssr vmess vless trojan hysteria2 .您可以修改 ?ss=64&vmess=64&vless=64&trojan=64&hysteria2=64 的数值，来控制获取节点的数量。 =0时为不获取相应节点。
                                            <br> 如部分路由器不支持ss节点，获取会报错，可设置： ?ss=0&vmess=64&vless=64&trojan=64&hysteria2=64 即不获取ss节点。</p>
                                    </div>

                                    @if(!empty($subscribeDomains))
                                    <div class="portlet light" style="margin-bottom:10px;">
                                        <div class="portlet-title">
                                            <div class="caption">
                                                <span class="caption-subject font-green bold">选择订阅域名</span>
                                                <span style="margin-left:8px;color:#999;font-size:12px;">切换后下方所有订阅链接自动更新</span>
                                            </div>
                                        </div>
                                        <div class="portlet-body" style="padding-top:0;">
                                            <div id="domain-selector" style="margin-bottom:0;"></div>
                                            <div class="help-block" style="margin-top:6px;margin-bottom:0;">✅ 可用 &nbsp; ❌ 不可达（可能被墙，请选择其他域名）</div>
                                        </div>
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
                                                <ol>【 v2rayN 】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li>下载软件：<a href="https://dl.v2rayn.co/apps/v2rayn/7.22.7/v2rayN-macos-arm64.dmg" target="_blank">点此下载 v2rayN（Apple Silicon / M 系列芯片）</a>，打开 dmg，将 <code>v2rayN</code> 拖入「应用程序」文件夹完成安装</li>
                                                    <li><strong style="color:#e7505a;">重要</strong>：安装后<strong>首次启动前</strong>，必须先打开「终端」（Terminal）执行以下命令解除 macOS 隔离属性，否则无法启动：
                                                        <pre style="background:#f5f5f5;padding:8px 12px;border-radius:4px;margin:6px 0;"><code>xattr -cr /Applications/v2rayN.app</code></pre>
                                                    </li>
                                                    <li>启动 v2rayN，在软件界面 - 订阅 - 订阅设置 - 添加 - 备注随意 - 地址：<code class="sub-link-input">{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 确定 - 返回软件界面 - 订阅 - 更新订阅</li>
                                                    <li>软件界面 - 右键任意节点 - 设为活动的服务器； v2rayN 软件界面 - 代理 - 自动设置系统代理；打开浏览器上网吧</li>
                                                </ol>
                                                <hr>
                                                <ol>【 v2rayU 不再推荐 】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li>安装软件:<a href="/clients/V2rayU-64.dmg" target="_blank">Intel芯片 </a> , <a href="/clients/V2rayU-arm64.dmg" target="_blank">AppleM芯片 </a></li>
                                                    <li>添加订阅: v2rayU图标 - Subscription - 输入订阅URL </li>
                                                    <li>使用节点: v2rayU - Server - 选择节点,  v2rayU - turn v2ray-core on </li>
                                                </ol>
                                                <hr>
                                                <ol>【 v2rayA 暂停使用 】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li>一键安装homebrew (如已安装homebrew请略过): <a href="/article?id=53" target="_blank">安装homebrew教程</a></li>
                                                    <li>一键安装v2rayA : <a href="/article?id=55" target="_blank">安装v2rayA教程</a></li>
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
                                                <ol>【 v2rayN 】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li> <a href="https://dl.v2rayn.co/apps/v2rayn/7.22.7/v2rayN-windows-64.zip" target="_blank">点此下载V2rayN</a> 解压缩 - 右键以管理员身份运行 <code>V2rayN.exe</code></li>
                                                    <li> 双击任务栏右下角 <code>V2rayN</code>图标 - 在软件界面中 - 订阅 - 订阅设置 - 添加 - 备注随意 - 地址：<code class="sub-link-input">{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 确定 - 返回软件界面 - 订阅 - 更新订阅  </li>
                                                    <li> 软件界面 - 右键任意节点 - 设为活动的服务器 ； v2rayN软件界面 - 代理 - 自动设置系统代理；打开浏览器上网吧 </li>
                                                    <li> <a href="/article?id=47">没看懂？点我图文教程</a></li>
                                                </ol>


                                            </div>
                                            <div class="tab-pane" id="tools3">
                                                <ol>【Qv2ray】
                                                </ol>
                                                <hr>
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/Shadowsocks-qt5-3.0.1.zip')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击状态栏小飞机，找到服务器 -> 编辑订阅，复制黏贴订阅地址 </li>
                                                    <li> 更新订阅设置即可 </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane" id="tools4">
                                                <ol>【 onexray 】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li> 在<code>非国区</code>苹果商店 搜索 <code>onexray</code> 或 <a href="https://apps.apple.com/us/app/onexray/id6745748773" target="_blank">点此下载 onexray</a> 免费 - 安装  </li>
                                                    <li> 打开 onexray - 进入订阅/分组设置 - 添加订阅 - 地址：<code class="sub-link-input">{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 更新订阅  </li>
                                                    <li> 选择任意节点 - 开启主开关 - 打开浏览器上网吧 <small>*第一次使用，会提示是否允许添加 VPN 配置，点击允许</small></li>
                                                </ol>
                                                <hr>
                                                <ol>【 Happ Proxy Utility 】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li> 在<code>非国区</code>苹果商店 搜索 <code>Happ</code> 或 <a href="https://apps.apple.com/us/app/happ-proxy-utility/id6504287215" target="_blank">点此下载 Happ Proxy Utility</a> 免费 - 安装  </li>
                                                    <li> 打开 Happ - 添加订阅 - 地址：<code class="sub-link-input">{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 更新订阅  </li>
                                                    <li> 选择任意节点 - 开启主开关 - 打开浏览器上网吧 <small>*第一次使用，会提示是否允许添加 VPN 配置，点击允许</small></li>
                                                </ol>
                                                <hr>
                                                <ol>【Sing-Box】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?app=singbox&vless=128&ss=64&vmess=64" />
                                                    <li> 在<code>美区</code>苹果商店 搜索 <code>sing-box</code> 免费 - 安装  </li>
                                                </ol>
                                                <hr>
                                                <ol>【Shadowrocket 3.99$】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?rocket=64" />
                                                    <li> 在<code>非国区</code>苹果商店 搜索 <code>shadowrocket</code> 购买 - 安装 <small>*在帮助中心页面提供了免费的applestore账号*</small></li>
                                                    <li> 打开<code>shadowrocket</code> - 点击右上角<code>+</code>号，类型: <code>Subscribe</code> - URL:<code class="sub-link-input">{{$link}}?rocket=64</code><small>*注意，小火箭的订阅链接很独特*</small> - 备注随意 - 完成 - 此时应已获取节点</li>
                                                    <li> 选择任意节点 - 开启节点 - 打开浏览器上网吧 <small>*第一次使用，会提示是否允许shadowrocket使用VPN,点击ALLOW</small></li>
                                                    <li> <a href="/article?id=48">没看懂？点我查看图文教程</a></li>
                                                </ol>
                                                <hr>
                                                {{-- [PAUSED 2026-06-21] Loon 订阅已停用(配置严谨性待评估, 担心被墙). 恢复: 移除本注释
                                                <ol>【Loon 7.99$】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?app=loon&vless=128&ss=64&vmess=64" />
                                                    <li> 在<code>美区</code>苹果商店 搜索 <code>Loon</code> 7.99$   </li>
                                                </ol>
                                                <hr>
                                                --}}
                                                {{-- [PAUSED 2026-06-21] Quantumult X 订阅已停用(配置严谨性待评估, 担心被墙). 恢复: 移除本注释
                                                <ol>【Quantumult X】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?format=quanx-b64&vless=128&ss=64&vmess=64" />
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
                                                <ol>【v2rayNG】
                                                    <input type="text" class="form-control sub-link-input" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li> <a href="https://dl.v2rayng.org/releases/latest/v2rayNG_2.2.5-fdroid_arm64-v8a.apk">点此下载v2rayNG </a> - 安装 - 打开软件</li>
                                                    <li> 软件界面 - 右滑 - 订阅设置 - 点击右上角 <code>+</code> - 备注随意 - 地址：<code class="sub-link-input">{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 返回主界面 - 点击右上角打开菜单 - 更新订阅 </li>
                                                    <li> 选择一个节点 - 点击右下角小飞机 - 开始使用吧。 </li>
                                                    <li> <a href="/article?id=58">图文教程</a></li>
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

            // 渲染选择器
            var html = '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;">';
            for (var i = 0; i < allDomains.length; i++) {
                var d = allDomains[i];
                var selected = (i === 0);
                var isDefault = (d === baseDomain);
                var label = isDefault ? d + '（默认）' : d;
                // 简短显示：只取域名部分
                var shortLabel = d.replace(/^https?:\/\//, '');
                var displayLabel = isDefault ? shortLabel + '（默认）' : shortLabel;
                var selectedBg = selected ? '#32c5d2' : '#fff';
                var selectedColor = selected ? '#fff' : '#555';
                var selectedBorder = selected ? '#32c5d2' : '#e0e0e0';
                var selectedWeight = selected ? '600' : '400';
                html += '<div id="domain-btn-' + i + '" class="domain-btn" data-domain="' + d + '" ';
                html += 'style="display:flex;align-items:center;gap:8px;padding:10px 16px;border:2px solid ' + selectedBorder + ';border-radius:6px;cursor:pointer;background:' + selectedBg + ';color:' + selectedColor + ';font-size:13px;font-weight:' + selectedWeight + ';transition:all .2s;user-select:none;" ';
                html += 'onclick="switchDomain(\'' + d + '\', ' + i + ')" ';
                html += 'onmouseenter="domainHover(' + i + ', true)" onmouseleave="domainHover(' + i + ', false)">';
                html += '<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">' + displayLabel + '</span>';
                html += '<img id="domain-status-' + i + '" src="/check.png" width="14" height="14" style="opacity:0.3;flex-shrink:0;" />';
                html += '</div>';
            }
            html += '</div>';
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
                btns[j].style.background = isSelected ? '#32c5d2' : '#fff';
                btns[j].style.color = isSelected ? '#fff' : '#555';
                btns[j].style.borderColor = isSelected ? '#32c5d2' : '#e0e0e0';
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
            // 替换 code.sub-link-input 内的文字
            var codes = document.querySelectorAll('code.sub-link-input');
            for (var i = 0; i < codes.length; i++) {
                codes[i].innerText = codes[i].innerText.replace(oldDomain, newDomain);
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
                btn.style.borderColor = '#e0e0e0';
                btn.style.background = '#fff';
            }
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
