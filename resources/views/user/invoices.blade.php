@extends('user.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top: 0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold">消费账单</span>
                        </div>
                        <div style="text-align: center;"><span class="font-blue">账户等级：</span>
                                    <span class="font-red">{{Auth::user()->levelList->level_name}}&nbsp;&nbsp;&nbsp;</span>
                        <button type="button" class="btn btn-sm green btn-outline" onclick="reLevel()">等级校正</button>
                        <span class="font-blue">*等级按照您当前购买的套餐现在的等级的最大值计算。</span>
                        </div>
                        <div class="actions"></div>
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

                <!-- BEGIN EXAMPLE TABLE PORTLET-->
                <div class="portlet light">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold">充值账单</span>
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
                                                充值卡券
                                                @elseif($coupon->type == 1)
                                                代金券
                                                @else
                                                优惠券
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
    function reLevel() {
        $.post("/reLevel", {_token:'{{csrf_token()}}'}, function(ret) {
            layer.msg(ret.message, {time:1000}, function() {
                window.location.reload();
            });
        });
    }
    //    开启 vmess节点
    function reUUID() {
        $.post("/reUUID", {_token:'{{csrf_token()}}'}, function(ret) {
            layer.msg(ret.message, {time:1000}, function() {
                window.location.reload();
            });
        });
    }
</script>
@endsection
