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
                            <li>
                                <h5>
                                    <span class="font-blue">花呗：</span>
                                    <span class="font-blue">{{Auth::user()->credit / 100}}￥</span>
                                </h5>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">余额：</span>
                                    @if (Auth::user()->balance < 0)
                                    <span class="font-red">{{Auth::user()->balance / 100}} ￥ (请在 {{Auth::user()->credit_days}} 日内还款)</span>
                                    @else
                                    <span class="font-red">{{ (Auth::user()->balance) / 100}}￥</span>
                                    @endif
                                </h4>
                            </li>
                            <li>
                                <a class="btn red btn-outline" href="/invoices" ><i class="icon-wallet"></i> {{trans('home.recharge')}}</a>
                            </li>
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

        
    </script>
@endsection
