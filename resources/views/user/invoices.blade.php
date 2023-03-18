@extends('user.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top: 0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            @if($fakapay == 'on')
            <!-- sdo2022-04-12 余额充值代码开始写 -->
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold">充值余额 (卡券/卡密/CDK方式)  </span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <form enctype="multipart/form-data" class="form-bordered" >
                            <div class="form-group" id="charge_coupon_code_url" >
                                <a href="{{$fakapay_10url}}" type="button" target="_blank" class="btn green">购买 10￥ 卡密（商品已做安全处理，可叠加）</a>
                            </div>
                            <div class="form-group">
                                <!-- <label for="charge_coupon" > 输入{{trans('home.coupon_code')}} </label> -->
                                <input type="text" class="form-control" name="charge_coupon" id="charge_coupon" placeholder="{{trans('home.please_input_coupon')}}">
                            </div>
                            <div class="alert alert-danger" style="display: none;" id="charge_msg"></div>
                            <div class="form-group">
                                <button type="button" class="btn red btn-outline" onclick="return coupon_charge();"><i class="icon-wallet"></i>  {{trans('home.recharge')}}</button>
                                <a class="btn btn-sm btn-outline blue" href="javascript:location.reload();">余额 ：{{ (Auth::user()->balance) / 100}}￥</a>
                            </div>
                            <div class="form-group">
                                <h5><span class="font-blue">* 商品名已安全处理，拍下即为充值卡密。可购买多个卡密，叠加充值。根据您需要充值的金额，拍下相应数量的卡密。
                                    <br>* 支付问题发邮件到 <span class="font-red">3ups@ssmail.win</span> 为您快速解决</span>
                                <a href="/article?id=46" type="button" target="_blank" class="btn btn-sm default">售后和 常见问题解决方案</a></h5>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            @if($clonepay == 'on')
            <!-- sdo2022-04-13 CP代付 -->
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold">充值余额 (代付方式)  </span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <form enctype="multipart/form-data" class="form-bordered" >
                            <div class="form-group" id="charge_coupon_code_url" >
                                @代付充值 -> <a href="{{$clonepay_url}}" type="button" target="_blank" class="btn red btn-outline">代付网站 (商品已做安全处理)</a>
                            </div>
                            <hr>
                            <div class="form-group">
                                1. 在 <code>代付网站</code> 用邮箱 <span class="font-blue">{{Auth::user()->username}}</span> 注册登录。 (邮箱相同，充值同步)
                                <br><br>2. 在 <code>代付网站</code> 支付充值 =  <span class="font-blue">本站充值</span> (充值记录，自动同步)
                                <br><br>3. 充值完成 -><a class="btn btn-sm btn-outline blue" href="javascript:location.reload();">刷新余额 ：{{ (Auth::user()->balance) / 100}}￥</a>
                                <br> 
                            </div>
                            <hr>
                            <div class="alert alert-danger" style="display: none;" id="clonepay_msg"></div>
                            <div class="form-group">
                                @常见问题 -> <button type="button" class="btn btn-sm blue btn-outline" onclick="return clonepay_Sync();"><i class="icon-wallet"></i>  同步充值记录 </button> 
                                <div>
                                    支付问题： 请与 3ups@ssmail.win(管理员邮箱) 邮件沟通 或 客服工单沟通 <a href="/article?id=46" type="button" target="_blank" class="btn btn-sm default"> 代付常见问题解决</a>
                                    <h4 class="font-red">未与 管理员 沟通就发起的支付投诉/发卡投诉, 账号ID/IP将被冻结</h4>
                                    <h6>*本站已加入支付联盟, 黑名单账号全网共享</h6>
                                </div>
                            </div>
                            
                            
                        </form>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-md-12">
                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold">卡券账单</span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="table-scrollable table-scrollable-borderless">
                            <table class="table table-hover table-light table-checkable order-column">
                                <thead>
                                    <tr>
                                        <th> # </th>
                                        <th> 名字 </th>
                                        <th> 卡券 </th>
                                        <th> 用途 </th>
                                        <th> 金额 </th>
                                        <th> 时间 </th>
                                    </tr>
                                </thead>
                                <tbody>
                                @if($couponList->isEmpty())
                                    <tr>
                                        <td colspan="8"><h3>没有充值记录</h3></td>
                                    </tr>
                                @else
                                    @foreach($couponList as $key => $coupon)
                                        <tr class="odd gradeX">
                                            <td>{{$key + 1}}</td>
                                            <td>{{$coupon->name}}</td>
                                            <td>{{$coupon->sn}}</td>
                                            <td>@if($coupon->type == 3 )
                                                充值券
                                                @elseif($coupon->type == 1)
                                                代金券
                                                @elseif($coupon->type == 2)
                                                优惠券
                                                @else
                                                其他
                                                @endif
                                                </td>
                                            <td>{{$coupon->amount / 100}}￥</td>
                                            <td>{{$coupon->updated_at}}</td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-12 col-sm-12">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $couponList->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END EXAMPLE TABLE PORTLET-->
                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold">消费账单</span>
                        </div>
                        <!-- <div style="text-align: center;"><span class="font-blue">账户等级：</span>
                                    <span class="font-red">{{Auth::user()->levelList->level_name}}&nbsp;&nbsp;&nbsp;</span>
                        <button type="button" class="btn btn-sm green btn-outline" onclick="reLevel()">等级校正</button>
                        <span class="font-blue">*等级按照您当前购买的套餐现在的等级的最大值计算。</span>
                        </div>
                        <div class="actions"></div> -->
                    </div>
                    <div class="portlet-body">
                        <div class="table-scrollable table-scrollable-borderless">
                            <table class="table table-hover table-light table-checkable order-column">
                                <thead>
                                    <tr>
                                        <th> # </th>
                                        <th> {{trans('home.invoice_table_id')}} </th>
                                        <th> {{trans('home.invoice_table_name')}} </th>
                                        <th> {{trans('home.invoice_table_pay_way')}} </th>
                                        <th> {{trans('home.invoice_table_price')}} </th>
                                        <th> {{trans('home.invoice_table_create_date')}} </th>
					                    <th> {{trans('home.invoice_table_expire_at')}} </th>
                                        <th> {{trans('home.invoice_table_status')}} </th>
                                    </tr>
                                </thead>
                                <tbody>
                                @if($orderList->isEmpty())
                                    <tr>
                                        <td colspan="8"><h3>{{trans('home.invoice_table_none')}}</h3></td>
                                    </tr>
                                @else
                                    @foreach($orderList as $key => $order)
                                        <tr class="odd gradeX">
                                            <td>{{$key + 1}}</td>
                                            <td><a href="{{url('invoice/' . $order->order_sn)}}">{{$order->order_sn}}</a></td>
                                            <td>{{empty($order->goods) ? trans('home.invoice_table_goods_deleted') : $order->goods->name}}</td>
                                            <td>{{$order->pay_way === 1 ? trans('home.service_pay_button') : trans('home.online_pay')}}</td>
                                            <td>￥{{$order->amount / 100}}</td>
                                            <td>{{$order->created_at}}</td>
					                        <td>{{$order->expire_at}}</td>
                                            <td>
                                                @if(!$order->is_expire)
                                                    @if($order->status == -1)
                                                        <a href="javascript:;" class="btn btn-sm default disabled"> {{trans('home.invoice_table_closed')}} </a>
                                                    @elseif($order->status == 0)
                                                        <a href="javascript:;" class="btn btn-sm dark disabled"> {{trans('home.invoice_table_wait_payment')}} </a>
                                                        <!-- @if(!empty($order->payment))
                                                            <a href="{{url('payment/' . $order->payment->sn)}}" target="_self" class="btn btn-sm red">{{trans('home.pay')}}</a>
                                                        @endif -->
                                                    @elseif($order->status == 1)
                                                        <a href="javascript:;" class="btn btn-sm dark disabled"> {{trans('home.invoice_table_wait_confirm')}} </a>
                                                    @elseif($order->status == 2)
                                                        <a href="javascript:;" class="btn btn-sm green disabled"> {{trans('home.invoice_table_wait_active')}} </a>
                                                    @else
                                                        <a href="javascript:;" class="btn btn-sm default disabled"> {{trans('home.invoice_table_expired')}} </a>
                                                    @endif
                                                @else
                                                    <a href="javascript:;" class="btn btn-sm default disabled"> {{trans('home.invoice_table_expired')}} </a>
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
                                    {{ $orderList->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END EXAMPLE TABLE PORTLET-->

                
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')

<script type="text/javascript">
    //    // 重置用户等级
    // function reLevel() {
    //     $.post("/reLevel", {_token:'{{csrf_token()}}'}, function(ret) {
    //         layer.msg(ret.message, {time:1000}, function() {
    //             window.location.reload();
    //         });
    //     });
    // }
    //    开启 vmess节点
    // function reUUID() {
    //     $.post("/reUUID", {_token:'{{csrf_token()}}'}, function(ret) {
    //         layer.msg(ret.message, {time:1000}, function() {
    //             window.location.reload();
    //         });
    //     });
    // }
    // sdo2022-04-12 自动去除 卡密中的 空格
    // 自动去除公钥和私钥中的空格和换行
    $("#charge_coupon").on('input', function () {
        $(this).val($(this).val().replace(/(\s+)/g, ''));
    });
    // sdo2022-04-12 充值
    function coupon_charge() {
        var charge_coupon = $("#charge_coupon").val();
        if ((charge_coupon == '' || charge_coupon == undefined)) {
            $("#charge_msg").show().html("{{trans('home.coupon_not_empty')}}");
            return false;
        }
        $.ajax({
            url:'/charge',
            type:"POST",
            data:{_token:'{{csrf_token()}}', coupon_sn:charge_coupon},
            beforeSend:function(){
                $("#charge_msg").show().html("{{trans('home.recharging')}}");
            },
            success:function(ret){
                if (ret.status == 'fail') {
                    $("#charge_msg").show().html(ret.message);
                    return false;
                }
                window.location.reload();
            },
            error:function(){
                $("#charge_msg").show().html("{{trans('home.error_response')}}");
            },
            complete:function(){}
        });
    }

    // sdo2022-04-13 同步代付记录
    @if($clonepay == 'on')
    function clonepay_Sync() {
        $.ajax({
            url:'/clonepay_sync',
            type:"POST",
            data:{_token:'{{csrf_token()}}'},
            beforeSend:function(){
                $("#clonepay_msg").show().html("正在自动同步...");
            },
            success:function(ret){
                $("#clonepay_msg").show().html(ret.message);
                return false;
            },
            error:function(){
                $("#clonepay_msg").show().html("{{trans('home.error_response')}}");
            },
        });
    }
    @endif
</script>
@endsection
