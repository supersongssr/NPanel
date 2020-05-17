@extends('user.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
  <!--       <div class="row">
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
                                        <span class="label label-danger">账号状态异常 请重新登陆</span>
                                    @elseif ( Auth::user()->status == 1 )
                                        <span class="label label-success">账号正常</span>
                                    @else
                                        <span class="label label-info">账号异常,请重新登录</span>
                                    @endif
                                    @if ( Auth::user()->enable == 0  )
                                        <span class="label label-danger">!请检查流量 和 等级</span>
                                    @elseif ( Auth::user()->enable == 1 )
                                        <span class="label label-success">节点正常</span>
                                    @else
                                        <span class="label label-info">!请检查流量 和 等级</span>
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
                                    <span class="font-blue">学历：</span>
                                    <span class="font-red">{{Auth::user()->levelList->level_name}}</span>
                                </h4>
                            </li>
                            
                            @if(Auth::user()->traffic_reset_day)
                            <li>
                                <h4>
                                    <span class="font-red">总流量：</span>
                                    <span class="font-red">{{ flowAutoShow(Auth::user()->u + Auth::user()->d)}} /{{flowAutoShow(Auth::user()->transfer_enable)}}</span>
                                    <span class="font-blue">( {{flowAutoShow(Auth::user()->u)}}/{{flowAutoShow(Auth::user()->transfer_enable - Auth::user()->transfer_monthly)}}流量包 +</span>
                                    <span class="font-blue">{{flowAutoShow(Auth::user()->d)}}/{{flowAutoShow(Auth::user()->transfer_monthly)}}每月流量 {{Auth::user()->traffic_reset_day}}号重置)</span>
                                    
                                </h4>
                            </li>
                            @else
                            <li>
                                <h4>
                                    <span class="font-blue">流量：</span>
                                    <span class="font-blue"> {{flowAutoShow(Auth::user()->u + Auth::user()->d)}} /{{flowAutoShow(Auth::user()->transfer_enable)}} </span>
                                </h4>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div> -->

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
                                        <p> 【提示】：请通过订阅获取所有节点。 </p>
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
                                                        <span class="mt-comment-author">{{$node->name}} ·{{$node->level}}#{{$node->id}}</span>
                                                        <span class="mt-comment-date">
                                                            <span class="badge badge-danger">{{$node->node_onload * 10}}%</span>
                                                        </span>
                                                    </div>
                                                    <div class="mt-comment-text"> Class:{{$node->sort}},No.{{$node->id}},Rate:{{$node->traffic_rate}},TopRate:{{$node->node_cost/5}},Bandwidth:{{$node->bandwidth}}M,Traffic:{{floor($node->traffic /100000000)}}G,Online:{{$node->node_online}},Onload:{{$node->node_onload * 10}}%</div>
                                                    <div class="mt-comment-details">
                                                            <span class="mt-comment-status mt-comment-status-pending">
                                                                @if($node->labels)
                                                                    @foreach($node->labels as $vo)
                                                                        <span class="badge badge-info">{{$vo->labelInfo->name}}</span>
                                                                    @endforeach
                                                                @endif
                                                                <!-- <span class="badge badge-success">{{$node->traffic_rate}} Rate</span>
                                                                <span class="badge badge-inverse">No.#{{$node->id}}</span> -->
                                                            </span>
                                                        <ul class="mt-comment-actions" style="display: block;">
                                                            <li>
                                                                <a class="btn btn-sm green btn-outline" data-toggle="modal" href="#txt_{{$node->id}}" > <i class="fa fa-reorder"></i> </a>
                                                            </li>
                                                            <li>
                                                                <a class="btn btn-sm green btn-outline" data-toggle="modal" href="/nodeMonitor?id={{$node->id}}"> @if($node->type == 1) <i class="fa fa-paper-plane"></i> @else <i class="fa fa-vimeo"></i> @endif </a>
                                                            </li>
                                                            <li>
                                                                <a class="btn btn-sm green btn-outline" data-toggle="modal" href="/nodeMonitor?id={{$node->id}}"> <i class="fa fa-qrcode"></i> </a>
                                                            </li>
                                                        </ul>
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

        @foreach($nodeList as $node)
        <!-- 配置文本 -->
            <div class="modal fade draggable-modal" id="txt_{{$node->id}}" tabindex="-1" role="basic" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                            <h4 class="modal-title">{{trans('home.setting_info')}}</h4>
                        </div>
                        <div class="modal-body">
                            @if ($node->type == 2)
                            <textarea class="form-control" rows="10" readonly="readonly">Vmess V2ray Config：
address: {{$node->server}}
port: {{$node->v2_port}}
id: {{Auth::user()->vmess_id}}
alterId： {{$node->v2_alter_id}}
security： {{$node->v2_method}}
network： {{$node->v2_net}}
remarks： {{$node->name}}#{{$node->id}}
type： {{$node->v2_type}}
host： {{$node->v2_host}}
path： {{$node->v2_path}}
TLS： {{$node->v2_tls}}
allowInsecure: true
MAC:tls servername： {{$node->v2_host}}
IOS:peer： {{$node->v2_host}}         </textarea>
                            @else
                            <textarea class="form-control" rows="10" readonly="readonly">SS SSR Config：
address: {{$node->server}}
port: {{$node->v2_port}}
id: {{Auth::user()->vmess_id}}
alterId： {{$node->v2_alter_id}}
security： {{$node->v2_method}}
network： {{$node->v2_net}}
remarks： {{$node->name}}#{{$node->id}}
type： {{$node->v2_type}}
host： {{$node->v2_host}}
MAC:tls servername： {{$node->v2_host}}
IOS:peer： {{$node->v2_host}}
path： {{$node->v2_path}}
TLS： {{$node->v2_tls}}
allowInsecure: true         </textarea>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
        @endforeach
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
