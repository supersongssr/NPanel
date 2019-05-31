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
                                    <span class="font-blue">{{trans('home.account_expire')}}：</span>
                                    <span class="font-red">@if(date('Y-m-d') > Auth::user()->expire_time) {{trans('home.expired')}} @else {{Auth::user()->expire_time}} @endif</span>
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
                        @if($nodeList->isEmpty())
                            <div style="text-align: center;"><h2>请先<a href="{{url('services')}}">购买服务</a></h2></div>
                        @else
                            <div class="portlet-body">
                                <div class="mt-clipboard-container">
                                    <!-- Song -->
                                    <div class="alert alert-danger">
                                        <p> VIP用户专享：香港、台湾、日本、新加坡高速节点 + VIP独享节点 + 10Mbps起网速保障 + 1080P网速支持 + Netflix！支持 + 35ms + 服务单优先回复！<br> VIP用户保证网速，如网速不满意，提交工单增加节点。</p>
                                    </div>
                                    <!-- -->
                                    <input type="text" id="mt-target-1" class="form-control" value="{{$link}}?ver=1" />

                                    <a href="javascript:;" class="btn blue mt-clipboard" data-clipboard-action="copy" data-clipboard-target="#mt-target-1">
                                        {{trans('home.copy_subscribe_address')}}SR订阅地址
                                    </a>
                                    <input type="text" id="mt-target-2" class="form-control" value="{{$link}}?ver=2" />
                                    <a href="javascript:;" class="btn blue mt-clipboard" data-clipboard-action="copy" data-clipboard-target="#mt-target-2">
                                        {{trans('home.copy_subscribe_address')}}V2+SS订阅地址
                                    </a>
                                    <a href="javascript:exchangeSubscribe();" class="btn green">
                                        {{trans('home.exchange_subscribe')}}SR和V2Ray订阅地址
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
                                                <ol>【SR 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksX-NG-R8-1.4.4.dmg')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 点击状态栏纸飞机 -> 服务器 -> 编辑订阅 </li>
                                                    <li> 点击窗口左下角 “+”号 新增订阅，完整复制本页上方“订阅服务”处地址，然后将其粘贴至“订阅地址”栏 </li>
                                                    <li> 点击纸飞机 -> 服务器 -> 手动更新订阅 </li>
                                                    <li> 点击纸飞机 -> 服务器，选定合适服务器 </li>
                                                    <li> 点击纸飞机 -> 打开Shadowsocks </li>
                                                    <li> 点击纸飞机 -> PAC自动模式 </li>
                                                    <li> 点击纸飞机 -> 代理设置->从 GFW List 更新 PAC </li>
                                                    <li> 打开系统偏好设置 -> 网络，在窗口左侧选定显示为“已连接”的网络，点击右下角“高级...” </li>
                                                    <li> 切换至“代理”选项卡，勾选“自动代理配置”和“不包括简单主机名”，点击右下角“好”，再次点击右下角“应用” </li>
                                                </ol>
                                                <!-- song -->
                                                <hr>
                                                <ol>【V2 教程】
                                                    <li> <a href="/downloads/V2RayX.app.zip" target="_blank">点击此处</a>下载客户端，解压 </li>
                                                    <li> 将 V2RayX.app 复制到 程序 文件夹，然后点击网站内菜单---节点列表，点击您想要添加的节点名称 </li>
                                                    <li> 目前MAC端节点需要手动添加节点；您可以在节点列表查看到部分V2节点的配置信息；MAC端推荐使用SR呢 </li>
                                                </ol>
                                                <!-- -->
                                            </div>
                                            <div class="tab-pane" id="tools2">
                                                <!-- song -->
                                                <ol>【SR 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksR-win.zip')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 运行 ShadowsocksR 文件夹内的 ShadowsocksR.exe </li>
                                                    <li> 右击桌面右下角状态栏（或系统托盘）纸飞机 -> 服务器订阅 -> SSR服务器订阅设置 </li>
                                                    <li> 点击窗口左下角 “Add” 新增订阅，完整复制本页上方 “订阅服务” 处地址，将其粘贴至“网址”栏，点击“确定” </li>
                                                    <li> 右击纸飞机 -> 服务器订阅 -> 更新SSR服务器订阅（不通过代理） </li>
                                                    <li> 右击纸飞机 -> 服务器，选定合适服务器 </li>
                                                    <li> 右击纸飞机 -> 系统代理模式 -> PAC模式 </li>
                                                    <li> 右击纸飞机 -> PAC -> 更新PAC为GFWList </li>
                                                    <li> 右击纸飞机 -> 代理规则 -> 绕过局域网和大陆 </li>
                                                    <li> 右击纸飞机，取消勾选“服务器负载均衡” </li>
                                                </ol>
                                                <hr>
                                                <ol>【V2 教程】
                                                    <li> 根据系统<a href="/downloads/v2ray_x64.zip" target="_blank">下载64位版</a>或者<a href="/downloads/v2ray_x86.zip" target="_blank">下载32位版</a>，下载后解压 </li>
                                                    <li> 运行程序v2rayN.exe，双击右下角图标 </li>
                                                    <li> V2Ray软件界面，点击订阅，订阅设置，填写本站的V2Ray订阅地址 </li>
                                                    <li> 点订阅，更新订阅，即可获取本站节点</li>
                                                    <li>右键右下角图标，开启http代理，然后选择一个节点即可使用。</li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools3">
                                                <ol>【SR 教程】
                                                    <li> <a href="{{asset('clients/Shadowsocks-qt5-3.0.1.zip')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击状态栏小飞机，找到服务器 -> 编辑订阅，复制黏贴订阅地址 </li>
                                                    <li> 更新订阅设置即可 </li>
                                                </ol>
                                                <hr>
                                                <ol>【V2 教程】
                                                    <li>能耍 Linux 的都是大佬了，受我一拜</li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools4">
                                                <ol>【SR 教程】
                                                    <li> iOS端需要配合电脑，利用爱思助手来免费安装 </li>
                                                    <li> <a href="https://www.i4.cn/news_detail_3339.html" target="_blank">点击此处</a>查看爱思助手安装ipa文件教程 </li>
                                                    <li> <a href="/downloads/Shadowrocket_2.1.12.ipa" target="_blank">点击此处</a>下载iOS客户端的ipa文件 </li>
                                                    <li> 然后请使用爱思助手安装客户端的ipa文件包（软件原价20元，这个方法可以免费获得） </li>
                                                    <li> 打开 Shadowrocket，点击右上角 “+”号 添加节点，类型选择 Subscribe </li>
                                                    <li> 完整复制本页上方 “订阅服务” 处地址，将其粘贴至 “URL”栏，点击右上角 “完成” </li>
                                                    <li> 左划新增的服务器订阅，点击 “更新” </li>
                                                    <li> 选定合适服务器节点，点击右上角连接开关，屏幕上方状态栏出现“VPN”图标 </li>
                                                    <li> 当进行海外游戏时请将 Shadowrocket “首页” 页面中的 “全局路由” 切换至 “代理”，并确保“设置”页面中的“UDP”已开启转发 </li>
                                                </ol>
                                                <hr>
                                                <ol>【V2 教程】
                                                    <li> 推荐下载kitsunebi（支持kcp tcp协议，需要在美区下载，请注意不是lite 版）；请注意，kitsunebi需要采用本站的kitsunebi专用订阅地址才能获取节点； kitsunebi还在开发阶段，如订阅地址失效，请反馈服务单</li>
                                                    <li> 或者 使用shadowrocket 也可以使用本站部分V2节点，安装教程参考上面【SR 教程】 </li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools5">
                                                <ol>【SR 教程】
                                                    <li> <a href="{{asset('clients/ShadowsocksRR-3.5.1.1.apk')}}" target="_blank">点击此处</a>下载客户端并启动 </li>
                                                    <li> 单击左上角的shadowsocksR进入配置文件页，点击右下角的“+”号，点击“添加/升级SSR订阅”，完整复制本页上方“订阅服务”处地址，填入订阅信息并保存 </li>
                                                    <li> 选中任意一个节点，返回软件首页 </li>
                                                    <li> 在软件首页处找到“路由”选项，并将其改为“绕过局域网及中国大陆地址” </li>
                                                    <li> 点击右上角的小飞机图标进行连接，提示是否添加（或创建）VPN连接，点同意（或允许） </li>
                                                </ol>
                                                <hr>
                                                <ol>【V2 教程】
                                                    <li> 推荐下载<a href="/downloads/v2rayng.apk">v2rayNG</a>；或者<a href="/downloads/BifrostV.apk">bifrostv</a>，然后安装。</li>
                                                    <li> 点击右上角 订阅设置 subscription setting，输入本站V2Ray订阅地址 </li>
                                                    <li> 选择一个节点，点击右下角小飞机，即可使用。 </li>
                                                </ol>
                                            </div>
                                            <div class="tab-pane" id="tools6">
                                                <ol>【SR 教程】
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
                        @endif
                    @else
                        <div style="text-align: center;"><h3>{{trans('home.subscribe_baned')}}</h3></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @if(!$nodeList->isEmpty())
                    <div class="portlet light">
                        <div class="portlet-title">
                            <div class="caption">
                                <span class="caption-subject font-blue bold">{{trans('home.my_node_list')}}</span>
                            </div>
                            <!--<div class="actions">
                                <div class="btn-group btn-group-devided" data-toggle="buttons">
                                    <button class="btn btn-info" id="copy_all_nodes" data-clipboard-text="{{$allNodes}}"> 复制所有节点 </button>
                                </div>
                            </div> -->
                        </div>
                        <div class="portlet-body">
                            <div class="tab-content">
                                <div class="tab-pane active">
                                    <!-- -->
                                    <div class="alert alert-danger">
                                        <p> 【提示】：请通过订阅获取节点;免费用户不提供技术支持;如需更快网速,欢迎使用VIP节点; </p>
                                    </div>
                                    <!-- -->
                                    <div class="mt-comments">
                                        @foreach($nodeList as $node)
                                            <div class="mt-comment">
                                                <div class="mt-comment-img" style="width:auto;">
                                                    @if($node->country_code)
                                                        <img src="{{asset('assets/images/country/' . $node->country_code . '.png')}}"/>
                                                    @else
                                                        <img src="{{asset('/assets/images/country/un.png')}}"/>
                                                    @endif
                                                </div>
                                                <div class="mt-comment-body">
                                                    <div class="mt-comment-info">
                                                        <span class="mt-comment-author">{{$node->name}}</span>
                                                        <span class="mt-comment-date">
                                                                <span class="badge badge-inverse">#{{$node->id}}</span>
                                                            </span>
                                                    </div>
                                                    <div class="mt-comment-text"> {{$node->desc}} </div>
                                                    <div class="mt-comment-details">
                                                            <span class="mt-comment-status mt-comment-status-pending">
                                                                @if($node->labels)
                                                                    @foreach($node->labels as $vo)
                                                                        <span class="badge badge-info">{{$vo->labelInfo->name}}</span>
                                                                        <span class="badge badge-success">{{$node->traffic_rate}}  倍率</span>
                                                                    @endforeach
                                                                @endif
                                                            </span>
                                                        <!-- <ul class="mt-comment-actions" style="display: block;">
                                                            <li>
                                                                <a class="btn btn-sm green btn-outline" data-toggle="modal" href="#txt_{{$node->id}}" > <i class="fa fa-reorder"></i> </a>
                                                            </li>
                                                            <li>
                                                                <a class="btn btn-sm green btn-outline" data-toggle="modal" href="#link_{{$node->id}}"> @if($node->type == 1) <i class="fa fa-paper-plane"></i> @else <i class="fa fa-vimeo"></i> @endif </a>
                                                            </li>
                                                            <li>
                                                                <a class="btn btn-sm green btn-outline" data-toggle="modal" href="#qrcode_{{$node->id}}"> <i class="fa fa-qrcode"></i> </a>
                                                            </li>
                                                        </ul> -->
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
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
    </script>

    <script type="text/javascript">
        // 更换订阅地址
        function exchangeSubscribe() {
            layer.confirm('更换订阅地址将导致：<br>1.旧地址立即失效；<br>2.连接密码被更改；', {icon: 7, title:'警告'}, function(index) {
                $.post("{{url('exchangeSubscribe')}}", {_token:'{{csrf_token()}}'}, function (ret) {
                    layer.msg(ret.message, {time:1000}, function () {
                        if (ret.status == 'success') {
                            window.location.reload();
                        }
                    });
                });

                layer.close(index);
            });
        }
    </script>

    @if(!$nodeList->isEmpty())
        <script type="text/javascript">
            var copy_all_nodes = document.getElementById('copy_all_nodes');
            var clipboard = new Clipboard(copy_all_nodes);

            clipboard.on('success', function(e) {
                layer.alert("复制成功，通过右键菜单倒入节点链接即可", {icon:1, title:'提示'});
            });

            clipboard.on('error', function(e) {
                console.log(e);
            });
        </script>
    @endif
@endsection
