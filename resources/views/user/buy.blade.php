@extends('user.layouts')
@section('css')
    <link href="/assets/pages/css/invoice-2.min.css" rel="stylesheet" type="text/css" />
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
                                    <span class="font-blue">账户等级：</span>
                                    <span class="font-red">{{Auth::user()->levelList->level_name}}</span>
                                </h4>
                            </li>
                            @if (Auth::user()->balance < 0)
                            <li>
                                <h5>
                                    <span class="font-blue">花呗：</span>
                                    <span class="font-blue">{{ (Auth::user()->balance + Auth::user()->credit) / 100}} ￥/ {{Auth::user()->credit / 100}}￥</span>
                                </h5>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">本期账单：</span>
                                    <span class="font-red">{{Auth::user()->balance / 100}}￥(请在{{Auth::user()->credit_days}}日内还款)</span>
                                </h4>
                            </li>
                            @else
                            <li>
                                <h5>
                                    <span class="font-blue">花呗：</span>
                                    <span class="font-blue">{{Auth::user()->credit / 100}}￥</span>
                                </h5>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">账户余额：</span>
                                    <span class="font-red">{{ (Auth::user()->balance) / 100}}￥</span>
                                </h4>
                            </li>
                            
                            @endif
                            <li>
                                <a class="btn btn-sm red" href="#" data-toggle="modal" data-target="#charge_modal" style="color: #FFF;">{{trans('home.recharge')}}</a>
                            </li>


                        </ul>
                        <p><small>*等级越高，节点越多，带宽越大<br>*购买套餐时，您可以透支信用卡额度去购买套餐，每邀请一个用户注册，赠送5元信用卡余额。 如果您邀请的用户被系统删除，这个5元的信用额度会被扣掉。 邀请的人越多信用额度也越多。 请注意，这个信用额度可以透支，但是欠的钱要记得还啊</small></p>
                    </div>
                </div>
            </div>
        </div>
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="invoice-content-2 bordered">
            <div class="row invoice-body">
                <div class="col-xs-12 table-responsive">
                    <table class="table table-hover">
                        @if($goods->type == 3)
                            <thead>
                                <tr>
                                    <th class="invoice-title"> {{trans('home.service_name')}} </th>
                                    <th class="invoice-title text-center"> {{trans('home.service_price')}} </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 10px;">
                                        <h2>{{$goods->name}}</h2>
                                        充值金额：{{$goods->price/100}}元
                                        </td>
                                    <td class="text-center"> ￥{{$goods->price / 100}} </td>
                                </tr>
                            </tbody>
                        @else
                            <thead>
                                <tr>
                                    <th class="invoice-title"> {{trans('home.service_name')}} </th>
                                    <th class="invoice-title text-center"> {{trans('home.service_price')}} </th>
                                    <th class="invoice-title text-center"> {{trans('home.service_quantity')}} </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 10px;">
                                        <h2>{{$goods->name}}</h2>
                                        {{trans('home.service_traffic')}} {{$goods->traffic_label}}
                                        <br/>
                                        {{trans('home.service_days')}} {{$goods->days}} {{trans('home.day')}}
                                    </td>
                                    <td class="text-center"> ￥{{$goods->price / 100}} </td>
                                    <td class="text-center"> x 1 </td>
                                </tr>
                            </tbody>
                        @endif
                    </table>
                </div>
            </div>
            @if($goods->type <= 2)
                <div class="row invoice-subtotal">
                    <div class="col-xs-3">
                        <h2 class="invoice-title"> {{trans('home.service_subtotal_price')}} </h2>
                        <p class="invoice-desc"> ￥{{$goods->price / 100}} </p>
                    </div>
                    <div class="col-xs-3">
                        <h2 class="invoice-title"> {{trans('home.service_total_price')}} </h2>
                        <p class="invoice-desc grand-total"> ￥{{$goods->price / 100}} </p>
                    </div>
                    <div class="col-xs-6">
                        <h2 class="invoice-title"> {{trans('home.coupon')}} </h2>
                        <p class="invoice-desc">
                            <div class="input-group">
                                <input class="form-control" type="text" name="coupon_sn" id="coupon_sn" placeholder="{{trans('home.coupon')}}" />
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="redeemCoupon()"><i class="fa fa-refresh"></i> {{trans('home.redeem_coupon')}} </button>
                                </span>
                            </div>
                        </p>
                    </div>
                </div>
            @endif
            <div class="row">
                <div class="col-xs-12" style="text-align: right;">

                    <!-- @if(\App\Components\Helpers::systemConfig()['is_youzan'])
                        <a class="btn btn-lg red hidden-print" onclick="onlinePay(2)"> {{trans('home.online_pay')}} </a>
                    @elseif(\App\Components\Helpers::systemConfig()['is_trimepay'])
                        <a class="btn btn-lg green hidden-print" onclick="onlinePay(3)"> {{trans('home.online_pay')}} </a>
                    @elseif(\App\Components\Helpers::systemConfig()['is_alipay'])
                        <a class="btn btn-lg green hidden-print" onclick="onlinePay(4)"> 支付宝扫码 </a>
                    @elseif(\App\Components\Helpers::systemConfig()['is_f2fpay'])
                        <a class="btn btn-lg green hidden-print" onclick="onlinePay(5)"> 支付宝扫码 </a>
                    @endif -->
                    @if($goods->type <= 2)
                        <a class="btn btn-lg blue hidden-print uppercase" onclick="pay()"> {{trans('home.service_pay_button')}} </a>
                        <a class="btn btn-sm red" href="#" data-toggle="modal" data-target="#charge_modal" style="color: #FFF;">{{trans('home.recharge_balance')}}</a>
                    @endif
                </div>

            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
        <div id="charge_modal" class="modal fade" tabindex="-1" data-focus-on="input:first" data-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                        <h4 class="modal-title">{{trans('home.recharge_balance')}}</h4> 
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger" style="display: none; text-align: center;" id="charge_msg"></div>
                        <form action="#" method="post" class="form-horizontal">
                            <div class="form-body">
                                <div class="form-group">
                                    <label for="charge_type" class="col-md-4 control-label">{{trans('home.payment_method')}}</label>
                                    <div class="col-md-6">
                                        <select class="form-control" name="charge_type" id="charge_type">
                                            <option value="1" selected>{{trans('home.coupon_code')}}</option>
                                            @if(!$chargeGoodsList->isEmpty())
                                                <option value="2" >{{trans('home.online_pay')}}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                @if(!$chargeGoodsList->isEmpty())
                                    <div class="form-group" id="charge_balance" style="display: none;">
                                        <label for="online_pay" class="col-md-4 control-label">充值金额</label>
                                        <div class="col-md-6">
                                            <select class="form-control" name="online_pay" id="online_pay">
                                                @foreach($chargeGoodsList as $key => $chargeGoods)
                                                    <option value="{{$chargeGoods->id}}">充值{{$chargeGoods->price /100}}元</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif
                                <div class="form-group" id="charge_coupon_code_url" >
                                    <label for="charge_coupon" class="col-md-4 control-label"> 购买{{trans('home.coupon_code')}} </label>
                                    <div class="col-md-6">
                                    <a href="https://www.39faka.com/details/D0161C39" type="button" target="_blank" class="btn green">购买10￥卡密（商品已做安全处理，可叠加）</a>
                                        <h5><span class="font-blue">*商品名已安全处理，拍下即为充值卡密</span></h5>
                                        <h5><span class="font-blue">*请在发卡平台购买卡密后，在此充值余额</span></h5>
                                        <h5><span class="font-blue">*可购买多个卡密，叠加充值</span></h5>
                                        <h5><span class="font-blue">*根据您需要充值的金额，拍下相应数量的卡密</span></h5>
                                        <h5><span class="font-red">支付问题发邮件到 3ups@ssmail.win 为您快速解决</span></h5>
                                        <a href="/article?id=46" type="button" target="_blank" class="btn red">售后和 常见问题解决方案</a>
                                        <a href="https://www.39faka.com/details/6D960E4F" type="button" target="_blank" class="btn green">购买60￥卡密（商品已做安全处理，可叠加）</a>

                                    </div>
                                </div>
                                <div class="form-group" id="charge_coupon_code" >
                                    <label for="charge_coupon" class="col-md-4 control-label"> 输入{{trans('home.coupon_code')}} </label>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="charge_coupon" id="charge_coupon" placeholder="{{trans('home.please_input_coupon')}}">
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <a class="font-blue">当前余额：{{ (Auth::user()->balance) / 100}}￥ </a>   
                        <button type="button" data-dismiss="modal" class="btn dark btn-outline">{{trans('home.close')}}</button>
                        <button type="button" class="btn red btn-outline" onclick="return charge();">{{trans('home.recharge')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script src="/js/layer/layer.js" type="text/javascript"></script>
    <script type="text/javascript">
        // 校验优惠券是否可用
        function redeemCoupon() {
            var coupon_sn = $('#coupon_sn').val();
            var goods_price = '{{$goods->price / 100}}';

            $.ajax({
                type: "POST",
                url: "/redeemCoupon",
                async: false,
                data: {_token:'{{csrf_token()}}', coupon_sn:coupon_sn},
                dataType: 'json',
                beforeSend: function () {
                    index = layer.load(1, {
                        shade: [0.7,'#CCC']
                    });
                },
                success: function (ret) {
                    console.log(ret);
                    layer.close(index);
                    $("#coupon_sn").parent().removeClass("has-error");
                    $("#coupon_sn").parent().removeClass("has-success");
                    $(".input-group-addon").remove();
                    if (ret.status == 'success') {
                        $("#coupon_sn").parent().addClass('has-success');
                        $("#coupon_sn").parent().prepend('<span class="input-group-addon"><i class="fa fa-check fa-fw"></i></span>');

                        // 根据类型计算折扣后的总金额
                        var total_price = 0;
                        if (ret.data.type == '2') {
                            total_price = goods_price * ret.data.discount / 10;
                        } else {
                            total_price = goods_price - ret.data.amount/100; // 分转换为 元
                            total_price = total_price > 0 ? total_price : 0;
                        }

                        // 四舍五入，保留2位小数
                        total_price = total_price.toFixed(2);

                        $(".grand-total").text("￥" + total_price);
                    } else {
                        $(".grand-total").text("￥" + goods_price);
                        $("#coupon_sn").parent().addClass('has-error');
                        $("#coupon_sn").parent().remove('.input-group-addon');
                        $("#coupon_sn").parent().prepend('<span class="input-group-addon"><i class="fa fa-remove fa-fw"></i></span>');

                        layer.msg(ret.message);
                    }
                }
            });
        }

        // 在线支付
        function onlinePay(pay_type) {
            var goods_id = '{{$goods->id}}';
            var coupon_sn = $('#coupon_sn').val();

            index = layer.load(1, {
                shade: [0.7,'#CCC']
            });

            $.ajax({
                type: "POST",
                url: "/payment/create",
                async: false,
                data: {_token:'{{csrf_token()}}', goods_id:goods_id, coupon_sn:coupon_sn, pay_type:pay_type},
                dataType: 'json',
                beforeSend: function () {
                    index = layer.load(1, {
                        shade: [0.7,'#CCC']
                    });
                },
                success: function (ret) {
                    layer.msg(ret.message, {time:1300}, function() {
                        if (ret.status == 'success') {
                            // if (pay_type==4) {
                            //     // 如果是Alipay支付写入Alipay的支付页面
                            //     document.body.innerHTML += ret.data;
                            //     document.forms['alipaysubmit'].submit();
                            // } else {
                                window.location.href = ret.url;
                            // }
                        } else {
                            window.location.href = '/invoices';
                        }
                    });
                }
                //complete: function () {
                    //
                //}
            });
        }

        // 余额支付
        function pay() {
            var goods_id = '{{$goods->id}}';
            var coupon_sn = $('#coupon_sn').val();

            index = layer.load(1, {
                shade: [0.7,'#CCC']
            });

            $.ajax({
                type: "POST",
                url: "/buy/" + goods_id,
                async: false,
                data: {_token:'{{csrf_token()}}', coupon_sn:coupon_sn},
                dataType: 'json',
                beforeSend: function () {
                    index = layer.load(1, {
                        shade: [0.7,'#CCC']
                    });
                },
                success: function (ret) {
                    layer.msg(ret.message, {time:1300}, function() {
                        if (ret.status == 'success') {
                            window.location.href = '/invoices';
                        } else {
                            layer.close(index);
                        }
                    });
                }
            });
        }

        // 切换充值方式
        $("#charge_type").change(function(){
            if ($(this).val() == 2) {
                $("#charge_balance").show();
                $("#charge_coupon_code").hide();
                $("#charge_coupon_code_url").hide();
            } else {
                $("#charge_balance").hide();
                $("#charge_coupon_code").show();
                $("#charge_coupon_code_url").show();
            }
        });

        // 充值
        function charge() {
            var charge_type = $("#charge_type").val();
            var charge_coupon = $("#charge_coupon").val();
            var online_pay = $("#online_pay").val();

            if (charge_type == '2') {
                $("#charge_msg").show().html("正在跳转支付界面");
                window.location.href = '/buy/' + online_pay;
                return false;
            }

            if (charge_type == '1' && (charge_coupon == '' || charge_coupon == undefined)) {
                $("#charge_msg").show().html("{{trans('home.coupon_not_empty')}}");
                $("#charge_coupon").focus();
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

                    $("#charge_modal").modal("hide");
                    window.location.reload();
                },
                error:function(){
                    $("#charge_msg").show().html("{{trans('home.error_response')}}");
                },
                complete:function(){}
            });
        }
    </script>
@endsection
