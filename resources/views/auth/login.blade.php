@extends('auth.layouts')
@section('title', trans('login.title'))
@section('css')
    <link href="/assets/pages/css/login-2.min.css" rel="stylesheet" type="text/css" />
    <style>
        @media screen and (max-height: 575px){  
            .g-recaptcha {
                -webkit-transform:scale(0.81);
                transform:scale(0.81);
                -webkit-transform-origin:0 0; 
                transform-origin:0 0;
            }
        }  
        .geetest_holder.geetest_wind {
            min-width: 245px !important;
        }
    </style>
@endsection
@section('content')
    <!-- BEGIN LOGIN FORM -->
    <form class="login-form" action="{{url('login')}}" id="login-form" method="post">
        @if($errors->any())
            <div class="alert alert-danger">
                <span> {!! $errors->first() !!} </span>
            </div>
        @endif
        @if (Session::get('regSuccessMsg'))
            <div class="alert alert-success">
                <button class="close" data-close="alert"></button>
                <span> {{Session::get('regSuccessMsg')}} </span>
            </div>
        @endif
        <!-- PoW hidden fields -->
        <input type="hidden" id="_pow_nonce" name="_pow_nonce" value="" />
        <input type="hidden" id="_pow_ts" name="_pow_ts" value="" />
        <input type="hidden" id="_pow_salt" name="_pow_salt" value="" />
        <input type="hidden" id="_pow_diff" name="_pow_diff" value="" />
        <input type="hidden" id="_pow_sig" name="_pow_sig" value="" />
        <div class="form-group">
            <label class="control-label visible-ie8 visible-ie9">{{trans('login.username')}}</label>
            <input class="form-control form-control-solid placeholder-no-fix" type="text" autocomplete="off" placeholder="{{trans('login.username')}}" name="username" value="{{Request::old('username')}}" required />
        </div>
        <div class="form-group">
            <label class="control-label visible-ie8 visible-ie9">{{trans('login.password')}}</label>
            <input class="form-control form-control-solid placeholder-no-fix" type="password" autocomplete="off" placeholder="{{trans('login.password')}}" name="password" value="{{Request::old('password')}}" required />
            <input type="hidden" name="_token" value="{{csrf_token()}}" />
        </div>
        @switch(\App\Components\Helpers::systemConfig()['is_captcha'])
            @case(2)
                <!-- Geetest -->
                <div class="form-group">
                    {!! Geetest::render() !!}
                </div>
                @break
            @case(3)
                <!-- Google noCAPTCHA -->
                <div class="form-group">
                    {!! NoCaptcha::display() !!}
                    {!! NoCaptcha::renderJs(session::get('locale')) !!}
                </div>
                @break
            @case(1)
                <!-- Default Captcha -->
                <div class="form-group" style="margin-bottom:65px;">
                    <label class="control-label visible-ie8 visible-ie9">{{trans('login.captcha')}}</label>
                    <input class="form-control form-control-solid placeholder-no-fix" style="width:60%;float:left;" type="text" autocomplete="off" placeholder="{{trans('login.captcha')}}" name="captcha" value="" />
                    <img src="{{captcha_src()}}" onclick="this.src='/captcha/default?'+Math.random()" alt="{{trans('login.captcha')}}" style="float:right;" />
                </div>
                @break
            @default
        @endswitch
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
<!-- <script>
alert("家: ssvss.xyz 保存书签呦 \nQQ：2107254004 \nQQ群： \nTG群：dossrxyz");  
</script>  -->
@endsection
@section('script')
    <script src="/js/pow.js" type="text/javascript"></script>
    <script type="text/javascript">
        // PoW 初始化 — 页面加载后立刻后台计算
        PoW.init();

        var _powSubmitted = false;
        $('#login-form').submit(function(event){
            if (_powSubmitted) return true; // 已经注入 PoW, 允许提交

            // 先检查Google reCAPTCHA有没有进行验证
            if ( $('#g-recaptcha-response').val() === '' ) {
                Msg(false, "{{trans('login.required_captcha')}}", 'error');
                return false;
            }

            // PoW: 注入 nonce 并提交
            event.preventDefault();
            var $btn = $(this).find('button[type=submit]');
            var origText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 安全验证中...');

            var ready = PoW.consume(function () {
                _powSubmitted = true;
                $btn.prop('disabled', false).html(origText);
                $('#login-form').submit();
            });

            if (ready === false && PoW.isReady() === false) {
                // 还在计算中, 等回调
            }
            return false;
        })

        // 生成提示
        function Msg(clear, msg, type) {
            if ( !clear ) $('.login-form .alert').remove();
            
            var typeClass = 'alert-danger',
                clear = clear ? clear : false,
                $elem = $('.login-form');
            type === 'error' ? typeClass = 'alert-danger' : typeClass = 'alert-success';

            var tpl = '<div class="alert ' + typeClass + '">' +
                    '<button type="button" class="close" onclick="$(this).parent().remove();"></button>' +
                    '<span> ' + msg + ' </span></div>';
            
            if ( !clear ) {
                $elem.prepend(tpl);
            } else {
                $('.login-form .alert').remove();
            }
        }
    </script>
@endsection