@extends('user.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <div class="row">
            <div class="col-md-12">
                <div class="note note-info">
                    <p>通过您的推广链接/邀请码注册您将获得如下奖励：</p>
                    <p><code>A：赠送 1G 流量 <br>B：赠送 5元 信用卡，可用于购买商品 <br>C：赠送 5元 奖励（设置收款信息，可提现）<br>D：用户消费时，奖励25%消费返利（设置收款信息，可提现）循环奖励，消费多少次奖励多少次 <br>E：将奖励 C 生成代金券 <br>F：将奖励 D 生成代金券</code><br><small>* AB+C/D 2+1奖励模式<br>* 具体提现标准请参考：<a href="/article?id=40" target="_blank">网站提现详细规则</a><br>* 简易规则：AB必返，CD 2选1，EF与CD互斥</small></p>
                </div>
            </div>
        </div>
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-4">
                <div class="tab-pane active">
                    <div class="portlet light bordered">
                        <div class="portlet-title">
                            <div class="caption">
                                <span class="caption-subject font-dark bold">{{trans('home.invite_code_make')}}</span>
                            </div>
                        </div>
                        <div class="portlet-body">
                            <div class="alert alert-info">
                                <i class="fa fa-warning"></i>
                                {{trans('home.invite_code_tips1')}} <strong> {{$num}} </strong> {{trans('home.invite_code_tips2', ['days' => \App\Components\Helpers::systemConfig()['user_invite_days']])}}
                            </div>
                            <button type="button" class="btn blue" onclick="makeInvite()" @if(!$num) disabled @endif> {{trans('home.invite_code_button')}} </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="tab-pane active">
                    <div class="portlet light bordered">
                        <div class="portlet-title">
                            <div class="caption">
                                <span class="caption-subject font-dark bold">{{trans('home.invite_code_my_codes')}}</span>
                            </div>
                        </div>
                        <div class="portlet-body">
                            <div class="table-scrollable table-scrollable-borderless">
                                <table class="table table-hover table-light table-checkable order-column">
                                    <thead>
                                        <tr>
                                            <th> # </th>
                                            <th> {{trans('home.invite_code_table_name')}} </th>
                                            <th> {{trans('home.invite_code_table_date')}} </th>
                                            <th> {{trans('home.invite_code_table_status')}} </th>
                                            <th> {{trans('home.invite_code_table_user')}} </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($inviteList->isEmpty())
                                            <tr>
                                                <td colspan="5" style="text-align: center;">{{trans('home.invite_code_table_none_codes')}}</td>
                                            </tr>
                                        @else
                                            @foreach($inviteList as $key => $invite)
                                                <tr>
                                                    <td> {{$key + 1}} </td>
                                                    <td> <a class="copy" data-clipboard-text="{{url('register?aff=' . Auth::user()->id . '&code=' . $invite->code)}}">{{$invite->code}}</a> </td>
                                                    <td> {{$invite->dateline}} </td>
                                                    <td>
                                                        @if($invite->status == '0')
                                                            <span class="label label-sm label-success"> {{trans('home.invite_code_table_status_un')}} </span>
                                                        @elseif($invite->status == '1')
                                                            <span class="label label-sm label-danger"> {{trans('home.invite_code_table_status_yes')}} </span>
                                                        @else
                                                            <span class="label label-sm label-default"> {{trans('home.invite_code_table_status_expire')}} </span>
                                                        @endif
                                                    </td>
                                                    <td> {{empty($invite->user) ? ($invite->status == 1 ? '【账号已删除】' : '') : $invite->user->username}} </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="row">
                                <div class="col-md-4 col-sm-4">
                                    <div class="dataTables_info" role="status" aria-live="polite">{{trans('home.invite_code_summary', ['total' => $inviteList->total()])}}</div>
                                </div>
                                <div class="col-md-8 col-sm-8">
                                    <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                        {{ $inviteList->links() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

    <script type="text/javascript">
        // 生成邀请码
        function makeInvite() {
            var _token = '{{csrf_token()}}';

            $.ajax({
                type: "POST",
                url: "{{url('makeInvite')}}",
                async: false,
                data: {_token:_token},
                dataType: 'json',
                success: function (ret) {
                    layer.msg(ret.message, {time:1000}, function() {
                        if (ret.status == 'success') {
                            window.location.reload();
                        }
                    });
                }
            });

            return false;
        }
    </script>

    @if(!$inviteList->isEmpty())
        <script type="text/javascript">
            var url = document.getElementsByClassName('copy');
            var clipboard = new Clipboard(url);

            clipboard.on('success', function(e) {
                layer.alert("复制成功，您可以直接黏贴发送邀请链接", {icon:1, title:'提示'});
            });

            clipboard.on('error', function(e) {
                console.log(e);
            });
        </script>
    @endif
@endsection