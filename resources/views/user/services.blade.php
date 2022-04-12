@extends('user.layouts')
@section('css')
    <link href="/assets/pages/css/pricing.min.css" rel="stylesheet" type="text/css" />
    <link href="/assets/global/plugins/fancybox/source/jquery.fancybox.css" rel="stylesheet" type="text/css" />
    <style>
        .fancybox > img {
            width: 75px;
            height: 75px;
        }
    </style>
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <div class="row">
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-body">
                        <ul class="list-inline">
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


                           <!--  <li>
                                <h4>
                                    <span class="font-blue">账户等级：</span>
                                    <span class="font-red">{{Auth::user()->levelList->level_name}}</span>
                                </h4>
                            </li> -->
                        </ul>
                        <p><small>*等级高，节点多，带宽大，流量足。 <a href="/profile#tab_6">移动、电信用户可设置CF+中转。</a>  <br>*<code>CF+</code>:网络优化技术(直连网络不佳网络优化用它) <code>CN+</code>:中转技术(移动电信强力推荐) <code>NetFLix+</code>:支持网飞视频 <code>Azure+</code>:微软强力G口带宽 <code>BGP+</code>:三网混合加速 </small></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet light">
                        <div class="tabbable-line">
                            <ul class="nav nav-tabs">
                                <li>
                                    <a href="#services1" data-toggle="tab"> <i class="fa fa-book"></i> 说明 </a>
                                </li>
                                <li>
                                    <a href="#services2" data-toggle="tab"> <i class="fa fa-cloud"></i> 按时间买 </a>
                                </li>
                                <li  class="active">
                                    <a href="#services3" data-toggle="tab"> <i class="fa fa-jsfiddle"></i> 按流量买 </a>
                                </li>
                            </ul>
                            <div class="tab-content " style="font-size:16px;">
                                <div class="tab-pane" id="services1">
                                    <h4>如何购买：</h4>
                                    <ol>
                                        <li>① 充值余额</li>
                                        <li>② 购买商品</li>
                                    </ol>
                                    <h4>按时间买：</h4>
                                    <ol>
                                        <li>流量每月重置</li>
                                        <li>等级越高，流量越多、带宽越大、特权越多</li>
                                    </ol>
                                    <h4>按流量买：</h4>
                                    <ol>
                                        <li>一次性流量</li>
                                        <li>等级越高，节点越多、带宽越大、特权越多</li>
                                    </ol>
                                    <h4>商品说明：</h4>
                                    <ol>
                                        <li>购买多个商品时:</li>
                                        <li>流量叠加、特权叠加、节点叠加</li>
                                        <li>等级、带宽取现有商品中最大值</li>
                                        <li>账号有效期取现有商品中最大值</li>
                                        <li>流量扣除顺序：按时间买的流量 -> 按流量买的流量 -> 系统赠送流量(签到、返利等)</li>
                                        <li><code>*Bt、P2P、网盘上载流量优先扣除 按流量买的流量 -> 系统赠送的流量 -> 按时间买的流量</code></li>
                                    </ol>


                                </div>
                                <div class="tab-pane" id="services2">
                                    <div class="pricing-content-1" style="padding-top: 10px;">
                                        <div class="row">
                                            @if($packageList->isEmpty())
                                                <div class="col-md-12" style="text-align: center;">
                                                    <h2>暂无基础套餐</h2>
                                                </div>
                                            @else
                                                @foreach($packageList as $key => $goods)
                                                    <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12">
                                                        <div class="price-column-container border-active" style="margin-bottom: 20px;">
                                                            <div class="price-table-head bg-{{$goods->color}}">
                                                                <h2 class="no-margin">{{$goods->name}}</h2>
                                                            </div>
                                                            <div class="arrow-down border-top-{{$goods->color}}"></div>
                                                            <div class="price-table-pricing">
                                                                <h3><sup class="price-sign">￥</sup>{{$goods->price / 100}}</h3>
                                                                @if($goods->is_hot)
                                                                    <div class="price-ribbon">热销</div>
                                                                @endif
                                                            </div>
                                                            <div class="price-table-content">
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-bar-chart"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding">每月：{{$goods->traffic_label}}</div>
                                                                </div>
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-clock"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding">时长：{{$goods->days}}天</div>
                                                                </div>
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-rocket"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding">节点：{{$goods->level}}级</div>
                                                                </div>
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-tag"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding"><code>{{$goods->desc}}</code></div>
                                                                </div>
                                                            </div>
                                                            <div class="arrow-down arrow-grey"></div>
                                                            <div class="price-table-footer">
                                                                <button type="button" class="btn {{$goods->color}} {{$goods->is_hot ? '' : 'btn-outline'}} sbold uppercase price-button" onclick="buy('{{$goods->id}}')">{{trans('home.service_buy_button')}}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane active" id="services3">
                                    <div class="pricing-content-1" style="padding-top: 10px;">
                                        <div class="row">
                                            @if($trafficList->isEmpty())
                                                <div class="col-md-12" style="text-align: center;">
                                                    <h2>暂无流量包</h2>
                                                </div>
                                            @else
                                                @foreach($trafficList as $key => $goods)
                                                    <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12">
                                                        <div class="price-column-container border-active" style="margin-bottom: 20px;">
                                                            <div class="price-table-head bg-{{$goods->color}}">
                                                                <h2 class="no-margin">{{$goods->name}}</h2>
                                                            </div>
                                                            <div class="arrow-down border-top-{{$goods->color}}"></div>
                                                            <div class="price-table-pricing">
                                                                <h3><sup class="price-sign">￥</sup>{{$goods->price / 100}}</h3>
                                                                @if($goods->is_hot)
                                                                    <div class="price-ribbon">热销</div>
                                                                @endif
                                                            </div>
                                                            <div class="price-table-content">
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-bar-chart"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding">流量：{{$goods->traffic_label}}</div>
                                                                </div>
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-clock"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding">时长：{{$goods->days}}天</div>
                                                                </div>
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-rocket"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding">节点: {{$goods->level}}级</div>
                                                                </div>
                                                                <div class="row mobile-padding">
                                                                    <div class="col-xs-3 text-right mobile-padding">
                                                                        <i class="icon-tag"></i>
                                                                    </div>
                                                                    <div class="col-xs-9 text-left mobile-padding"><code>{{$goods->desc}}</code></div>
                                                                </div>
                                                            </div>
                                                            <div class="arrow-down arrow-grey"></div>
                                                            <div class="price-table-footer">
                                                                <button type="button" class="btn {{$goods->color}} {{$goods->is_hot ? '' : 'btn-outline'}} sbold uppercase price-button" onclick="buy('{{$goods->id}}')">{{trans('home.service_buy_button')}}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                        <a href="https://www.yiranpay.com/mall/?link=m622ea2c5739dd" type="button" target="_blank" class="btn green">购买10￥卡密（商品已做安全处理，可叠加）</a>
                                        <h5><span class="font-blue">*商品名已安全处理，拍下即为充值卡密</span></h5>
                                        <h5><span class="font-blue">*请在发卡平台购买卡密后，在此充值余额</span></h5>
                                        <h5><span class="font-blue">*可购买多个卡密，叠加充值</span></h5>
                                        <h5><span class="font-blue">*根据您需要充值的金额，拍下相应数量的卡密</span></h5>
                                        <h5><span class="font-red">支付问题发邮件到 3ups@ssmail.win 为您快速解决</span></h5>
                                        <a href="/article?id=46" type="button" target="_blank" class="btn red">售后和 常见问题解决方案</a>
                                        <!-- <a href="https://www.39faka.com/details/6D960E4F" type="button" target="_blank" class="btn green">购买60￥卡密（商品已做安全处理，可叠加）</a> -->

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
                        <button type="button" data-dismiss="modal" class="btn dark btn-outline">{{trans('home.close')}}</button>
                        <button type="button" class="btn red btn-outline" onclick="return charge();">{{trans('home.recharge')}}</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script src="/assets/global/plugins/fancybox/source/jquery.fancybox.js" type="text/javascript"></script>

    <script type="text/javascript">
        function buy(goods_id) {
            window.location.href = '/buy/' + goods_id;
        }

        // 查看商品图片
        $(document).ready(function () {
            $('.fancybox').fancybox({
                openEffect: 'elastic',
                closeEffect: 'elastic'
            })
        })

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
