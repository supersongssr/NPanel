@extends('admin.layouts')
@section('css')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        
        <div class="row">
            <div class="col-md-12">
                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold uppercase"> 节点列表 </span>
                        </div>
                        <div class="actions">
                            <div class="btn-group">
                                <button type="button" class="btn blue" onclick="doSearch();">查询</button>
                                <button type="button" class="btn grey" onclick="doReset();">重置</button>
                                <button class="btn sbold blue" onclick="addNode()"> 添加节点 </button>
                            </div>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <input type="text" class="col-md-4 col-sm-4 col-xs-12 form-control" name="id" value="{{Request::get('id')}}" id="id" placeholder="节点ID" onkeydown="if(event.keyCode==13){doSearch();}">
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <input type="text" class="col-md-4 col-sm-4 col-xs-12 form-control" name="nodename" value="{{Request::get('nodename')}}" id="nodename" placeholder="节点名" onkeydown="if(event.keyCode==13){doSearch();}">
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <input type="text" class="col-md-4 col-sm-4 col-xs-12 form-control" name="ipv6" value="{{Request::get('ipv6')}}" id="ipv6" placeholder="ipv6" onkeydown="if(event.keyCode==13){doSearch();}">
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <select class="form-control" name="type" id="type" onChange="doSearch()">
                                    <option value="" @if(Request::get('type') == '') selected @endif>类型</option>
                                    <option value="1" @if(Request::get('type') == '1') selected @endif>S1</option>
                                    <option value="2" @if(Request::get('type') == '2') selected @endif>V2</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <select class="form-control" name="sort" id="sort" onChange="doSearch()">
                                    <option value="" @if(Request::get('sort') == '') selected @endif>等级</option>
                                    <option value="-1" @if(Request::get('sort') == '-1') selected @endif>高->低</option>
                                    <option value="1" @if(Request::get('sort') == '1') selected @endif>低->高</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <select class="form-control" name="status" id="status" onChange="doSearch()">
                                    <option value="" @if(Request::get('status') == '') selected @endif>状态</option>
                                    <option value="1" @if(Request::get('status') == '1') selected @endif>正常</option>
                                    <option value="0" @if(Request::get('status') == '0') selected @endif>维护</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <select class="form-control" name="traffic_rate" id="traffic_rate" onChange="doSearch()">
                                    <option value="" @if(Request::get('traffic_rate') == '') selected @endif>倍率</option>
                                    <option value="-1" @if(Request::get('traffic_rate') == '-1') selected @endif>高->低</option>
                                    <option value="1" @if(Request::get('traffic_rate') == '1') selected @endif>低->高</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <select class="form-control" name="traffic" id="traffic" onChange="doSearch()">
                                    <option value="" @if(Request::get('traffic') == '') selected @endif>流量</option>
                                    <option value="-1" @if(Request::get('traffic') == '-1') selected @endif>高->低</option>
                                    <option value="1" @if(Request::get('traffic') == '1') selected @endif>低->高</option>
                                </select>
                            </div>
                            
                        </div>
                        <div class="row">
                            <div class="col-md-8 col-sm-8">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $nodeList->links() }}
                                </div>
                            </div>
                        </div>
                        <div class="table-scrollable table-scrollable-borderless">
                            <table class="table table-hover table-light">
                                <thead>
                                <tr>
                                    <th> 操作 </th>
                                    <th> <span class="node-id"><a href="javascript:showIdTips();">ID</a></span> </th>
                                    <th> 类型 </th>
                                    <th> 16G </th>
                                    <th> 名称 </th>
                                    <th> 统计 </th>
                                    <th> <span class="node-flow"><a href="javascript:showFlowTips();">流量</a></span> </th>
                                    <th> 监控 </th>
                                    <th> 存活 </th>
                                    <th> 状态 </th>
                                    <th> 在线 </th>
                                    <th> 倍率 </th>
                                    <th> 等级 </th>
                                    <th> 操作 </th>
                                </tr>
                                </thead>
                                <tbody>
                                    @if($nodeList->isEmpty())
                                        <tr>
                                            <td colspan="11" style="text-align: center;">暂无数据</td>
                                        </tr>
                                    @else
                                        @foreach($nodeList as $node)
                                            <tr class="odd gradeX">
                                                <td><a class="btn green" href="javascript:editNode('{{$node->id}}');"> 编辑 </a>
                                                </td>
                                                <td> {{$node->id}} </td>
                                                <td>
                                                    @if($node->is_transit)
                                                        <span class="label {{$node->status ? 'label-info' : 'label-default'}}">{{$node->is_transit ? '中转' : ''}}</span>
                                                    @else
                                                        <span class="label {{$node->status ? 'label-info' : 'label-default'}}">{{$node->type == 2 ? 'V2' : 'SR'}}</span>
                                                    @endif
                                                </td>
                                                <td> {{$node->ipv6}} </td>
                                                <td> {{$node->name}} </td>
                                                <td>
                                                    <!-- 
                                                    @if($node->is_nat)
                                                        <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">NAT</span>
                                                    @else
                                                        <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">{{$node->ip}}</span>
                                                    @endif
                                                -->
                                                    <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">{{$node->desc}}</span>
                                                </td>
                                                <td> {{$node->is_transit ? '' : $node->transfer}} </td>
                                                <td><a class="btn green" href="javascript:nodeMonitor('{{$node->id}}');"> 流量 </a>
                                                </td>
                                                <td> <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">{{$node->is_transit ? '' : $node->uptime}}</span> </td>
                                                <td> <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">{{$node->is_transit ? '' : $node->load}}</span> </td>
                                                <td> <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">{{$node->is_transit ? '' : $node->online_users}}</span> </td>
                                                
                                                <td> <span class="label {{$node->status ? 'label-danger' : 'label-default'}}">{{$node->traffic_rate}}</span> </td>
                                                <td><span class="label label-info">{{$node->sort}}</span></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a class="btn btn-default dropdown-toggle" data-toggle="dropdown" href="javascript:;" aria-expanded="false"> 操作
                                                            <i class="fa fa-angle-down"></i>
                                                        </a>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a href="javascript:editNode('{{$node->id}}');"> 编辑 </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:delNode('{{$node->id}}');"> 删除 </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:nodeMonitor('{{$node->id}}');"> 流量概况 </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-4 col-sm-4">
                                <div class="dataTables_info" role="status" aria-live="polite">共 {{$nodeList->total()}} 个节点</div>
                            </div>
                            <div class="col-md-8 col-sm-8">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $nodeList->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END EXAMPLE TABLE PORTLET-->
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-info">
                    <table class="table table-hover table-light">
                        <tr>
                            <th>等级</th>
                            <th>All</th>
                            <th>Lv.9</th>
                            <th>Lv.8</th>
                            <th>Lv.7</th>
                            <th>Lv.6</th>
                            <th>Lv.5</th>
                            <th>Lv.4</th>
                            <th>Lv.3</th>
                            <th>Lv.2</th>
                            <th>Lv.1</th>
                            <th>Lv.0</th>
                        </tr>
                        <tr>
                            <td>用户</td>
                            <td>{{$sts['userall']}}</td>
                            <td>{{$sts['uservip9']}}</td>
                            <td>{{$sts['uservip8']}}</td>
                            <td>{{$sts['uservip7']}}</td>
                            <td>{{$sts['uservip6']}}</td>
                            <td>{{$sts['uservip5']}}</td>
                            <td>{{$sts['uservip4']}}</td>
                            <td>{{$sts['uservip3']}}</td>
                            <td>{{$sts['uservip2']}}</td>
                            <td>{{$sts['uservip1']}}</td>
                            <td>{{$sts['uservip0']}}</td>
                        </tr>
                        <tr>
                            <td>节点</td>
                            <td>{{$sts['nodeall']}}</td>
                            <td>{{$sts['nodelv9']}}</td>
                            <td>{{$sts['nodelv8']}}</td>
                            <td>{{$sts['nodelv7']}}</td>
                            <td>{{$sts['nodelv6']}}</td>
                            <td>{{$sts['nodelv5']}}</td>
                            <td>{{$sts['nodelv4']}}</td>
                            <td>{{$sts['nodelv3']}}</td>
                            <td>{{$sts['nodelv2']}}</td>
                            <td>{{$sts['nodelv1']}}</td>
                            <td>{{$sts['nodelv0']}}</td>
                        </tr>
                        <tr>
                            <td>比率</td>
                            <td>{{ @floor($sts['userall'] / $sts['nodeall']) }}</td>
                            <td>{{ @floor($sts['uservip9'] / $sts['nodelv9']) }}</td>
                            <td>{{ @floor($sts['uservip8'] / $sts['nodelv8']) }}</td>
                            <td>{{ @floor($sts['uservip7'] / $sts['nodelv7']) }}</td>
                            <td>{{ @floor($sts['uservip6'] / $sts['nodelv6']) }}</td>
                            <td>{{ @floor($sts['uservip5'] / $sts['nodelv5']) }}</td>
                            <td>{{ @floor($sts['uservip4'] / $sts['nodelv4']) }}</td>
                            <td>{{ @floor($sts['uservip3'] / $sts['nodelv3']) }}</td>
                            <td>{{ @floor($sts['uservip2'] / $sts['nodelv2']) }}</td>
                            <td>{{ @floor($sts['uservip1'] / $sts['nodelv1']) }}</td>
                            <td>{{ @floor($sts['uservip0'] / $sts['nodelv0']) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script type="text/javascript">
        // 添加节点
        function addNode() {
            window.location.href = '/admin/addNode';
        }

        // 编辑节点
        function editNode(id) {
            window.location.href = '/admin/editNode?id=' + id + '&page=' + '{{Request::get('page', 1)}}';
        }

        // 删除节点
        function delNode(id) {
            layer.confirm('确定删除节点？', {icon: 2, title:'警告'}, function(index) {
                $.post("/admin/delNode", {id:id, _token:'{{csrf_token()}}'}, function(ret) {
                    layer.msg(ret.message, {time:1000}, function() {
                        if (ret.status == 'success') {
                            window.location.reload();
                        }
                    });
                });

                layer.close(index);
            });
        }

        // 节点流量监控
        function nodeMonitor(id) {
            window.location.href = '/admin/nodeMonitor?id=' + id  + '&page=' + '{{Request::get('page', 1)}}';
        }

        // 显示提示
        function showIdTips() {
            layer.tips('对应SSR(R)后端usermysql.json中的nodeid', '.node-id', {
                tips: [3, '#3595CC'],
                time: 1200
            });
        }

        // 搜索
        function doSearch() {
            var id = $("#id").val();
            var nodename = $("#nodename").val();
            var ipv6 = $("#ipv6").val();
            var type = $("#type option:checked").val();
            var sort = $("#sort option:checked").val();
            var status = $("#status option:checked").val();
            var traffic_rate = $("#traffic_rate option:checked").val();
            var traffic = $("#traffic option:checked").val();

            window.location.href = '/admin/nodeList' + '?id=' + id +'&nodename=' + nodename + '&ipv6=' + ipv6 + '&type=' + type + '&sort=' + sort + '&status=' + status + '&traffic_rate=' + traffic_rate + '&traffic=' + traffic;
        }

        // 重置
        function doReset() {
            window.location.href = '/admin/nodeList';
        }

        // 显示提示
        function showFlowTips() {
            layer.tips('如果服务器使用锐速等加速工具，则实际产生的流量会超出以下的值', '.node-flow', {
                tips: [3, '#3595CC'],
                time: 1200
            });
        }

        // 修正table的dropdown
        $('.table-scrollable').on('show.bs.dropdown', function () {
            $('.table-scrollable').css( "overflow", "inherit" );
        });

        $('.table-scrollable').on('hide.bs.dropdown', function () {
            $('.table-scrollable').css( "overflow", "auto" );
        });
    </script>
@endsection