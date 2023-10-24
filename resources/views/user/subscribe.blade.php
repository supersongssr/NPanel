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
                                        <p>支持技术：ss ssr vmess vless trojan .您可以修改 ?ss=64&vmess=64&vless=64&trojan=64 的数值，来控制获取节点的数量。 =0时为不获取相应节点。
                                            <br> 如部分路由器不支持ss节点，获取会报错，可设置： ?ss=0&vmess=64&vless=64&trojan=64 即不获取ss节点。</p>
                                    </div>
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
                                                <a href="#tools6" data-toggle="tab"> <i class="fa fa-gamepad"></i> Games </a>
                                            </li>
                                        </ul>
                                        <div class="tab-content" style="font-size:16px;">
                                            <div class="tab-pane" id="tools1">
                                                <ol>【 v2rayA 】
                                                    <input type="text" id="mt-target-1" class="form-control" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li>1. 一键安装homebrew (如已安装homebrew请略过): <a href="/article?id=53" target="_blank">安装homebrew教程</a></li>
                                                    <li>2. 一键安装v2rayA : <a href="/article?id=55" target="_blank">安装v2rayA教程</a></li>
                                                    <li>3. 使用 v2rayA : <a href="https://zhuanlan.zhihu.com/p/414998586" target="_blank">如何使用v2rayA</a></li>
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
                                                    <input type="text" id="mt-target-1" class="form-control" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li> <a href="/clients/v2rayN-Core.zip" target="_blank">点此下载V2rayN</a> 解压缩 - 右键以管理员身份运行 <code>V2rayN.exe</code></li>
                                                    <li> 双击任务栏右下角 <code>V2rayN</code>图标 - 在软件界面中 - 订阅 - 订阅设置 - 添加 - 备注随意 - 地址：<code>{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 确定 - 返回软件界面 - 订阅 - 更新订阅  </li>
                                                    <li> 软件界面 - 右键任意节点 - 设为活动的服务器 ； 右键点击右下角软件图标 - 代理 - 自动设置系统代理；打开浏览器上网吧 </li>
                                                    <li> <a href="/article?id=47">没看懂？点我图文教程</a></li>
                                                </ol>
                                                <hr>
                                                <!-- <ol>【 Clash 】
                                                    <input type="text" id="mt-target-1" class="form-control" value="https://sub.1upsrss.top/sub?target=clash&url={{$link}}?v2ray=64&scv=true" />
                                                    <li> 订阅地址 <code>https://sub.1upsrss.top/sub?target=clash&url={{$link}}?v2ray=64&scv=true</code> </li>
                                                </ol> -->
                                                <hr>
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="https://github.com/shadowsocksrr/shadowsocksr-csharp/releases/download/4.9.2/ShadowsocksR-win-4.9.2.zip" target="_blank">点击此处（V4.9.2版本）</a>下载客户端并启动 </li>
                                                    <li> 运行 ShadowsocksR 文件夹内的 ShadowsocksR.exe </li>
                                                    <li> 右击桌面右下角状态栏（或系统托盘）纸飞机 -> 服务器订阅 -> SSR服务器订阅设置 </li>
                                                    <li> 点击窗口左下角 “Add” 新增订阅，完整复制本页上方 “订阅服务” 处地址，将其粘贴至“网址”栏，点击“确定” </li>
                                                    <li> 右击纸飞机 -> 服务器订阅 -> 更新SSR服务器订阅（不通过代理） </li>
                                                    <li> 右击纸飞机 -> 服务器，选定合适服务器 </li>
                                                    <li> 右击纸飞机 -> 系统代理模式 -> 全局模式 </li>
                                                    <li> 右击纸飞机 -> 代理规则 -> 绕过局域网和大陆 </li>
                                                    <li> 右击纸飞机，取消勾选“服务器负载均衡” </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane" id="tools3">
                                                <ol>【Vmess 教程】
                                                    <li><a href="https://github.com/jiangxufeng/v2rayL">点此查看Qv2ray的教程</a></li>
                                                </ol>
                                                <hr>
                                                <!-- <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/Shadowsocks-qt5-3.0.1.zip')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击状态栏小飞机，找到服务器 -> 编辑订阅，复制黏贴订阅地址 </li>
                                                    <li> 更新订阅设置即可 </li>
                                                </ol> -->
                                            </div>
                                            <div class="tab-pane" id="tools4">
                                                <ol>【Vmess节点 - 小火箭软件 教程】
                                                    <input type="text" id="mt-target-1" class="form-control" value="{{$link}}?rocket=64" />
                                                    <li> 在<code>非国区</code>苹果商店 搜索 <code>shadowrocket</code> 购买 - 安装 <small>*在帮助中心页面提供了免费的applestore账号*</small></li>
                                                    <li> 打开<code>shadowrocket</code> - 点击右上角<code>+</code>号，类型: <code>Subscribe</code> - URL:<code>{{$link}}?rocket=64</code><small>*注意，小火箭的订阅链接很独特*</small> - 备注随意 - 完成 - 此时应已获取节点</li>
                                                    <li> 选择任意节点 - 开启节点 - 打开浏览器上网吧 <small>*第一次使用，会提示是否允许shadowrocket使用VPN,点击ALLOW</small></li>
                                                    <li> <a href="/article?id=48">没看懂？点我查看图文教程</a></li>
                                                </ol>
                                                <hr>
                                                <ol>【Vmess Quan-X 教程】
                                                    <input type="text" id="mt-target-1" class="form-control" value="https://sub.1upsrss.top/sub?target=quanx&url={{$link}}?v2ray=64&emoji=true&fdn=true&list=true" />
                                                    <li> 订阅地址 <code>https://sub.1upsrss.top/sub?target=quanx&url={{$link}}?v2ray=64&emoji=true&fdn=true&list=true</code>
                                                </ol>
                                                <hr>
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
                                                <ol>【Vmess节点 - V2rayNG软件 教程】
                                                    <input type="text" id="mt-target-1" class="form-control" value="{{$link}}?ss=64&vmess=64&vless=64&trojan=64" />
                                                    <li> <a href="/clients/v2rayNG_1.7.1.apk">点此下载v2rayNG v1.7.1</a> - 安装 - 打开软件</li>
                                                    <li> 软件界面 - 右滑 - 订阅设置 - 点击右上角 <code>+</code> - 备注随意 - 地址：<code>{{$link}}?ss=64&vmess=64&vless=64&trojan=64</code> - 返回主界面 - 点击右上角打开菜单 - 更新订阅 </li>
                                                    <li> 选择一个节点 - 点击右下角小飞机 - 开始使用吧。 </li>
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
