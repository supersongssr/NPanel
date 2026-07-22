@extends('user.layouts')
@section('title', '账号激活')
@section('css')
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="portlet light bordered">
                    <div class="portlet-body" style="text-align:center; padding:40px;">
                        <h3 class="font-red bold">您的账号尚未激活</h3>
                        <p style="margin:20px 0; line-height:1.8;">
                            检测到当前账号状态为未激活，部分功能可能受限。<br>
                            请点击下方按钮立即激活账号，激活后即可正常使用。
                        </p>
                        <button class="btn green btn-lg" onclick="doActivate()">立即激活账号</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END PAGE BASE CONTENT -->
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script type="text/javascript">
        // 一键激活当前登录账号
        function doActivate() {
            $.post('/activateSelf', {
                '_token': '{{csrf_token()}}'
            }, function (ret) {
                if (ret.status == 'success') {
                    layer.msg(ret.message, {icon: 1, time: 1500}, function () {
                        location.href = '/'; // 激活成功回首页
                    });
                } else {
                    layer.msg(ret.message, {icon: 2, time: 3000});
                }
            }, 'json').fail(function () {
                layer.msg('请求失败，请重试', {icon: 2, time: 3000});
            });
        }
    </script>
@endsection
