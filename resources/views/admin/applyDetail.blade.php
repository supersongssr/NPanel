@extends('admin.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <div class="portlet light bordered">
                    <div class="portlet-title">
                        <div class="caption">
                            <span class="caption-subject font-dark bold uppercase">提现申请详情</span>
                        </div>
                        <div class="actions">
                            @if($info->status == -3)
                              <span class="label label-default label-danger"> 已驳回+收款地址报错 </span>
                            @elseif($info->status == -2)
                                <span class="label label-default label-danger"> 已驳回+更改提现方式 </span>
                            @elseif($info->status == -1)
                                <span class="label label-default label-danger"> 已驳回+重新提交 </span>
                            @elseif($info->status == 2)
                                <span class="label label-default label-success"> 已提现 </span>
                            @elseif($info->status == 3)
                                <span class="label label-default label-success"> 已代金券 </span>
                            @elseif($info->status == 4)
                                <span class="label label-default label-success"> 已微信 </span>
                            @elseif($info->status == 5)
                                <span class="label label-default label-success"> 已支付宝 </span>
                            @elseif($info->status == 6)
                                <span class="label label-default label-success"> 已USDT </span>
                            @endif
                                @if($info->status == 1)
                                <span class="label label-default label-success"> 审核通过待打款 </span>
                                @endif
                                <div class="btn-group">
                                    <a class="btn btn-sm blue dropdown-toggle" href="javascript:;" data-toggle="dropdown"> 审核
                                        <i class="fa fa-angle-down"></i>
                                    </a>
                                    <ul class="dropdown-menu pull-right">
                                      <li>
                                          <a href="javascript:setStatus('-3');"> <i class="fa fa-remove"></i>驳回：检查收款地址</a>
                                      </li>
                                        <li>
                                            <a href="javascript:setStatus('-2');"> <i class="fa fa-remove"></i>驳回：更换收款方式 </a>
                                        </li>
                                        <li>
                                            <a href="javascript:setStatus('-1');"> <i class="fa fa-remove"></i> 驳回：重新提交</a>
                                        </li>
                                        <li>
                                            <a href="javascript:setStatus('1');"> <i class="fa fa-circle-o"></i> 审核通过 </a>
                                        </li>
                                        <li>
                                            <a href="javascript:setStatus('2');"> <i class="fa fa-check"></i> 审核通过+现金打款 </a>
                                        </li>

                                        <li>
                                            <a href="javascript:setStatus('4');"> <i class="fa fa-check"></i> 审核通过+微信打款 </a>
                                        </li>

                                        <li>
                                            <a href="javascript:setStatus('5');"> <i class="fa fa-check"></i> 审核通过+支付宝打款 </a>
                                        </li>

                                        <li>
                                            <a href="javascript:setStatus('6');"> <i class="fa fa-check"></i> 审核通过+USDT打款 </a>
                                        </li>
                                    </ul>
                                </div>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="table-scrollable">
                            <table class="table table-striped table-hover table-checkable">
                                <thead>
                                    <tr >
                                        <th colspan="6">ID：{{$info->id}} | 申请人：{{$info->user->username}} | 提现金额：{{$info->amount /100}} | 申请时间：{{$info->created_at}}</th>
                                    </tr>
                                    <tr >
                                        <th> USDT({{$info->amount /100 /6.8}}$): {{$info->user->usdt}}</th>
                                    </tr>
                                    <tr >
                                        <th >WECHAT({{$info->amount /100 * 0.92}}￥)：<a href="{{$info->user->wechat}}" target="_blank">{{$info->user->wechat}}</a></th>
                                    </tr>
                                    <tr >
                                        <th >ALIPAY({{$info->amount /100 * 0.94}}￥)：<a href="{{$info->user->alipay}}" target="_blank">{{$info->user->alipay}}</a></th>
                                    </tr>
                                    <tr class="uppercase">
                                        <th> # </th>
                                        <th> 人 </th>
                                        <th> 订单 </th>
                                        <th> 金额 </th>
                                        <th> 金额 </th>
                                        <th> 状态 </th>
                                        <th> 时间 </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($list->isEmpty())
                                        <tr>
                                            <td colspan="6" style="text-align: center;">暂无数据</td>
                                        </tr>
                                    @else
                                        @foreach($list as $vo)
                                            <tr>
                                                <td> {{$vo->id}} </td>
                                                @if(empty($vo->user))
                                                <td> 【账号已删除】 </td>
                                                @else
                                                <td> <a href="{{'/admin/userBalanceLogList?username='.$vo->user->username}}" target="_blank">{{$vo->user->username}}</a>  </td>
                                                @endif
                                                <td> {{empty($vo->order) ? '注册返利' : $vo->order->goods->name}}</td>
                                                <td> {{$vo->amount / 100}} </td>
                                                <td> {{$vo->ref_amount / 100}} </td>
                                                <td> {{$vo->status }} </td>
                                                <td> {{$vo->created_at}} </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-md-4 col-sm-4">
                                <div class="dataTables_info" role="status" aria-live="polite">本申请共涉及 {{$list->total()}} 单</div>
                            </div>
                            <div class="col-md-8 col-sm-8">
                                <div class="dataTables_paginate paging_bootstrap_full_number pull-right">
                                    {{ $list->links() }}
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
    <script src="/js/layer/layer.js" type="text/javascript"></script>

    <script type="text/javascript">
        // 更改状态
        function setStatus(status) {
            $.post("/admin/setApplyStatus", {_token:'{{csrf_token()}}', id:'{{$info->id}}', status:status}, function(ret){
                layer.msg(ret.message, {time:1000}, function() {
                    window.location.reload();
                });
            });
        }
    </script>
@endsection
