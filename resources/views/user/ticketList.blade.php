@extends('user.layouts')
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
                            <span class="caption-subject bold"> 公开工单 - 相似问题快速解决 </span>
                            <code>*常见问题工单已公开展示；相似问题可以在这里快速找到解决方案。</code>
                        </div>
                       
                    </div>
                        
                        <div class="row">
                            <div class="col-md-3 col-sm-4 col-xs-12" >
                                <input type="text" class="col-md-4 form-control" name="search" value="{{Request::get('search')}}" id="search" placeholder="请输入 关键词 " onkeydown="if(event.keyCode==13){do_search();}">
                            </div>
                            <div class="col-md-3 col-sm-4 col-xs-12">
                                <button type="button" class="btn blue" onclick="do_search();">查询</button>
                            </div>
                        </div>
                    <div class="portlet-body">
                        <div class="table-scrollable table-scrollable-borderless">
                            <table class="table table-hover table-light table-checkable order-column">
                                <thead>
                                    <tr>
                                        <th> # </th>
                                        <th> {{trans('home.ticket_table_title')}} </th>
                                        <th> {{trans('home.ticket_table_status')}} </th>
                                    </tr>
                                </thead>
                                <tbody>
                                @if($openTicket->isEmpty())
                                    <tr>
                                        <td colspan="4" style="text-align: center;"> <h3>{{trans('home.ticket_table_none')}}</h3> </td>
                                    </tr>
                                @else
                                    @foreach($openTicket as $key => $ticket)
                                        <tr class="odd gradeX">
                                            <td> {{$key + 1}} </td>
                                            <td> <a href="/viewTicket?id={{$ticket->id}}" target="_blank">{{$ticket->title}}</a> </td>
                                            <td>
                                                <span class="label label-info"> 公开工单 </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 col-sm-12">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $openTicket->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END EXAMPLE TABLE PORTLET-->
                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold"> {{trans('home.ticket_title')}} </span>
                        </div>
                        <div class="actions">
                            <div class="btn-group">
                                <button class="btn sbold blue" data-toggle="modal" data-target="#charge_modal"> {{trans('home.ticket_table_new_button')}} </button>
                            </div>
                        </div>
                    </div>
                    <code>*禁止滥用工单；管理回复且公开工单，奖励用户0.33￥/次；用户提交工单，扣除0.33￥/次。</code>
                    <div class="portlet-body">
                        <div class="table-scrollable table-scrollable-borderless">
                            <table class="table table-hover table-light table-checkable order-column">
                                <thead>
                                    <tr>
                                        <th> # </th>
                                        <th> {{trans('home.ticket_table_title')}} </th>
                                        <th> {{trans('home.ticket_table_status')}} </th>
                                    </tr>
                                </thead>
                                <tbody>
                                @if($ticketList->isEmpty())
                                    <tr>
                                        <td colspan="4" style="text-align: center;"> <h3>{{trans('home.ticket_table_none')}}</h3> </td>
                                    </tr>
                                @else
                                    @foreach($ticketList as $key => $ticket)
                                        <tr class="odd gradeX">
                                            <td> {{$key + 1}} </td>
                                            <td> <a href="/replyTicket?id={{$ticket->id}}" target="_blank">{{$ticket->title}}</a> </td>
                                            <td>
                                                @if ($ticket->status == 0)
                                                    <span class="label label-info"> {{trans('home.ticket_table_status_wait')}} </span>
                                                @elseif ($ticket->status == 1)
                                                    <span class="label label-danger"> {{trans('home.ticket_table_status_reply')}} </span>
                                                @else
                                                    <span class="label label-default"> {{trans('home.ticket_table_status_close')}} </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 col-sm-12">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $ticketList->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END EXAMPLE TABLE PORTLET-->
            </div>
        </div>
        <div id="charge_modal" class="modal fade" tabindex="-1" data-focus-on="input:first" data-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                        <h4 class="modal-title"> {{trans('home.ticket_table_new_button')}} </h4>
                    </div>
                    <div class="modal-body">
                        <input type="text" name="title" id="title" placeholder="{{trans('home.ticket_table_title')}}" class="form-control margin-bottom-20">
                        <textarea name="content" id="content" placeholder="{{trans('home.ticket_table_new_desc')}}" class="form-control margin-bottom-20" rows="4"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" data-dismiss="modal" class="btn dark btn-outline"> {{trans('home.ticket_table_new_cancel')}} </button>
                        <button type="button" data-dismiss="modal" class="btn green btn-outline" onclick="addTicket()"> {{trans('home.ticket_table_new_yes')}} </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script type="text/javascript">
        // 发起工单
        function addTicket() {
            var title = $("#title").val();
            var content = $("#content").val();

            if (title == '' || title == undefined) {
                layer.alert('您未填写工单标题', {icon: 2, title:'提示'});
                return false;
            }

            if (content == '' || content == undefined) {
                layer.alert('您未填写工单内容', {icon: 2, title:'提示'});
                return false;
            }

            layer.confirm('确定提交工单？', {icon: 3, title:'警告'}, function(index) {
                $.post("/addTicket", {_token:'{{csrf_token()}}', title:title, content:content}, function(ret) {
                    layer.msg(ret.message, {time:1000}, function() {
                        if (ret.status == 'success') {
                            window.location.reload();
                        }
                    });
                });

                layer.close(index);
            });
        }
        // 搜索
        function do_search() {
            var search = $("#search").val();

            window.location.href = '/tickets' + '?search=' + search;
        }
    </script>
@endsection