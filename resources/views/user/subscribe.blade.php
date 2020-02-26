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
                        <ul class="list-inline">
                            <li>
                                <h4>
                                    <span class="font-blue">账号：</span>
                                    <span class="font-red">{{Auth::user()->username}}</span>
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">状态：</span>
                                    @if ( Auth::user()->status == 0  )
                                        <span class="label label-danger">状态异常 请退出重新登陆</span>
                                    @elseif ( Auth::user()->status == 1 )
                                        <span class="label label-success">正常</span>
                                    @else
                                        <span class="label label-info">异常,请退出登录</span>
                                    @endif
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">{{trans('home.account_expire')}}：</span>
                                    <span class="font-red">@if(date('Y-m-d') > Auth::user()->expire_time) {{trans('home.expired')}} @else {{Auth::user()->expire_time}} @endif</span>
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">等级：</span>
                                    <span class="font-red">Lv.{{Auth::user()->level}}</span>
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">{{trans('home.account_bandwidth_usage')}}：</span>
                                    <span class="font-red">{{flowAutoShow(Auth::user()->u + Auth::user()->d)}}（{{flowAutoShow(Auth::user()->transfer_enable)}}）</span>
                                </h4>
                            </li>
                            @if(Auth::user()->traffic_reset_day)
                            <li>
                                <h4>
                                    <span class="font-blue"> {{trans('home.account_reset_notice', ['reset_day' => Auth::user()->traffic_reset_day])}} </span>
                                </h4>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-blue bold">如何使用？{{trans('home.subscribe_address')}}使用教程！</span>
                        </div>
                        <div class="actions">

                        </div>
                    </div>
                    @if(Auth::user()->subscribe->status)
                            <div class="portlet-body">
                                <div class="mt-clipboard-container">
                                    <!-- Song -->
                                    <div class="alert alert-danger">
                                        <p> VIP用户专享：香港、台湾、日本、新加坡高速节点 + VIP独享节点 + 10Mbps起网速保障 + 1080P网速支持 + Netflix！支持 + 35ms + 服务单优先回复！<br> SR 和 V2ray是不同软件，不同技术呦 :)</p>
                                    </div>
                                    <!-- -->
                                    <a href="javascript:exchangeSubscribe();" class="btn green">
                                        {{trans('home.exchange_subscribe')}}订阅链接防止外泄
                                    </a>
                                    <input type="text" id="mt-target-1" class="form-control" value="{{$link}}?ver=1" />
                                    <a href="javascript:;" class="btn blue mt-clipboard" data-clipboard-action="copy" data-clipboard-target="#mt-target-1">
                                        {{trans('home.copy_subscribe_address')}}SS-R订阅地址
                                    </a>
                                    <input type="text" id="mt-target-2" class="form-control" value="{{$link}}?ver=2" />
                                    <a href="javascript:;" class="btn blue mt-clipboard" data-clipboard-action="copy" data-clipboard-target="#mt-target-2">
                                        {{trans('home.copy_subscribe_address')}}Vmess订阅地址
                                    </a>
                                    
                                    <div class="tabbable-line">
                                        <ul class="nav nav-tabs ">
                                            <li class="active">
                                                <a href="#tools1" data-toggle="tab"> <i class="fa fa-apple"></i> Mac </a>
                                            </li>
                                            <li>
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
                                            <div class="tab-pane active" id="tools1">
                                                <!-- Song-->
                                                <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksX-NG-R8-1.4.4.dmg')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 点击状态栏纸飞机 -> 服务器 -> 编辑订阅 </li>
                                                    <li> 点击窗口左下角 “+”号 新增订阅，完整复制本页上方“订阅服务”处地址，然后将其粘贴至“订阅地址”栏 </li>
                                                    <li> 点击纸飞机 -> 服务器 -> 手动更新订阅 </li>
                                                    <li> 点击纸飞机 -> 服务器，选定合适服务器 </li>
                                                    <li> 点击纸飞机 -> 打开Shadowsocks </li>
                                                    <li> 点击纸飞机 -> 全局模式 </li>
                                                    <li> 打开系统偏好设置 -> 网络，在窗口左侧选定显示为“已连接”的网络，点击右下角“高级...” </li>
                                                    <li> 切换至“代理”选项卡，勾选“自动代理配置”和“不包括简单主机名”，点击右下角“好”，再次点击右下角“应用” </li>
                                                </ol>
                                                <!-- song -->
                                                <hr>
                                                <ol>【Vmess 教程】
                                                    <li> <a href="/clients/V2RayX.app-1.2.0.zip" target="_blank">点击此处</a>下载客户端，解压 </li>
                                                    <li> 将 V2RayX.app 复制到 程序 文件夹，然后点击网站内菜单---节点列表，点击您想要添加的节点名称 </li>
                                                    <li> 目前MAC端节点需要手动添加节点；您可以在节点列表查看到部分V2节点的配置信息；MAC端推荐使用SR呢 </li>
                                                </ol>
                                                <!-- -->
                                            </div>
                                            <div class="tab-pane" id="tools2">
                                                <!-- song -->
                                                <ol>【SS-R 教程】
                                                    <li> <a href="https://github.com/shadowsocksrr/shadowsocksr-csharp/releases/download/4.9.2/ShadowsocksR-win-4.9.2.zip" target="_blank">点击此处（V4.9.2版本）</a>下载客户端并启动 </li>
                                                    <li> 运行 ShadowsocksR 文件夹内的 ShadowsocksR.exe </li>
                                                    <li> 右击桌面右下角状态栏（或系统托盘）纸飞机 -> 服务器订阅 -> SSR服务器订阅设置 </li>
                                                    <li> 点击窗口左下角 “Add” 新增订阅，完整复制本页上方 “订阅服务” 处地址，将其粘贴至“网址”栏，点击“确定” </li>
                                                    <li> 右击纸飞机 -> 服务器订阅 -> 更新SSR服务器订阅（不通过代理） </li>
                                                    <li> 右击纸飞机 -> 服务器，选定合适服务器 </li>
                                                    <li> 右击纸飞机 -> 系统代理模式 -> 全局模式 </li>
                                                    <li> 右击纸飞机 -> 代理规则 -> 绕过局域网和大陆 </li>
                                                    <li> 右击纸飞机，取消勾选“服务器负载均衡” </li>
                                                </ol>
                                                <hr>
                                                <ol>【Vmess 教程】
                                                    <li> <a href="https://github.com/2dust/v2rayN/releases/download/3.5/v2rayN.zip" target="_blank">下载V2rayN软件(网站提供版本为V3.5版本)</a>，下载后解压 （PS:请注意，本版本采用本地端口是10808，普通用户不用理会。如需指定代理，您可以在软件设置中配置您想用的端口。）</li>
                                                    <li> 运行程序v2rayN.exe，双击右下角图标 </li>
                                                    <li> V2Ray软件界面，点击订阅，订阅设置，填写本站的V2Ray订阅地址 </li>
                                                    <li> 点订阅，更新订阅，即可获取本站节点</li>
                                                    <li>右键右下角图标，开启http代理，然后选择一个节点即可使用。</li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools3">
                                                <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/Shadowsocks-qt5-3.0.1.zip')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击状态栏小飞机，找到服务器 -> 编辑订阅，复制黏贴订阅地址 </li>
                                                    <li> 更新订阅设置即可 </li>
                                                </ol>
                                                <hr>
                                                <ol>【Vmess 教程】
                                                    <li>能耍 Linux 的都是大佬了，受我一拜</li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools4">
                                                <ol>【SS-R 教程】
                                                    <li> 1 下载软件：推荐使用 shadowrocket </li>
                                                    <li> 2 下载软件：网站帮助中心有提供用于下载 shadowrocket的 苹果商店的账号和密码。请务必注意此商店账号米处吗，只能用于登录苹果商店，不能用户登录设置中的appleid！ </li>
                                                    <li> 3 下载软件：登录苹果商店后，搜索shadowrocket 安装（原价20元，用本站提供的免费账号密码下载免费） </li>
                                                    <li> 打开 Shadowrocket，点击右上角 “+”号 添加节点，类型选择 Subscribe </li>
                                                    <li> 完整复制本页上方 “订阅服务” 处地址，将其粘贴至 “URL”栏，点击右上角 “完成” </li>
                                                    <li> 左划新增的服务器订阅，点击 “更新” </li>
                                                    <li> 选定合适服务器节点，点击右上角连接开关，屏幕上方状态栏出现“VPN”图标 </li>
                                                </ol>
                                                <hr>
                                                <ol>【Vmess 教程】
                                                    <li> 1 下载软件：推荐您使用 shadowrocket 或者 kitsunebi（均需要在美国商店下载）</li>
                                                    <li> 2 下载软件： 网站帮助中心有提供一些用于下载 shadowrocket的苹果商店账号密码，请注意，此账号密码只能用于登录苹果商店，千万不要登录设置里的appleID。</li>
                                                    <li> 3 添加订阅： 以shadowrocket为例，进入点击右上角+号，类型选择 Subscribe</li>
                                                    <li> 4 添加订阅： 完整复制本页上方“订阅服务”处 Vmess地址，将其粘贴至“URL”栏，点击右上角完成</li>
                                                    <li> 5 获取节点： 右滑或左滑添加的订阅，更新以获取节点。</li>
                                                    <li> 6 Vmess节点设置： Shadowrocket软件，要使用vmess节点，必须将您要使用的节点，点击右边 i ，进入此节点的编辑，然后开启 允许不安全 。注意，使用哪个节点，就需要单独设置哪个节点的 允许不安全（节点采用了自签证书）（WIN 安卓等平台不存在此问题）</li>
                                                    <li> 7 连接使用： 接下来就可以正常使用Vmess节点了。</li>
                                                    <li> 8 kitsunbe教程与此类似。不再赘述。</li>
                                                    <input type="text" id="mt-target-3" class="form-control" value="{{$link}}?ver=3" />
                                                    <a href="javascript:;" class="btn blue mt-clipboard" data-clipboard-action="copy" data-clipboard-target="#mt-target-3">
                                                        {{trans('home.copy_subscribe_address')}}IOS小火箭专用Vmess订阅
                                                    </a>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools5">
                                                <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksRR-3.5.1.1.apk')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击左上角的shadowsocksR进入配置文件页，点击右下角的“+”号，点击“添加/升级SSR订阅”，完整复制本页上方“订阅服务”处地址，填入订阅信息并保存 </li>
                                                    <li> 选中任意一个节点，返回软件首页 </li>
                                                    <li> 在软件首页处找到“路由”选项，并将其改为“绕过局域网及中国大陆地址” </li>
                                                    <li> 点击右上角的小飞机图标进行连接，提示是否添加（或创建）VPN连接，点同意（或允许） </li>
                                                </ol>
                                                <hr>
                                                <ol>【Vmess 教程】
                                                    <li> 推荐下载<a href="/clients/app-universal-release.apk">v2rayNG</a>；或者<a href="/downloads/BifrostV.apk">bifrostv</a>，然后安装。</li>
                                                    <li> 点击右上角 订阅设置 subscription setting，输入本站V2Ray订阅地址 </li>
                                                    <li> 选择一个节点，点击右下角小飞机，即可使用。 </li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools6">
                                                <ol>【SS-R 教程】
                                                    <li> <a href="{{asset('clients/SSTap-beta-setup-1.0.9.7.zip')}}" target="_blank">点击此处</a>下载客户端并安装 </li>
                                                    <li> 打开 SSTap，选择 <i class="fa fa-cog"></i> -> SSR订阅 -> SSR订阅管理，添加订阅地址 </li>
                                                    <li> 添加完成后，再次选择 <i class="fa fa-cog"></i> - SSR订阅 - 手动更新SSR订阅，即可同步节点列表。</li>
                                                    <li> 在代理模式中选择游戏或「不代理中国IP」，点击「连接」即可加速。</li>
                                                    <li> 需要注意的是，一旦连接成功，客户端会自动缩小到任务栏，可在设置中关闭。</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                    @else
                        <div style="text-align: center;"><h3>{{trans('home.subscribe_baned')}}</h3>
                        <br>您的订阅受到保护，直到24小时内不同请求IP少于32次；您可以临时解除保护，系统会分配新的节点给您。
                        <br><br><button type="button" class="btn btn-big red btn-outline" onclick="reActiveSubscribe()">临时解除保护</button><br>*点击 临时解除保护 软件中的旧的节点会失效，请更新订阅获取新的节点使用。</div>
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
            layer.confirm('更换订阅地址将导致：<br>1.旧地址立即失效；<br>2.连接密码被更改；', {icon: 7, title:'警告'}, function(index) {
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
