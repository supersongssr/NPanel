@extends('user.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->

        <div class="row">
            <div class="col-md-12">
                <div class="portlet light">
                    <div class="portlet-body">
                        <ul class="list-inline">
                            <li>
                                <h4>
                                    <span class="font-blue">账号：</span>
                                    <span class="font-red">{{Auth::user()->username}}</span>
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">状态：</span>
                                    @if ( Auth::user()->status == 0  )
                                        <span class="label label-danger">账号状态异常 请重新登陆</span>
                                    @elseif ( Auth::user()->status == 1 )
                                        <span class="label label-success">账号正常</span>
                                    @else
                                        <span class="label label-info">账号异常,请重新登录</span>
                                    @endif
                                    @if ( Auth::user()->enable == 0  )
                                        <span class="label label-danger">!请检查流量 和 等级</span>
                                    @elseif ( Auth::user()->enable == 1 )
                                        <span class="label label-success">节点正常</span>
                                    @else
                                        <span class="label label-info">!请检查流量 和 等级</span>
                                    @endif
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">{{trans('home.account_expire')}}：</span>
                                    <span class="font-red">@if(date('Y-m-d') > Auth::user()->expire_time) {{trans('home.expired')}} @else {{Auth::user()->expire_time}} @endif</span>
                                </h4>
                            </li>
                            <li>
                                <h4>
                                    <span class="font-blue">等级：</span>
                                    <span class="font-red">Lv.{{Auth::user()->level}}</span>
                                </h4>
                            </li>
                            
                            @if(Auth::user()->traffic_reset_day)
                            <li>
                                <h4>
                                    <span class="font-red">总流量：</span>
                                    <span class="font-red">{{ flowAutoShow(Auth::user()->u + Auth::user()->d)}} /{{flowAutoShow(Auth::user()->transfer_enable)}}</span>
                                    <span class="font-blue">( {{flowAutoShow(Auth::user()->u)}}/{{flowAutoShow(Auth::user()->transfer_enable - Auth::user()->transfer_monthly)}}流量包 +</span>
                                    <span class="font-blue">{{flowAutoShow(Auth::user()->d)}}/{{flowAutoShow(Auth::user()->transfer_monthly)}}每月流量 {{Auth::user()->traffic_reset_day}}号重置)</span>
                                    
                                </h4>
                            </li>
                            @else
                            <li>
                                <h4>
                                    <span class="font-blue">流量：</span>
                                    <span class="font-blue"> {{flowAutoShow(Auth::user()->u + Auth::user()->d)}} /{{flowAutoShow(Auth::user()->transfer_enable)}} </span>
                                </h4>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="row">
            <div class="col-md-12">
                <div class="portlet light bordered">
                    <div class="portlet-title tabbable-line">

                        <div class="caption">
                            <i class="icon-directions font-green hide"></i>
                            <span class="caption-subject font-blue bold"> {{trans('home.announcement')}} </span>
                        </div>
                        <div class="actions">
                            <span class="caption-subject">
                                <a class="btn btn-sm green" href="subscribe">订阅、使用教程</a>
                                <a class="btn btn-sm blue" href="javascript:checkIn();"> 签到 </a>
                            </span>
                        </div>
                    </div>
                    <div class="portlet-body">
                        <div class="tab-content">
                            <div class="alert alert-danger">
                                <p> 家:{{\App\Components\Helpers::systemConfig()['website_name']}} 保存书签呦&nbsp TG群:dossrxyz</p>
                            </div>
                            @if($notice)
                                {!!$notice->content!!}
                            @else
                                <div style="text-align: center;">
                                    <h3>暂无公告</h3>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="portlet light bordered">
                    <div class="portlet-body">
                        <div id="chart2" style="width: auto;height:450px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="portlet light bordered">
                    <div class="portlet-body">
                        <div id="chart1" style="width: auto;height:450px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script src="/assets/global/plugins/echarts/echarts.min.js" type="text/javascript"></script>

    <script type="text/javascript">
        // 签到
        function checkIn() {
            $.post('/checkIn', function(ret) {
                if (ret.status == 'success') {
                    layer.alert(ret.message, {icon:1, title:'提示'});
                } else {
                    layer.alert(ret.message, {icon:2, title:'提示'})
                }
            });
        }
    </script>

    <script type="text/javascript">
        var myChart = echarts.init(document.getElementById('chart1'));

        option = {
            title: {
                text: '{{trans('home.traffic_log_30days')}}',
                subtext: '{{trans('home.traffic_log_unit')}}'
            },
            tooltip: {
                trigger: 'axis'
            },
            toolbox: {
                show: true,
                feature: {
                    saveAsImage: {}
                }
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30']
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    formatter: '{value} G'
                }
            },
            series: [
                @if(!empty($trafficDaily))
                {
                    name:'{{trans('home.traffic_log_keywords')}}',
                    type:'line',
                    data:[{!! $trafficDaily !!}],
                    markPoint: {
                        data: [
                            {type: 'max', name: '{{trans('home.traffic_log_max')}}'}
                        ]
                    }
                }
                @endif
            ]
        };

        myChart.setOption(option);
    </script>
    <script type="text/javascript">
        var myChart = echarts.init(document.getElementById('chart2'));

        option = {
            title: {
                text: '{{trans('home.traffic_log_24hours')}}',
                subtext: '{{trans('home.traffic_log_unit')}}'
            },
            tooltip: {
                trigger: 'axis'
            },
            toolbox: {
                show: true,
                feature: {
                    saveAsImage: {}
                }
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: ['0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23']
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    formatter: '{value} G'
                }
            },
            series: [
                @if(!empty($trafficHourly))
                {
                    name:'{{trans('home.traffic_log_keywords')}}',
                    type:'line',
                    data:[{!! $trafficHourly !!}],
                    markPoint: {
                        data: [
                            {type: 'max', name: '{{trans('home.traffic_log_max')}}'}
                        ]
                    }
                }
                @endif
            ]
        };

        myChart.setOption(option);
    </script>
@endsection
