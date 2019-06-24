@extends('auth.layouts')
@section('title', trans('active.title'))
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
    <form class="forget-form" action="{{url('activeUser')}}" method="post" style="display: block;">
        @if(\App\Components\Helpers::systemConfig()['is_active_register'])
            <div class="form-title">
                <span class="form-title">{{trans('active.title')}}</span>
            </div>
            <div class="alert alert-danger">
                <span> 为什么需要激活账号？<br> 1、 新注册用户。 <br>2、 余额 < 0 的用户。 <br> PS: 如果您余额 < 0 ，请激活账号后，充值使余额 > 0 ，然后购买任意商品即可恢复账号！ (参考 网站 - 帮助中心 - 邀请返利 提现说明 )</span>
            </div>
            <div class="form-group">
                <input class="form-control placeholder-no-fix" type="text" autocomplete="off" placeholder="{{trans('active.username_placeholder')}}" name="username" value="{{Request::get('username')}}" required />
                <input type="hidden" name="_token" value="{{csrf_token()}}" />
            </div>
        @else
            <div class="alert alert-danger">
                <span> {{trans('active.tips')}} </span>
            </div>
        @endif
        <div class="form-actions">
            <button type="button" class="btn btn-default" onclick="login()">{{trans('active.back')}}</button>
            @if(\App\Components\Helpers::systemConfig()['is_active_register'])
                <button type="submit" class="btn red uppercase pull-right">{{trans('active.submit')}}</button>
            @endif
        </div>
    </form>
    <!-- END FORGOT PASSWORD FORM -->
@endsection
@section('script')
    <script type="text/javascript">
        // 登录
        function login() {
            window.location.href = '{{url('login')}}';
        }
    </script>
@endsection