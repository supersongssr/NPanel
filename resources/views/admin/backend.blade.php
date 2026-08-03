@extends('admin.layouts')
@section('css')
    <link href="/assets/global/plugins/datatables/datatables.min.css" rel="stylesheet" type="text/css" />
    <link href="/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <!-- BEGIN PAGE BASE CONTENT -->
        <div class="row">
            <div class="col-md-12">
                <!-- BEGIN PROFILE CONTENT -->
                <div class="profile-content">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="portlet light bordered">
                                <div class="portlet-title tabbable-line">
                                    <ul class="nav nav-tabs">
                                        <li class="active">
                                            <a href="#tab_node" data-toggle="tab"> 节点配置 </a>
                                        </li>
                                        <li>
                                            <a href="#tab_unlock" data-toggle="tab"> 解锁配置 </a>
                                        </li>
                                        <li>
                                            <a href="#tab_fallback" data-toggle="tab"> 回落配置 </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="portlet-body">
                                    <div class="tab-content">
                                        <div class="tab-pane active" id="tab_node">
                                            <div class="portlet-body">
                                                <div class="table-scrollable">
                                                    <table class="table table-hover table-light">
                                                        <thead>
                                                            <tr>
                                                                <th> 根域名 </th>
                                                                <th> Zone ID </th>
                                                                <th> 记录上限 </th>
                                                                <th> CDN </th>
                                                                <th> CF Token </th>
                                                                <th> 到期日期 </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @php
                                                                $domainPoolView = (!empty($node_domain_map) && is_array($node_domain_map)) ? $node_domain_map : (json_decode($node_domain_pool ?? '[]', true) ?: []);
                                                            @endphp
                                                            @forelse ($domainPoolView as $dpDomain => $dpMeta)
                                                                <tr>
                                                                    <td>{{ $dpDomain }}</td>
                                                                    <td>{{ is_array($dpMeta) ? ($dpMeta['zone_id'] ?? '') : '' }}</td>
                                                                    <td>{{ is_array($dpMeta) ? ($dpMeta['records_limit'] ?? 180) : 180 }}</td>
                                                                    <td>{{ (is_array($dpMeta) && !empty($dpMeta['cdn'])) ? '✅ 是' : '—' }}</td>
                                                                    <td>{{ (is_array($dpMeta) && !empty($dpMeta['cf_token'])) ? '🔑 独立Token' : '全局默认' }}</td>
                                                                    <td>{{ (is_array($dpMeta) && !empty($dpMeta['expire_date'])) ? $dpMeta['expire_date'] : '—' }}</td>
                                                                </tr>
                                                            @empty
                                                                <tr><td colspan="6" class="text-center text-muted">暂未配置（请在 <code>.config.php</code> 的 <code>node_domain_map</code> 中添加）</td></tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <span class="help-block">域名池为文件配置，请直接编辑 <code>.config.php</code> 的 <code>node_domain_map</code>（CF Token 不在页面明文展示）。</span>

                                                <hr style="margin: 20px 0; border-top: 1px solid #e5e5e5;">

                                                <div class="form-horizontal" style="margin-top: 10px;">
                                                    <div class="form-group">
                                                        <label class="col-md-2 control-label">DNS 自动清理阈值（天）</label>
                                                        <div class="col-md-4">
                                                            <div class="input-group">
                                                                <input type="number" min="1" step="1" class="form-control input-sm" id="input_dns_expire_days"
                                                                       value="{{ intval($dns_expire_days ?? 32) }}">
                                                                <span class="input-group-btn">
                                                                    <button class="btn btn-success btn-sm" type="button" onclick="saveDnsExpireDays()">保存</button>
                                                                </span>
                                                            </div>
                                                            <span class="help-block">心跳超过该天数无活跃的节点，其 DNS 解析记录将被定时任务（<code>AutoDeleteExpiredDns</code>，每日 04:10）从 Cloudflare 与本地一并删除，释放 DNS 名额。默认 32，填 0 或非法值回退 32。</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="alert alert-info" style="margin-top: 10px;">
                                                    <b>说明：</b><br>
                                                    1. 根域名必须在 Cloudflare 托管并已获取 Zone ID。<br>
                                                    2. 记录上限控制每个域名下可创建的 DNS 记录数量，默认 180。<br>
                                                    3. <b>开启 CDN</b>：勾选后该域名将走 Cloudflare CDN（仅 <code>xhttp-cdn</code> 节点会选用此类域名），用于隔离防封号。<br>
                                                    4. <b>CF Token (独立)</b>：当域名托管在<b>不同的</b> Cloudflare 账号时填写该账号的 API Token (与全局 <code>CLOUDFLARE_TOKEN</code> 隔离); 留空则用 <code>.env</code> 的默认 Token。<b>与是否开启 CDN 无关</b> —— 例如某个普通节点域名托管在别的 CF 账号, 只填 Token 不勾 CDN 即可正常增删 DNS 记录。<br>
                                                    5. 协议默认使用 <code>xhttp-hy2</code>，节点注册时可通过 <code>v2_name</code> 指定。<br>
                                                    6. 修改后对新注册的节点立即生效，已有节点需重新注册。
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="tab_unlock">
                                            <div class="portlet-body">
                                                <div class="table-scrollable">
                                                    <table class="table table-hover table-light">
                                                        <thead>
                                                            <tr>
                                                                <th> 服务 </th>
                                                                <th> 地址 (Address) </th>
                                                                <th> 端口 (Port) </th>
                                                                <th> 密码 (Password) </th>
                                                                <th> 加密方式 (Method) </th>
                                                                <th> 操作 </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach(['netflix' => 'Netflix', 'openai' => 'OpenAI / ChatGPT', 'disney' => 'Disney+', 'tiktok' => 'TikTok', 'bahamut' => '动画疯 (Bahamut)', 'claude' => 'Claude', 'google_scholar' => 'Google Scholar'] as $key => $name)
                                                            <tr>
                                                                <td> <b>{{$name}}</b> </td>
                                                                <td> <input type="text" class="form-control input-sm" id="unlock_{{$key}}_address" value="{{${'unlock_'.$key.'_address'} ?? ''}}"> </td>
                                                                <td> <input type="number" class="form-control input-sm" style="width: 80px;" id="unlock_{{$key}}_port" value="{{${'unlock_'.$key.'_port'} ?? '8388'}}"> </td>
                                                                <td> <input type="text" class="form-control input-sm" id="unlock_{{$key}}_password" value="{{${'unlock_'.$key.'_password'} ?? ''}}"> </td>
                                                                <td>
                                                                    <select class="form-control input-sm" id="unlock_{{$key}}_method">
                                                                        @foreach(['chacha20-ietf-poly1305', 'aes-128-gcm', 'aes-256-gcm', 'rc4-md5'] as $m)
                                                                            <option value="{{$m}}" @if((${'unlock_'.$key.'_method'} ?? '') == $m) selected @endif>{{$m}}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-success" type="button" onclick="saveUnlockConfig('{{$key}}')">保存</button>
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="alert alert-info">
                                                    <b>说明：</b><br>
                                                    1. 只有在节点配置的 <code>node_unlock</code> 包含对应服务（如 <code>netflix=1</code>）时，才会下发对应的解锁配置。<br>
                                                    2. 地址通常为解锁机 IP 或中转域名。加密方式推荐使用 <code>chacha20-ietf-poly1305</code>。<br>
                                                    3. 修改后对所有使用该解锁服务的节点立即生效（节点拉取新配置后）。
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane" id="tab_fallback">
                                            <div class="portlet-body">
                                                <div class="form-horizontal">
                                                    <div class="form-group">
                                                        <label class="col-md-2 control-label">回落基础域名</label>
                                                        <div class="col-md-5">
                                                            <div class="input-group">
                                                                <input type="text" class="form-control input-sm" id="input_node_fallback_host"
                                                                       value="{{ $node_fallback_host ?? 'npanel-nav.freessr.bid' }}">
                                                                <span class="input-group-btn">
                                                                    <button class="btn btn-success btn-sm" type="button" onclick="saveFallbackHost()">保存</button>
                                                                </span>
                                                            </div>
                                                            <span class="help-block">回落伪装站的基础域名，不含协议前缀。保存后自动推导以下三个回落地址：</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="col-md-offset-2 col-md-8">
                                                            <table class="table table-condensed" style="margin-top: -5px;">
                                                                <thead><tr><th width="180">占位符</th><th width="220">推导规则</th><th>预览值</th></tr></thead>
                                                                <tbody>
                                                                    <tr><td><code>__httpProxyHost__</code></td><td>{域名}</td><td id="preview_http"></td></tr>
                                                                    <tr><td><code>__v2Fallback__</code></td><td>remote-{域名}</td><td id="preview_v2"></td></tr>
                                                                    <tr><td><code>__HYSTERIA_URL__</code></td><td>http://{域名}:80</td><td id="preview_hy2"></td></tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="alert alert-info" style="margin-top: 10px;">
                                                    <b>说明：</b><br>
                                                    1. <b>Nginx 伪装站</b>：nginx 不匹配代理路径时的回落目标（xhttp 模板专用）。<br>
                                                    2. <b>Vision Fallback</b>：Xray Vision 入站的 fallback 目标，自动添加 <code>remote-</code> 前缀。<br>
                                                    3. <b>Hysteria2 伪装 URL</b>：HY2 被主动探测时的代理伪装地址，自动添加 <code>http://</code> 和 <code>:80</code>。<br>
                                                    4. 修改后节点下次拉取配置时生效。
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
@endsection
@section('script')
    <script type="text/javascript">
        // Tab 自动定位与持久化
        $(function() {
            var hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            }

            $('.nav-tabs a').on('shown.bs.tab', function (e) {
                var currentHash = e.target.hash;
                if (history.replaceState) {
                    history.replaceState(null, null, currentHash);
                } else {
                    window.location.hash = currentHash;
                }
            });
        });

        // --- DNS Expire Days ---
        function saveDnsExpireDays() {
            var days = parseInt($.trim($('#input_dns_expire_days').val()), 10);
            if (isNaN(days) || days < 1) {
                layer.msg('请填写大于等于 1 的整数天数', {time: 2000});
                return;
            }
            $.post("/admin/setConfig", {
                _token: '{{csrf_token()}}',
                name: 'dns_expire_days',
                value: String(days)
            }, function (ret) {
                layer.msg(ret.message, {time: 1000}, function () {
                    if (ret.status == 'success') {
                        window.location.reload();
                    }
                });
            });
        }

        // --- Unlock Config ---
        function saveUnlockConfig(service) {
            var address = $("#unlock_" + service + "_address").val();
            var port = $("#unlock_" + service + "_port").val();
            var password = $("#unlock_" + service + "_password").val();
            var method = $("#unlock_" + service + "_method").val();

            var configs = [
                {name: "unlock_" + service + "_address", value: address},
                {name: "unlock_" + service + "_port", value: port},
                {name: "unlock_" + service + "_password", value: password},
                {name: "unlock_" + service + "_method", value: method}
            ];

            var total = configs.length;
            var count = 0;
            var success = true;

            configs.forEach(function(cfg) {
                $.post("/admin/setConfig", {
                    _token: '{{csrf_token()}}',
                    name: cfg.name,
                    value: cfg.value
                }, function (ret) {
                    count++;
                    if (ret.status == 'fail') {
                        success = false;
                        layer.msg(ret.message);
                    }
                    if (count == total && success) {
                        layer.msg('保存成功', {time: 1000});
                    }
                });
            });
        }

        // --- Fallback Config ---
        $(function() {
            var $input = $("#input_node_fallback_host");
            function updatePreview() {
                var host = $input.val().trim() || 'npanel-nav.freessr.bid';
                $("#preview_http").text(host);
                $("#preview_v2").text("remote-" + host);
                $("#preview_hy2").text("http://" + host + ":80");
            }
            $input.on("input", updatePreview);
            updatePreview();
        });

        function saveFallbackHost() {
            var host = $("#input_node_fallback_host").val().trim();
            if (!host) {
                layer.msg('域名不能为空', {time: 2000});
                return;
            }
            $.post("/admin/setConfig", {
                _token: '{{csrf_token()}}',
                name: 'node_fallback_host',
                value: host
            }, function (ret) {
                layer.msg(ret.message, {time: 1000});
            });
        }

    </script>
@endsection
