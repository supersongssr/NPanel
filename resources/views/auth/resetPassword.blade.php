@extends('auth.layouts')
@section('title', trans('home.reset_password_title'))
@section('css')
    <link href="/assets/pages/css/login-2.min.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    @if (Session::get('successMsg'))
        <div class="alert alert-success">
            <span> {{Session::get('successMsg')}} </span>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <span> {{$errors->first()}} </span>
        </div>
    @endif
    <form class="forget-form" action="{{url('resetPassword')}}" method="post" style="display: block;">
        <!-- PoW hidden fields -->
        <input type="hidden" id="_pow_nonce" name="_pow_nonce" value="" />
        <input type="hidden" id="_pow_ts" name="_pow_ts" value="" />
        <input type="hidden" id="_pow_salt" name="_pow_salt" value="" />
        <input type="hidden" id="_pow_diff" name="_pow_diff" value="" />
        <input type="hidden" id="_pow_sig" name="_pow_sig" value="" />
        @if(\App\Components\Helpers::systemConfig()['is_reset_password'])
            <div class="form-title">
                <span class="form-title">{{trans('home.reset_password_title')}}</span>
            </div>
            <div class="form-group">
                <input class="form-control placeholder-no-fix" type="text" autocomplete="off" placeholder="{{trans('home.username_placeholder')}}" name="username" value="{{Request::old('username')}}" required autofocus />
                <input type="hidden" name="_token" value="{{csrf_token()}}" />
            </div>
        @else
            <div class="alert alert-danger">
                <span> {{trans('home.system_down')}} </span>
            </div>
        @endif
        <div class="form-actions">
            <button type="button" class="btn btn-default" onclick="login()">{{trans('register.back')}}</button>
            @if(\App\Components\Helpers::systemConfig()['is_reset_password'])
                <button type="submit" class="btn red uppercase pull-right">{{trans('register.submit')}}</button>
            @endif
        </div>
    </form>
@endsection
@section('script')
    <script src="/js/pow.js" type="text/javascript"></script>
    <script type="text/javascript">
        // PoW 初始化
        PoW.init();

        // 登录
        function login() {
            window.location.href = '{{url('login')}}';
        }

        var _powSubmitted = false;
        $('.forget-form').submit(function(event){
            if (_powSubmitted) return true;

            event.preventDefault();
            var $btn = $(this).find('button[type=submit]');
            var origText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 安全验证中...');

            PoW.consume(function () {
                _powSubmitted = true;
                $btn.prop('disabled', false).html(origText);
                $('.forget-form').submit();
            });

            return false;
        });
    </script>
@endsection