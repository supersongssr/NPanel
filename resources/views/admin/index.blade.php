@extends('admin.layouts')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <div class="note note-info">
                    <table class="table table-hover table-light">
                        <tr>
                            <th>数据</th>
                            <th>All</th>
                            <th>6</th>
                            <th>5</th>
                            <th>4</th>
                            <th>3</th>
                            <th>2</th>
                            <th>1</th>
                            <th>0</th>
                        </tr>
                        <tr>
                            <td>Usr</td>
                            <td>{{$userall}}</td>
                            <td>{{$uservip6}}</td>
                            <td>{{$uservip5}}</td>
                            <td>{{$uservip4}}</td>
                            <td>{{$uservip3}}</td>
                            <td>{{$uservip2}}</td>
                            <td>{{$uservip1}}</td>
                            <td>{{$uservip0}}</td>
                        </tr>
                        <tr>
                            <td>G1</td>
                            <td>{{$nodeallgroup1}}|{{$userliveallgroup1}}</td>
                            <td>{{$nodelv6group1}}|{{$userlivevip6group1}}</td>
                            <td>{{$nodelv5group1}}|{{$userlivevip5group1}}</td>
                            <td>{{$nodelv4group1}}|{{$userlivevip4group1}}</td>
                            <td>{{$nodelv3group1}}|{{$userlivevip3group1}}</td>
                            <td>{{$nodelv2group1}}|{{$userlivevip2group1}}</td>
                            <td>{{$nodelv1group1}}|{{$userlivevip1group1}}</td>
                            <td>{{$nodelv0group1}}|{{$userlivevip0group1}}</td>
                        </tr>
                        <tr>
                            <td>G2</td>
                            <td>{{$nodeallgroup2}}|{{$userliveallgroup2}}</td>
                            <td>{{$nodelv6group2}}|{{$userlivevip6group2}}</td>
                            <td>{{$nodelv5group2}}|{{$userlivevip5group2}}</td>
                            <td>{{$nodelv4group2}}|{{$userlivevip4group2}}</td>
                            <td>{{$nodelv3group2}}|{{$userlivevip3group2}}</td>
                            <td>{{$nodelv2group2}}|{{$userlivevip2group2}}</td>
                            <td>{{$nodelv1group2}}|{{$userlivevip1group2}}</td>
                            <td>{{$nodelv0group2}}|{{$userlivevip0group2}}</td>
                        </tr>
                        <tr>
                            <td>G3</td>
                            <td>{{$nodeallgroup3}}|{{$userliveallgroup3}}</td>
                            <td>{{$nodelv6group3}}|{{$userlivevip6group3}}</td>
                            <td>{{$nodelv5group3}}|{{$userlivevip5group3}}</td>
                            <td>{{$nodelv4group3}}|{{$userlivevip4group3}}</td>
                            <td>{{$nodelv3group3}}|{{$userlivevip3group3}}</td>
                            <td>{{$nodelv2group3}}|{{$userlivevip2group3}}</td>
                            <td>{{$nodelv1group3}}|{{$userlivevip1group3}}</td>
                            <td>{{$nodelv0group3}}|{{$userlivevip0group3}}</td>
                        </tr>
                    </table>
                    <table class="table table-hover table-light">
                        <tr>
                            <td>分组</td>
                            <td>G3</td>
                            <td>G2</td>
                            <td>G1</td>
                        </tr>
                        <tr>
                            <td>VIP</td>
                            <td>{{$usergroup3}}</td>
                            <td>{{$usergroup2}}</td>
                            <td>{{$usergroup1}}</td>
                        </tr>
                    </table>
                    <table class="table table-hover table-light">
                        <tr>
                            <td>备注</td>
                            <td>config</td>
                            <td>date</td>
                        </tr>
                        <tr>
                            <td>日耗流量</td>
                            <td>all_traffic_daily_mark</td>
                            <td>{{$all_traffic_daily_mark}}</td>
                        </tr>
                        <tr>
                            <td>日供流量</td>
                            <td>all_traffic_daily_supply</td>
                            <td>{{$all_traffic_daily_supply}}</td>
                        </tr>
                        <tr>
                            <td>组1日耗</td>
                            <td>group1_traffic_daily_mark</td>
                            <td>{{$group1_traffic_daily_mark}}</td>
                        </tr>
                        <tr>
                            <td>组1日供</td>
                            <td>group1_traffic_daily_supply</td>
                            <td>{{$group1_traffic_daily_supply}}</td>
                        </tr>
                        <tr>
                            <td>组2日耗</td>
                            <td>group2_traffic_daily_mark</td>
                            <td>{{$group2_traffic_daily_mark}}</td>
                        </tr>
                        <tr>
                            <td>组2日供</td>
                            <td>group2_traffic_daily_supply</td>
                            <td>{{$group2_traffic_daily_supply}}</td>
                        </tr>
                    </table>
                </div>
                
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green-soft">
                                <span data-counter="counterup" data-value="{{$totalUserCount}}"></span>
                            </h3>
                            <small>总用户</small>
                        </div>
                        <div class="icon">
                            <i class="icon-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList?enable=1');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green-soft">
                                <span data-counter="counterup" data-value="{{$enableUserCount}}"></span>
                            </h3>
                            <small>有效用户</small>
                        </div>
                        <div class="icon">
                            <i class="icon-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green-sharp">
                                <span data-counter="counterup" data-value="{{$activeUserCount}}">0</span>
                            </h3>
                            <small>{{$expireDays}}日内活跃用户</small>
                        </div>
                        <div class="icon">
                            <i class="icon-user"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList?unActive=1');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green-sharp">
                                <span data-counter="counterup" data-value="{{$unActiveUserCount}}">0</span>
                            </h3>
                            <small>不活跃用户（超过{{$expireDays}}日未使用）</small>
                        </div>
                        <div class="icon">
                            <i class="icon-user"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList?online=1');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green-sharp">
                                <span data-counter="counterup" data-value="{{$onlineUserCount}}">0</span>
                            </h3>
                            <small>当前在线</small>
                        </div>
                        <div class="icon">
                            <i class="icon-user"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList?expireWarning=1');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$expireWarningUserCount}}">0</span>
                            </h3>
                            <small>临近到期</small>
                        </div>
                        <div class="icon">
                            <i class="icon-user-unfollow"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList?largeTraffic=1');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$largeTrafficUserCount}}">0</span>
                            </h3>
                            <small>流量大户（超过100G的用户）</small>
                        </div>
                        <div class="icon">
                            <i class="icon-user-unfollow"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userList?flowAbnormal=1');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$flowAbnormalUserCount}}">0</span>
                            </h3>
                            <small>1小时内流量异常</small>
                        </div>
                        <div class="icon">
                            <i class="icon-user-unfollow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/nodeList');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-blue-sharp">
                                <span data-counter="counterup" data-value="{{$nodeCount}}"></span>
                            </h3>
                            <small>节点</small>
                        </div>
                        <div class="icon">
                            <i class="icon-list"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/nodeList?status=0');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-blue-sharp">
                                <span data-counter="counterup" data-value="{{$unnormalNodeCount}}"></span>
                            </h3>
                            <small>维护中的节点</small>
                        </div>
                        <div class="icon">
                            <i class="icon-list"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/trafficLog');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-blue-sharp"> {{$totalFlowCount}} </h3>
                            <small>总消耗流量</small>
                        </div>
                        <div class="icon">
                            <i class="icon-speedometer"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/trafficLog');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-blue-sharp"> {{$flowCount}} </h3>
                            <small>30日内消耗流量</small>
                        </div>
                        <div class="icon">
                            <i class="icon-speedometer"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$totalOrder}}"></span>
                            </h3>
                            <small>总订单数</small>
                        </div>
                        <div class="icon">
                            <i class="icon-diamond"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$totalOnlinePayOrder}}"></span>
                            </h3>
                            <small>在线支付订单数</small>
                        </div>
                        <div class="icon">
                            <i class="icon-diamond"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$totalSuccessOrder}}"></span>
                            </h3>
                            <small>支付成功订单数</small>
                        </div>
                        <div class="icon">
                            <i class="icon-diamond"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-red">
                                <span data-counter="counterup" data-value="{{$todaySuccessOrder}}"></span>
                            </h3>
                            <small>今天成功订单数</small>
                        </div>
                        <div class="icon">
                            <i class="icon-diamond"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green">
                                ￥<span data-counter="counterup" data-value="{{$totalBalance}}"></span>
                            </h3>
                            <small>总余额</small>
                        </div>
                        <div class="icon">
                            <i class="icon-diamond"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered" onclick="skip('admin/userRebateList');">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green">
                                ￥<span data-counter="counterup" data-value="{{$totalWaitRefAmount}}"></span>
                            </h3>
                            <small>待提现佣金</small>
                        </div>
                        <div class="icon">
                            <i class="icon-credit-card"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <div class="dashboard-stat2 bordered">
                    <div class="display">
                        <div class="number">
                            <h3 class="font-green">
                                ￥<span data-counter="counterup" data-value="{{$totalRefAmount}}"></span>
                            </h3>
                            <small>已支出佣金</small>
                        </div>
                        <div class="icon">
                            <i class="icon-credit-card"></i>
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
    <script src="/assets/global/plugins/counterup/jquery.waypoints.min.js" type="text/javascript"></script>
    <script src="/assets/global/plugins/counterup/jquery.counterup.min.js" type="text/javascript"></script>
    <script type="text/javascript">
        function skip(url) {
            window.location.href = url;
        }
    </script>
@endsection
