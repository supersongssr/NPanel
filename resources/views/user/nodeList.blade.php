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
                                        <span class="label label-info">异常</span>
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
                                                        <a class="btn green" href="javascript:nodeMonitor('{{$node->id}}');">
                                                            <img src="{{asset('assets/images/country/' . $node->country_code . '.png')}}"/>
                                                        </a>
                                                    @else
                                                        <a class="btn green" href="javascript:nodeMonitor('{{$node->id}}');">
                                                        <img src="{{asset('/assets/images/country/un.png')}}"/>
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="mt-comment-body">
                                                    <div class="mt-comment-info">
                                                        <span class="mt-comment-author">{{$node->name}} -Lv.{{$node->sort}}</span>
                                                        <!-- <span class="mt-comment-date">
                                                                <span class="badge badge-danger">Lv.{{$node->sort}}</span>
                                                            </span>  -->
                                                    </div>
                                                    <div class="mt-comment-text"> {{$node->desc}} </div>
                                                    <div class="mt-comment-details">
                                                            <span class="mt-comment-status mt-comment-status-pending">
                                                                @if($node->labels)
                                                                    @foreach($node->labels as $vo)
                                                                        <span class="badge badge-info">{{$vo->labelInfo->name}}</span>
                                                                    @endforeach
                                                                @endif
                                                                <span class="badge badge-success">{{$node->traffic_rate * 100}}% 倍</span>
                                                                <span class="badge badge-inverse">#{{$node->id}}</span>
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
