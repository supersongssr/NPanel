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
                    <p>通过您的推广链接/邀请码注册您将获得如下奖励：</p>
                    <p><code>A：赠送 1G 流量 <br>B：赠送 5元 信用卡，可用于购买商品 <br>C：赠送 5元 奖励（设置收款信息，可提现）<br>D：用户消费时，奖励25%消费返利（设置收款信息，可提现）循环奖励，消费多少次奖励多少次 <br>E：将奖励 C 生成代金券 <br>F：将奖励 D 生成代金券</code><br><small>* AB+C/D 2+1奖励模式<br>* 具体提现标准请参考：<a href="/article?id=40" target="_blank">网站提现详细规则</a><br>* 简易规则：AB必返，CD 2选1，C提现条件： 所有邀请人消费金额 * 50% > 提现金额 ，D 提现条件：满{{$referral_money}}元可提现，EF与CD互斥</small></p>
                </div>
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
                            <p><code>邀请用户注册奖励： 5元 信用卡 + 5元现金（用于提现） + 25%消费返利（用于提现）<br>
                                请确保输入正常的收款信息，信息错误会导致收不到款！<a href="/profile#tab_2">点此设置我的收款信息</a></code></p>
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
                            <!-- <button type="submit" class="btn blue" onclick="autoExtractMoney()"> 提取余额 </button>
                            <button type="submit" class="btn green" onclick="extractMoney()"> 银行卡提现 </button> -->
                            <!-- <button type="submit" class="btn blue" onclick="ExtractAffMoney()"> 邀请奖励提现 </button>
                            <button type="submit" class="btn green" onclick="ExtractRefMoney()"> 消费返利提现 </button> -->
                            <button type="submit" class="btn blue" onclick="autoExtractAffMoney()"> 邀请奖励生成代金券 </button>
                            <button type="submit" class="btn green" onclick="autoExtractRefMoney()"> 消费返利生成代金券 </button>
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
                                            <td> ￥{{$referralLog->amount / 100}} </td>
                                            <td> ￥{{$referralLog->ref_amount / 100}} </td>
                                            <td>
                                                @if($referralLog->status == -3)
                                                    <span class="label label-sm label-default">可提现:检查收款地址</span>
                                                @elseif($referralLog->status == -2)
                                                    <span class="label label-sm label-default">可提现:更换收款方式</span>
                                                @elseif($referralLog->status == -1)
                                                    <span class="label label-sm label-default">可提现:重新申请提现</span>
                                                @elseif($referralLog->status == 0)
                                                    <span class="label label-sm label-default">未提现</span>
                                                @elseif ($referralLog->status == 1)
                                                    <span class="label label-sm label-danger">申请中</span>
                                                @elseif($referralLog->status == 2)
                                                    <span class="label label-sm label-default">已提现</span>
                                                @elseif($referralLog->status == 3)
                                                    <span class="label label-sm label-default">代金券</span>
                                                @elseif($referralLog->status == 4)
                                                    <span class="label label-sm label-default">微信</span>
                                                @elseif($referralLog->status == 5)
                                                    <span class="label label-sm label-default">支付宝</span>
                                                @elseif($referralLog->status == 6)
                                                    <span class="label label-sm label-default">USDT</span>
                                                @else
                                                    <span class="label label-sm label-info">状态异常请联系管理员</span>
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
                            <br>
                            * 为保护用户隐私，如果您不愿意提现您的邀请返利，您可以才选择将可提现的返利生成代金券，在网站购买商品时可抵扣相应的金额
                            <br>
                            <button type="submit" class="btn blue" onclick="autoExtractAffMoney()"> 邀请奖励生成代金券 </button>
                            <button type="submit" class="btn green" onclick="autoExtractRefMoney()"> 消费返利生成代金券 </button>
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
                                            <td> {{$vo->amount / 100}} </td>
                                            <td>
                                                @if ($vo->status == 0)
                                                    <span class="label label-sm label-danger">待审核</span>
                                                @elseif($vo->status == 1)
                                                    <span class="label label-sm label-default">审核通过待打款</span>
                                                @elseif($vo->status == 2)
                                                    <span class="label label-sm label-default">已提现</span>
                                                @elseif($vo->status == 3)
                                                    <span class="label label-sm label-default">代金券</span>
                                                @elseif($vo->status == 4)
                                                    <span class="label label-sm label-default">微信提现</span>
                                                @elseif($vo->status == 5)
                                                    <span class="label label-sm label-default">支付宝提现</span>
                                                @elseif($vo->status == 6)
                                                    <span class="label label-sm label-default">USDT提现</span>
                                                @elseif($vo->status == -1)
                                                    <span class="label label-sm label-info">重新申请提现</span>
                                                @elseif($vo->status == -2)
                                                    <span class="label label-sm label-info">更换收款方式</span>
                                                @elseif($vo->status == -3)
                                                    <span class="label label-sm label-info">检查收款地址</span>
                                                @else
                                                    <span class="label label-sm label-info">未知状态，请联系管理</span>
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

                <!-- 代金券记录 -->
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption font-dark">
                            <span class="caption-subject bold"> 代金券列表 </span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="table-scrollable">
                            <table class="table table-striped table-bordered table-hover table-checkable order-column">
                                <thead>
                                <tr>
                                    <th> # </th>
                                    <th> 代金券 </th>
                                    <th> 金额 </th>
                                    <th> 状态 </th>
                                    <th> 使用者 </th>
                                    <th> 生成时间 </th>
                                    <th> 有效期 </th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($couponList->isEmpty())
                                    <tr>
                                        <td colspan="6" style="text-align: center;"> {{trans('home.referral_table_none')}} </td>
                                    </tr>
                                @else
                                    @foreach($couponList as $key => $vo)
                                        <tr class="odd gradeX">
                                            <td> {{$key + 1}} </td>
                                            <td> {{$vo->sn}} </td>
                                            <td> ￥{{$vo->amount / 100}} </td>
                                            <td>
                                                @if ($vo->status == 0)
                                                    <span class="label label-sm label-danger">未使用</span>
                                                @elseif($vo->status == 1)
                                                    <span class="label label-sm label-default">已使用</span>
                                                @elseif($vo->status == 2)
                                                    <span class="label label-sm label-default">已过期</span>
                                                @endif
                                            </td>
                                            <td>#{{$vo->user_id}}</td>
                                            <td>{{$vo->created_at}}</td>
                                            <td>{{date('Y-m-d H:i:s',$vo->available_end )}}</td>
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

        // 申请邀请返利提现
        function ExtractAffMoney() {
            $.post("/ExtractAffMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

        // 申请 消费返利提现
        function ExtractRefMoney() {
            $.post("/ExtractRefMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

        // 自动提现生成 代金券
        function autoExtractAffMoney() {
            $.post("/autoExtractAffMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

        // 自动 消费返利生成代金券
        function autoExtractRefMoney() {
            $.post("/autoExtractRefMoney", {_token:'{{csrf_token()}}'}, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

    </script>
@endsection
