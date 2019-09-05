@extends('auth.layouts')
@section('title', '账号申请解封')
@section('css')
    <link href="/assets/pages/css/login-2.min.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    @if (Session::get('successMsg'))
        <div class="alert alert-success">
            <button class="close" data-close="alert"></button>
            <span> {{Session::get('successMsg')}} </span>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <span> {{$errors->first()}} </span>
        </div>
    @endif
    <!-- BEGIN FORGOT PASSWORD FORM -->
    <form class="forget-form" action="/reActiveUser" method="post" style="display: block;">
            <div class="form-title">
                <span class="form-title">账号申请解封</span>
            </div>
            <div class="alert alert-danger">
                <span> 为什么会封禁账户？<br> 1、 违反注册协议的账户。 <br>2、 账户余额 < 0 的账户。 <br> PS: 如果您余额 < 0 ，请激活账号后，充值至余额 > 0 ！ (参考 网站 - 帮助中心 - 邀请返利 提现说明 )</span>
            </div>
            <div class="form-group">
                <input class="form-control placeholder-no-fix" type="text" autocomplete="off" placeholder="{{trans('active.username_placeholder')}} 或 邮箱" name="username" value="{{Request::get('username')}}" required />
                <input type="hidden" name="_token" value="{{csrf_token()}}" />
            </div>
        <div class="form-actions">
            <button type="button" class="btn btn-default" onclick="login()">{{trans('active.back')}}</button>
            <button type="submit" class="btn red uppercase pull-right">申请解封</button>
        </div>
    </form>
    <!-- END FORGOT PASSWORD FORM -->
@endsection
@section('script')
    <script type="text/javascript">
        // 登录
        function login() {
            window.location.href = '/login';
        }
    </script>
@endsection