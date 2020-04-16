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
                                            <a href="#tab_4" data-toggle="tab">CN+入口选择</a>
                                        </li>
                                        <li >
                                            <a href="#tab_5" data-toggle="tab">CN+节点申请</a>
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
                                                    <label class="control-label">姓名</label>
                                                    <input type="text" class="form-control" name="wechat" value="{{Auth::user()->wechat}}" id="wechat" required />
                                                    <input type="hidden" name="_token" value="{{csrf_token()}}" />
                                                </div>
                                                <div class="form-group">
                                                    <label class="control-label"> 银行卡号 </label>
                                                    <input type="text" class="form-control" name="qq" value="{{Auth::user()->qq}}" id="qq" required />
                                                </div>
                                                <p><code>*请务必检查填写信息是否正确，如果由于您填写的账号的信息错误导致无法受到打款，只能自己承担呦</code></p>
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
                                        <div class="tab-pane active" id="tab_4">
                                            <form action="/profile" method="post" enctype="multipart/form-data" class="form-bordered">
                                                <div class="form-group">
                                                    {{ csrf_field() }}
                                                    <select class="form-control" name="cncdn" id="cncdn">
                                                        <option value="0">CN+ 节点 中转入口 选择</option>
                                                        @foreach($cncdns as $cncdn)
                                                            <option value="{{$cncdn->areaid}}" {{ $cncdn->areaid == Auth::user()->cncdn ? 'selected' : ''}} >{{$cncdn->area}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <p><code>*修改后，请软件中更新节点<br>*不会选？就选联通入口<br>*这是对 CN+ 节点有效的呦</code></p>
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
                                                    <label class="control-label"> 请输入您的账号，以确定您已阅读升级条款，并同意升级，这项操作不可逆： </label>
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