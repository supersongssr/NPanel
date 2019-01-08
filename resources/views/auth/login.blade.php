@extends('auth.layouts')
@section('title', trans('login.title'))
@section('css')
    <link href="/assets/pages/css/login-2.min.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <!-- BEGIN LOGIN FORM -->
    <form class="login-form" action="{{url('login')}}" method="post">
        @if (Session::get('errorMsg'))
            <div class="alert alert-danger">
                <button class="close" data-close="alert"></button>
                <span> {!! Session::get('errorMsg') !!} </span>
            </div>
        @endif
        @if (Session::get('regSuccessMsg'))
            <div class="alert alert-success">
                <button class="close" data-close="alert"></button>
                <span> {{Session::get('regSuccessMsg')}} </span>
            </div>
        @endif
        <div class="form-group">
            <label class="control-label visible-ie8 visible-ie9">{{trans('login.username')}}</label>
            <input class="form-control form-control-solid placeholder-no-fix" type="text" autocomplete="off" placeholder="{{trans('login.username')}}" name="username" value="{{Request::old('username')}}" />
        </div>
        <div class="form-group">
            <label class="control-label visible-ie8 visible-ie9">{{trans('login.password')}}</label>
            <input class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="{{trans('login.password')}}" name="password" value="{{Request::old('password')}}" />
            <input type="hidden" name="_token" value="{{csrf_token()}}" />
        </div>
        @if(\App\Components\Helpers::systemConfig()['is_captcha'])
            <div class="form-group" style="margin-bottom:65px;">
                <label class="control-label visible-ie8 visible-ie9">{{trans('login.captcha')}}</label>
                <input class="form-control form-control-solid placeholder-no-fix" style="width:60%;float:left;" type="text" autocomplete="off" placeholder="{{trans('login.captcha')}}" name="captcha" value="" />
                <img src="{{captcha_src()}}" onclick="this.src='/captcha/default?'+Math.random()" alt="{{trans('login.captcha')}}" style="float:right;" />
            </div>
        @endif
        <div class="form-actions">
            <div class="pull-left">
                <label class="rememberme mt-checkbox mt-checkbox-outline">
                    <input type="checkbox" name="remember" value="1"> {{trans('login.remember')}}
                    <span></span>
                </label>
            </div>
            <div class="pull-right forget-password-block">
                <a href="{{url('resetPassword')}}" class="forget-password">{{trans('login.forget_password')}}</a>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn red btn-block uppercase">{{trans('login.login')}}</button>
        </div>
        @if(\App\Components\Helpers::systemConfig()['is_register'])
            <div class="create-account">
                <p>
                    <a href="{{url('register')}}" class="btn-primary btn">{{trans('login.register')}}</a>
                </p>
            </div>
        @endif
    </form>
    <!-- END LOGIN FORM -->
<!-- song -->
<script>
alert("公益站点官网：www.ssrpanel.fun 保存书签呦 \n 支持V2Ray、SS、SSR \n 香港、韩国、日本、新加坡、台北节点 ; 支持Netflix! ; 邀请注册赠送64G流量每人 ; 50%免费节点 , 免费1-3M带宽, 永久免费续期 ; 公益不易 , 请勿浪费本站资源！\n QQ群：913585520 \n TG群：myssrclub \n 加群防失联^3^");  
</script>  
@endsection