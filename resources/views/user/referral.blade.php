@extends('user.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-info">
                    <p>通过您的推广链接/邀请码注册返利5$/用户,你们双方都将获得 5GB 流量奖励；当他们消费时，您将获得他们消费金额的 20% 作为奖励，奖励可提现。</p>
                    <p>推广返利：（ 5$余额 + 5G流量 ）* 邀请人数 + （ 20%消费返利 * 用户消费 ）；</p>
                </div>
                @if(Auth::user()->level == 0)
                <div class="note note-info">
                    <p>您可以申请信用额度<button type="submit" class="btn blue" onclick="CreditMoney()"> 信用花呗 </button></p>
                </div>
                @endif
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="portlet light form-fit bordered">
                    <div class="portlet-title">
                        <div class="caption">
                            <i class="icon-link font-blue"></i>
                            <span class="caption-subject font-blue bold">{{trans('home.referral_my_link')}}</span>
                        </div>
                    </div>
                    <div class="portlet-body form">
                        <div class="mt-clipboard-container">
                            <input type="text" id="mt-target-1" class="form-control" value="{{$link}}" />
                            <a href="javascript:;" class="btn blue mt-clipboard" data-clipboard-action="copy" data-clipboard-target="#mt-target-1">
                                <i class="icon-note"></i> {{trans('home.referral_button')}}
                            </a>
                            <br>
                            <p>推广返利：（ 5$余额 + 5G流量 ）* 邀请人数 + 20%消费返利(可提现);
                        </div>
                    </div>
                </div>

                <!-- 邀请记录 -->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold"> {{trans('home.invite_user_title')}} </span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="table-scrollable">
                            <table class="table table-striped table-bordered table-hover table-checkable order-column">
                                <thead>
                                <tr>
                                    <th> # </th>
                                    <th> {{trans('home.invite_user_username')}} </th>
                                    <th> {{trans('home.invite_user_created_at')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($referralUserList->isEmpty())
                                    <tr>
                                        <td colspan="6" style="text-align: center;"> {{trans('home.referral_table_none')}} </td>
                                    </tr>
                                @else
                                    @foreach($referralUserList as $key => $vo)
                                        <tr class="odd gradeX">
                                            <td> {{$key + 1}} </td>
                                            <td> {{$vo->username}} </td>
                                            <td> {{$vo->created_at}} </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 col-sm-12">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $referralUserList->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 推广记录 -->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold"> {{trans('home.referral_title')}} </span>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn blue" onclick="autoExtractMoney()"> 提取余额 </button>
                            <!-- <button type="submit" class="btn green" onclick="extractMoney()"> 银行卡提现 </button> -->
                        </div>
                    </div>
                    <div class="portlet-body">  
                        <div class="table-scrollable">
                            <table class="table table-striped table-bordered table-hover table-checkable order-column">
                                <thead>
                                <tr>
                                    <th> # </th>
                                    <th> {{trans('home.referral_table_date')}} </th>
                                    <th> 订单 </th>
                                    <th> {{trans('home.referral_table_user')}} </th>
                                    <th> {{trans('home.referral_table_amount')}} </th>
                                    <th> {{trans('home.referral_table_commission')}} </th>
                                    <th> {{trans('home.referral_table_status')}} </th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($referralLogList->isEmpty())
                                    <tr>
                                        <td colspan="6" style="text-align: center;"> {{trans('home.referral_table_none')}} </td>
                                    </tr>
                                @else
                                    @foreach($referralLogList as $key => $referralLog)
                                        <tr class="odd gradeX">
                                            <td> {{$key + 1}} </td>
                                            <td> {{$referralLog->created_at}} </td>
                                            <td> @if($referralLog->order_id == 0) 邀请 @elseif($referralLog->order_id == -1) 注销 @else {{$referralLog->order_id}}@endif</td>
                                            <td> {{empty($referralLog->user) ? '【账号已删除】' : $referralLog->user->username}} </td>
                                            <td> ￥{{$referralLog->amount}} </td>
                                            <td> ￥{{$referralLog->ref_amount}} </td>
                                            <td>
                                                @if ($referralLog->status == 1)
                                                    <span class="label label-sm label-danger">申请中</span>
                                                @elseif($referralLog->status == 2)
                                                    <span class="label label-sm label-default">已提额</span>
                                                @elseif($referralLog->status == 3)
                                                    <span class="label label-sm label-default">已提现</span>
                                                @else
                                                    <span class="label label-sm label-info">未提现</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-5 col-sm-5">
                                <div class="dataTables_info" role="status" aria-live="polite">{{trans('home.referral_summary', ['total' => $referralLogList->total(), 'amount' => $canAmount, 'money' => $referral_money])}}<br>银行卡提现请先<a href="/profile#tab_2">设置收款信息</a>，每笔提现手续费1￥。</div>
                            </div>
                            <div class="col-md-7 col-sm-7">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $referralLogList->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 提现记录 -->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold"> {{trans('home.referral_apply_title')}} </span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="table-scrollable">
                            <table class="table table-striped table-bordered table-hover table-checkable order-column">
                                <thead>
                                <tr>
                                    <th> # </th>
                                    <th> {{trans('home.referral_apply_table_date')}} </th>
                                    <th> {{trans('home.referral_apply_table_amount')}} </th>
                                    <th> {{trans('home.referral_apply_table_status')}} </th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($referralApplyList->isEmpty())
                                    <tr>
                                        <td colspan="6" style="text-align: center;"> {{trans('home.referral_table_none')}} </td>
                                    </tr>
                                @else
                                    @foreach($referralApplyList as $key => $vo)
                                        <tr class="odd gradeX">
                                            <td> {{$key + 1}} </td>
                                            <td> {{$vo->created_at}} </td>
                                            <td> {{$vo->amount}} </td>
                                            <td>
                                                @if ($vo->status == 0)
                                                    <span class="label label-sm label-danger">待审核</span>
                                                @elseif($vo->status == 1)
                                                    <span class="label label-sm label-default">审核通过待打款</span>
                                                @elseif($vo->status == 2)
                                                    <span class="label label-sm label-default">已提额</span>
                                                @elseif($vo->status >= 2)
                                                    <span class="label label-sm label-default">已提现</span>
                                                @else
                                                    <span class="label label-sm label-info">驳回</span>
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
                                    {{ $referralLogList->links() }}
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
        // 申请提现
        function extractMoney() {
            $.post("/extractMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

        // 申请提现，自动打款到余额
        function autoExtractMoney() {
            $.post("/autoExtractMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

        // 信用额度
        function CreditMoney() {
            $.post("/CreditMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }
    </script>
@endsection
