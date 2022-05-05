@extends('user.layouts')
@section('css')
    <link href="/assets/pages/css/profile.min.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top: 0px; min-height: 354px;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                @if (Session::has('successMsg'))
                    <div class="alert alert-success alert-dismissable">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true"></button>
                        {{Session::get('successMsg')}}
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <span> {{$errors->first()}} </span>
                    </div>
                @endif
                <!-- BEGIN PROFILE CONTENT -->
                <div class="profile-content">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="portlet light bordered">
                                <div class="portlet-title tabbable-line">
                                    <div class="caption caption-md">
                                        <i class="icon-globe theme-font hide"></i>
                                        <span class="caption-subject font-blue-madison bold uppercase">{{trans('home.profile')}}</span>
                                    </div>
                                    <ul class="nav nav-tabs">
                                        <li class="active">
                                            <a href="#tab_6" data-toggle="tab">网络优化</a>
                                        </li>
                                        <li >
                                            <a href="#tab_1" data-toggle="tab">账号密码</a>
                                        </li>
                                        <li>
                                            <a href="#tab_2" data-toggle="tab">收款信息</a>
                                        </li>
                                        <li>
                                            <a href="#tab_3" data-toggle="tab">节点密码</a>
                                        </li>
                                        <!-- <li >
                                            <a href="#tab_4" data-toggle="tab">CN+中转入口</a>
                                        </li> -->
                                        <li >
                                            <a href="#tab_5" data-toggle="tab">帐号升级</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="portlet-body">
                                    <div class="tab-content">
                                        <div class="tab-pane " id="tab_1">
                                            <form action="/profile" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    <label class="control-label">{{trans('home.current_password')}}</label>
                                                    <input type="password" class="form-control" name="old_password" id="old_password" autofocus required />
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label">{{trans('home.new_password')}}</label>
                                                    <input type="password" class="form-control" name="new_password" id="new_password" required />
                                                </div>
                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class=" col-md-4">
                                                            <button type="submit" class="btn green">{{trans('home.submit')}}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="tab-pane" id="tab_2">
                                            <form action="profile" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    <label class="control-label"> 打款失败联系方式：QQ / Wechat / Tg / Phone / Email / Facebook 等</label>
                                                    <input type="text" class="form-control" name="qq" value="{{Auth::user()->qq}}" id="qq" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label">USDT-TRC20 (CN地区已失效) </label>
                                                    <input type="text" class="form-control" name="usdt" value="{{Auth::user()->usdt}}" id="usdt" >
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label">微信收款二维码 ()</label>
                                                    <input type="text" class="form-control" name="wechat" value="{{Auth::user()->wechat}}" id="wechat" >
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label">支付宝收款二维码 ()</label>
                                                    <input type="text" class="form-control" name="alipay" value="{{Auth::user()->alipay}}" id="alipay" >
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <p>请使用 <a href="https://imgurl.org/" target="_blank">免费图床</a>上传收款码，复制 URL 地址到上面。<br><code>*请务必检查您的收款信息是否正确，如果由于您错误的设置无法收到打款，只能自己承担呦</code><br><code>*手续费由第三方平台手续，具体手续费以第三方平台为准。</code></p>
                                                <p><code>您的微信二维码为：</code><img src="{{Auth::user()->wechat}}" onerror='this.src="/assets/images/noimage.png"' style="max-width: 150px; max-height: 150px;">
                                                  <code>您的支付宝二维码为：</code><img src="{{Auth::user()->alipay}}" onerror='this.src="/assets/images/noimage.png"' style="max-width: 150px; max-height: 150px;"> </p>
                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <button type="submit" class="btn green">{{trans('home.submit')}}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="tab-pane" id="tab_3">
                                            <form action="/profile" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    <label class="control-label"> SR节点配置密码 </label>
                                                    <input type="text" class="form-control" name="passwd" value="{{Auth::user()->passwd}}" id="passwd" required />
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label"> V2ray节点配置密码 </label>
                                                        <div class="input-group">
                                                            <input class="form-control" type="text" name="vmess_id" value="{{Auth::user()->vmess_id}}" id="vmess_id" autocomplete="off" />
                                                            <span class="input-group-btn">
                                                                <button class="btn btn-success" type="button" onclick="makeVmessId()"> <i class="fa fa-refresh"></i> </button>
                                                            </span>
                                                        </div>
                                                        <span class="help-block"> <code> 修改后请保持 格式不变； 不懂？可以选择系统随机设置 </code></span>
                                                </div>
                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <button type="submit" class="btn green"> {{trans('home.submit')}} </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="tab-pane" id="tab_4">
                                            <form action="/profile" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    {{ csrf_field() }}
                                                    <select class="form-control" name="cncdn" id="cncdn">
                                                        @if (Auth::user()->cncdn)
                                                        <option value="{{Auth::user()->cncdn}}">{{Auth::user()->cncdn}}</option>
                                                        @else
                                                        <option value="666">默认随机</option>
                                                        @endif
                                                        <option value="666">默认随机</option>
                                                        @foreach($cncdns as $cncdn)
                                                            <option value="{{$cncdn->area}}" >{{$cncdn->area}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <p><code>*修改后，请软件中更新节点<br>*移动电信建议选联通入口<br>*这是对 CN+ 节点有效的呦</code><br>什么条件下使用它： 当您使用CN+节点网速不佳的时候。表现在移动、电信的网络被运营商Qos严重的情况下。
                                                    <br>技术原理：用户自身网络不佳时，通过中转服务器，来加速用户的上网速度。</p>
                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <button type="submit" class="btn green"> {{trans('home.submit')}} </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="tab-pane" id="tab_5">
                                            <form action="/cnupdate" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    <label class="control-label"> 请输入您的账号，以确定您已阅读升级条款，并同意升级，这项操作不可逆（适用于2019年前老帐号升级新版功能）： </label>
                                                    <input type="text" class="form-control" name="cn_update" value="请输入账号以确保您同意升级" id="cn_update" required />
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <p><code>*升级后，系统会重新计算您的总流量<br>*您的总流量 = 所有套餐流量之和 （每月流量按照每月重置）<br>*这意味着您除了购买的流量，签到和邀请返利的流量可能会被清空。<br>*我们的商品有所变动的情况下，以现有商品的标准为准<br>您的等级会被重置为您购买商品中的最大等级，以当前套餐为准<br>*请您仔细阅读我的账单页面和购买服务页面，以对比前后套餐变化，以确保您确定升级</code></p>
                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <button type="submit" class="btn green"> {{trans('home.submit')}} </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="tab-pane  active" id="tab_6">
                                            <form action="profile" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    <label class="control-label">请填入IP （默认为空值）</label>
                                                    <input type="text" class="form-control" name="cfcdn" value="{{Auth::user()->cfcdn}}" id="cfcdn"  />
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <p>
                                                  什么条件下使用它： 直连网速不佳的时候。尤其是移动/电信。<br>
                                                <br><code>原理：某些用户的网络IP被运营商给限制，无法使用国际带宽，或者国际带宽被限制在500K左右。 设置CF+ 的IP优化，可以避开运营商的IP限制和限速。</code>
                                                <br>WIN教程: <code><a href="https://www.baipiao.eu.org/batch.zip" target="_blank">点我下载 - 内附使用说明</a></code>
                                                <br>Linux教程：<code>curl https://www.baipiao.eu.org/cf.sh -o cf.sh && chmod +x cf.sh && ./cf.sh</code>
                                                </p>


                                                <div class="form-actions">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <button type="submit" class="btn green">{{trans('home.submit')}}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END PROFILE CONTENT -->
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
<script type="text/javascript">
    // 生成随机VmessId
    function makeVmessId() {
        $.get("/makeVmessId",  function(ret) {
            $("#vmess_id").val(ret);
        });
    }
</script>


@endsection
@section('script')
@endsection
