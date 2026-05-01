@extends('admin.layouts')

@section('css')
    <style>
        .command-card {
            margin-bottom: 20px;
            border: 1px solid #e7ecf1;
            padding: 20px;
            background: #fff;
            border-radius: 4px !important;
        }
        .command-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .command-desc {
            color: #666;
            margin-bottom: 15px;
            height: 40px;
            overflow: hidden;
        }
        .command-meta {
            font-size: 13px;
            color: #999;
            margin-bottom: 15px;
        }
        .command-output {
            background: #2b2b2b;
            color: #a9b7c6;
            padding: 10px;
            border-radius: 4px;
            font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 15px;
            display: none;
        }
    </style>
@endsection

@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <nav aria-label="breadcrumb" style="margin-left: 2px;">
            <ol class="breadcrumb" style="background: transparent; padding: 15px 0 5px 0; margin-bottom: 0; list-style: none;">
                <li class="breadcrumb-item" style="display: inline-block;">
                    <a href="/admin">首页</a>
                    <span style="margin: 0 5px; color: #ccc;">/</span>
                </li>
                <li class="breadcrumb-item active" aria-current="page" style="display: inline-block; color: #999;">系统任务调度</li>
            </ol>
        </nav>
        
        <h1 style="margin: 0 0 20px 2px; font-size: 24px; font-weight: 600; line-height: 1.1;"> 
            系统任务调度
            <small class="text-muted" style="font-size: 14px; color: #777; margin-left: 5px;">安全触发后端 Artisan 命令</small>
        </h1>
        
        <div class="row">
            @foreach($commands as $command)
                <div class="col-lg-4 col-md-6">
                    <div class="command-card">
                        <div class="command-title">{{ $command['title'] }}</div>
                        <div class="command-desc">{{ $command['description'] }}</div>
                        <div class="command-meta">
                            上次执行: <span id="last-run-{{ $command['id'] }}">{{ $command['last_run'] ?: '从未执行' }}</span>
                        </div>
                        <button class="btn btn-primary btn-block btn-run" 
                                data-id="{{ $command['id'] }}" 
                                id="btn-{{ $command['id'] }}">
                            <i class="fa fa-play"></i> 立即执行
                        </button>
                        <div class="command-output" id="output-{{ $command['id'] }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <!-- END CONTENT BODY -->
@endsection

@section('script')
    <script src="/assets/global/plugins/bootbox/bootbox.min.js" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            $('.btn-run').click(function() {
                var btn = $(this);
                var id = btn.data('id');
                var outputDiv = $('#output-' + id);
                var lastRunSpan = $('#last-run-' + id);

                bootbox.confirm({
                    message: "确定要执行该命令吗？部分任务耗时较长，请勿刷新页面。",
                    buttons: {
                        confirm: {
                            label: '确定',
                            className: 'btn-success'
                        },
                        cancel: {
                            label: '取消',
                            className: 'btn-danger'
                        }
                    },
                    callback: function (result) {
                        if (result) {
                            // Start Execution
                            btn.attr('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 正在执行...');
                            outputDiv.hide().empty();

                            $.ajax({
                                url: '/admin/artisan/run',
                                type: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    id: id
                                },
                                timeout: 600000, // 10 minutes timeout
                                success: function(res) {
                                    if (res.status === 'success') {
                                        bootbox.alert({
                                            message: "执行成功！",
                                            size: 'small'
                                        });
                                        lastRunSpan.text(res.last_run);
                                        if (res.output) {
                                            outputDiv.text(res.output).fadeIn();
                                        }
                                    } else {
                                        bootbox.alert("发生错误: " + res.message);
                                    }
                                },
                                error: function(xhr) {
                                    var msg = "请求失败";
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        msg += ": " + xhr.responseJSON.message;
                                    }
                                    bootbox.alert(msg);
                                },
                                complete: function() {
                                    btn.attr('disabled', false).html('<i class="fa fa-play"></i> 立即执行');
                                }
                            });
                        }
                    }
                });
            });
        });
    </script>
@endsection
