@extends('admin.layouts')
@section('css')
    <link href="/assets/global/plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css" />
    <link href="/assets/global/plugins/select2/css/select2-bootstrap.min.css" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <!-- BEGIN CONTENT BODY -->
    <div class="page-content" style="padding-top:0;">
        <div class="row">
            <div class="col-md-12">
                <!-- BEGIN PAGE BASE CONTENT -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="note note-info">
                        traffic_used={{round($node->traffic_used / 1024/1024/1024)}}G ; 
                        traffic_left={{round($node->traffic_left /1024/1024/1024)}}G;
                        traffic_used_daily={{round($node->traffic_used_daily/1024/1024/1024)}}G;
                        traffic_left_daily={{round($node->traffic_left_daily / 1024/1024/1024)}}G;           
                        </div>
                    </div>
                </div>
                <div class="portlet light bordered">
                    <div class="portlet-body form">
                        <!-- BEGIN FORM-->
                        <form action="/admin/editNode" method="post" class="form-horizontal" onsubmit="return do_submit();">
                            <div class="form-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="portlet light bordered">
                                            <div class="portlet-title">
                                                <div class="caption">
                                                    <span class="caption-subject font-dark bold uppercase">基础信息</span>
                                                </div>
                                            </div>
                                            <div class="portlet-body">
                                                
                                                
                                                <div class="form-group">
                                                    <label for="status" class="col-md-3 control-label">status</label>
                                                    <div class="col-md-8">
                                                        <div class="mt-radio-inline">
                                                            <label class="mt-radio">
                                                                <input type="radio" name="status" value="1" {{$node->status == '1' ? 'checked' : ''}}> 正常
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="status" value="0" {{$node->status == '0' ? 'checked' : ''}}> 维护
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="is_subscribe" class="col-md-3 control-label">is_subscribe</label>
                                                    <div class="col-md-8">
                                                        <div class="mt-radio-inline">
                                                            <label class="mt-radio">
                                                                <input type="radio" name="is_subscribe" value="1" {{$node->is_subscribe ? 'checked' : ''}}> 允许订阅
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="is_subscribe" value="0" {{!$node->is_subscribe ? 'checked' : ''}}> 不允许订阅
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="name" class="col-md-3 control-label"> name </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="name" value="{{$node->name}}" id="name" placeholder="节点名称" autofocus required>
                                                        <input type="hidden" name="id" value="{{$node->id}}">
                                                        <input type="hidden" name="_token" value="{{csrf_token()}}">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="server" class="col-md-3 control-label"> server </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="server" value="{{$node->server}}" id="server" placeholder="域名或ip">
                                                        <span class="help-block">服务器地址或CDNIP</span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="ip" class="col-md-3 control-label"> ip  </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="ip" value="{{$node->ip}}" id="ip" placeholder="服务器IPv4地址" >
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="sort" class="col-md-3 control-label">sort</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="sort" value="{{$node->sort}}" id="sort" placeholder="维护值">
                                                        <span class="help-block"> sort维护值 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="ipv6" class="col-md-3 control-label"> ipv6  </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="ipv6" value="{{$node->ipv6}}" id="ipv6" placeholder="服务器IPv6地址 ">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="traffic_rate" class="col-md-3 control-label"> traffic_rate  </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="traffic_rate" value="{{$node->traffic_rate}}" id="traffic_rate" placeholder="流量比例"  >
                                                        <span class="help-block"> 举例：0.1用100M结算10M，5用100M结算500M </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="country_code" class="col-md-3 control-label"> country_code </label>
                                                    <div class="col-md-8">
                                                        <select class="form-control" name="country_code" id="country_code">
                                                            <option value="">请选择</option>
                                                            @if(!$country_list->isEmpty())
                                                                @foreach($country_list as $country)
                                                                    <option value="{{$country->country_code}}" {{$node->country_code == $country->country_code ? 'selected' : ''}}>{{$country->country_code}} - {{$country->country_name}}</option>
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                        <span class="help-block">国家/地区代码</span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="node_cost" class="col-md-3 control-label"> node_cost </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="node_cost" value="{{$node->node_cost}}" id="node_cost" placeholder="$" required>
                                                        <span class="help-block"> 服务器成本，美金 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="level" class="col-md-3 control-label">level</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="level" value="{{$node->level}}" id="level" placeholder="节点等级">
                                                        <span class="help-block"> 节点level等级 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="node_group" class="col-md-3 control-label">node_group</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="node_group" value="{{$node->node_group}}" id="node_group" placeholder="节点分组">
                                                        <span class="help-block"> 节点分组，0分组代表通知分组 </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="group_id" class="col-md-3 control-label"> group_id </label>
                                                    <div class="col-md-8">
                                                        <select class="form-control" name="group_id" id="group_id">
                                                            <option value="0">请选择</option>
                                                            @if(!$group_list->isEmpty())
                                                                @foreach($group_list as $group)
                                                                    <option value="{{$group->id}}" {{$node->group_id == $group->id ? 'selected' : ''}}>{{$group->name}}</option>
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                        <span class="help-block"> 功能废弃 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="labels" class="col-md-3 control-label">labels</label>
                                                    <div class="col-md-8">
                                                        <select id="labels" class="form-control select2-multiple" name="labels[]" multiple>
                                                            @foreach($label_list as $label)
                                                                <option value="{{$label->id}}" @if(in_array($label->id, $node->labels)) selected @endif>{{$label->name}}</option>
                                                            @endforeach
                                                        </select>
                                                        <span class="help-block"> 节点标签，目前无用 </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="desc" class="col-md-3 control-label"> desc </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="desc" value="{{$node->desc}}" id="desc" placeholder="节点备注">
                                                        <span class="help-block"> 备注 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="desc" class="col-md-3 control-label"> info </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="info" value="{{$node->info}}" id="info" placeholder="节点信息">
                                                        <span class="help-block"> 信息 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="desc" class="col-md-3 control-label"> node_unlock </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="node_unlock" value="{{$node->node_unlock}}" id="node_unlock" placeholder="解锁">
                                                        <span class="help-block"> 信息 </span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="bandwidth" class="col-md-3 control-label">bandwidth </label>
                                                    <div class="col-md-8">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="bandwidth" value="{{$node->bandwidth}}" id="bandwidth" placeholder="带宽"  >
                                                            <span class="input-group-addon">带宽 M</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="traffic_limit" class="col-md-3 control-label">traffic_limit </label>
                                                    <div class="col-md-8">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control right" name="traffic_limit" value="{{floor($node->traffic_limit / 1024/1024/1024)}}" id="traffic_limit" placeholder="月流量限制"  >
                                                            <span class="input-group-addon">月流量 G</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="traffic" class="col-md-3 control-label">traffic </label>
                                                    <div class="col-md-8">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control right" name="traffic" value="{{floor($node->traffic / 1024/1024/1024)}}" id="traffic" placeholder="已用流量"  >
                                                            <span class="input-group-addon">已用流量 G</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="is_nat" class="col-md-3 control-label">NAT</label>
                                                    <div class="col-md-8">
                                                        <div class="mt-radio-inline">
                                                            <label class="mt-radio">
                                                                <input type="radio" name="is_nat" value="1" {{$node->is_nat == '1' ? 'checked' : ''}}> 是
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="is_nat" value="0" {{$node->is_nat == '0' ? 'checked' : ''}}> 否
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="ssh_port" class="col-md-3 control-label"> ssh_port </label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control" name="ssh_port" value="{{$node->ssh_port}}" id="ssh_port" placeholder="SRS端口" >
                                                        <span class="help-block">SSH检测端口，目前无用</span>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="monitor_url" class="col-md-3 control-label">monitor_url</label>
                                                    <div class="col-md-8">
                                                        <input type="text" class="form-control right" name="monitor_url" value="{{$node->monitor_url}}" id="monitor_url" placeholder="节点监控信息">
                                                        <span class="help-block"> 节点监控</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="portlet light bordered">
                                            <div class="portlet-title">
                                                <div class="caption">
                                                    <span class="caption-subject font-dark bold">配置信息</span>
                                                </div>
                                            </div>
                                            <div class="portlet-body">
                                                <div class="form-group">
                                                    <label for="service" class="col-md-3 control-label">type</label>
                                                    <div class="col-md-8">
                                                        <div class="mt-radio-inline">
                                                            <label class="mt-radio">
                                                                <input type="radio" name="service" value="1" @if($node->type == 1) checked @endif> SS
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="service" value="2" @if($node->type == 2) checked @endif> Vmess
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="service" value="3" @if($node->type == 3) checked @endif> Vless
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="service" value="4" @if($node->type == 4) checked @endif> Trojan
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- 通用 设置部分 -->
                                                    
                                                    <div class="form-group">
                                                        <label for="node_uuid" class="col-md-3 control-label">node_uuid</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="node_uuid" value="{{$node->node_uuid}}" id="node_uuid">
                                                            <span class="help-block"> 独立节点UUID,默认留空 </span>
                                                        </div>
                                                    </div>
                                                <!-- SS/SSR 设置部分 -->
                                                <div class="ss-setting {{$node->type == 1 ? '' : 'hidden'}}">
                                                    <div class="form-group">
                                                        <label for="method" class="col-md-3 control-label">method</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="method" id="method">
                                                                @foreach ($method_list as $method)
                                                                    <option value="{{$method->name}}" @if($method->name == $node->method) selected @endif>{{$method->name}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="protocol" class="col-md-3 control-label">protocol</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="protocol" id="protocol">
                                                                @foreach ($protocol_list as $protocol)
                                                                    <option value="{{$protocol->name}}" @if($protocol->name == $node->protocol) selected @endif>{{$protocol->name}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="protocol_param" class="col-md-3 control-label"> 协议参数 </label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="protocol_param" value="{{$node->protocol_param}}" id="protocol_param" placeholder="">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="obfs" class="col-md-3 control-label">混淆</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="obfs" id="obfs">
                                                                @foreach ($obfs_list as $obfs)
                                                                    <option value="{{$obfs->name}}" @if($obfs->name == $node->obfs) selected @endif>{{$obfs->name}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="obfs_param" class="col-md-3 control-label"> 混淆参数 </label>
                                                        <div class="col-md-8">
                                                            <textarea class="form-control" rows="5" name="obfs_param" id="obfs_param">{{$node->obfs_param}}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="compatible" class="col-md-3 control-label">SS+SR？</label>
                                                        <div class="col-md-8">
                                                            <div class="mt-radio-inline">
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="compatible" value="1" {{$node->compatible == '1' ? 'checked' : ''}}> 是
                                                                    <span></span>
                                                                </label>
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="compatible" value="0" {{$node->compatible == '0' ? 'checked' : ''}}> 否
                                                                    <span></span>
                                                                </label>
                                                            </div>
                                                            <span class="help-block"> 如果兼容请在服务端配置协议和混淆时加上<span style="color:red">_compatible</span> </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="is_tcp_check" class="col-md-3 control-label">TCP阻断检测</label>
                                                        <div class="col-md-8">
                                                            <div class="mt-radio-inline">
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="is_tcp_check" value="1" {{$node->is_tcp_check == '1' ? 'checked' : ''}}> 开启
                                                                    <span></span>
                                                                </label>
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="is_tcp_check" value="0" {{$node->is_tcp_check == '0' ? 'checked' : ''}}> 关闭
                                                                    <span></span>
                                                                </label>
                                                            </div>
                                                            <span class="help-block"> 每30~60分钟随机进行TCP阻断检测 </span>
                                                        </div>
                                                    </div>
                                                    <hr />
                                                    <div class="form-group">
                                                        <label for="single" class="col-md-3 control-label">单端口</label>
                                                        <div class="col-md-8">
                                                            <div class="mt-radio-inline">
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="single" value="0" {{!$node->single ? 'checked' : ''}}> 关闭
                                                                    <span></span>
                                                                </label>
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="single" value="1" {{$node->single ? 'checked' : ''}}> 启用
                                                                    <span></span>
                                                                </label>
                                                            </div>
                                                            <span class="help-block"> 如果启用请配置服务端的<span style="color:red"> <a href="javascript:showTnc();">additional_ports</a> </span>信息 </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group single-setting {{!$node->single ? 'hidden' : ''}}">
                                                        <label for="single_force" class="col-md-3 control-label">[单] 模式</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="single_force" id="single_force">
                                                                <option value="0" {{$node->single_force == '0' ? 'selected' : ''}}>兼容模式</option>
                                                                <option value="1" {{$node->single_force == '1' ? 'selected' : ''}}>严格模式</option>
                                                            </select>
                                                            <span class="help-block"> 严格模式：用户的端口无法连接，只能通过以下指定的端口号进行连接（<a href="javascript:showPortsOnlyConfig();">如何配置</a>）</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group single-setting {{!$node->single ? 'hidden' : ''}}">
                                                        <label for="single_port" class="col-md-3 control-label">[单] 端口号</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="single_port" value="{{$node->single_port}}" id="single_port" placeholder="443">
                                                            <span class="help-block"> 推荐80或443，服务端需要配置 </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group single-setting {{!$node->single ? 'hidden' : ''}}">
                                                        <label for="single_passwd" class="col-md-3 control-label">[单] 密码</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="single_passwd" value="{{$node->single_passwd}}" id="single_passwd" placeholder="password">
                                                        </div>
                                                    </div>
                                                    <div class="form-group single-setting {{!$node->single ? 'hidden' : ''}}">
                                                        <label for="single_method" class="col-md-3 control-label">[单] 加密方式</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="single_method" id="single_method">
                                                                @foreach ($method_list as $method)
                                                                    <option value="{{$method->name}}" @if($method->name == $node->single_method) selected @endif>{{$method->name}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group single-setting {{!$node->single ? 'hidden' : ''}}">
                                                        <label for="single_protocol" class="col-md-3 control-label">[单] 协议</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="single_protocol" id="single_protocol">
                                                                <option value="origin" {{$node->single_protocol == 'origin' ? 'selected' : ''}}>origin</option>
                                                                <option value="verify_deflate" {{$node->single_protocol == 'verify_deflate' ? 'selected' : ''}}>verify_deflate</option>
                                                                <option value="auth_sha1_v4" {{$node->single_protocol == 'auth_sha1_v4' ? 'selected' : ''}}>auth_sha1_v4</option>
                                                                <option value="auth_aes128_md5" {{$node->single_protocol == 'auth_aes128_md5' ? 'selected' : ''}}>auth_aes128_md5</option>
                                                                <option value="auth_aes128_sha1" {{$node->single_protocol == 'auth_aes128_sha1' ? 'selected' : ''}}>auth_aes128_sha1</option>
                                                                <option value="auth_chain_a" {{$node->single_protocol == 'auth_chain_a' ? 'selected' : ''}}>auth_chain_a</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group single-setting {{!$node->single ? 'hidden' : ''}}">
                                                        <label for="single_obfs" class="col-md-3 control-label">[单] 混淆</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="single_obfs" id="single_obfs">
                                                                <option value="plain" {{$node->single_obfs == 'plain' ? 'selected' : ''}}>plain</option>
                                                                <option value="http_simple" {{$node->single_obfs == 'http_simple' ? 'selected' : ''}}>http_simple</option>
                                                                <option value="random_head" {{$node->single_obfs == 'random_head' ? 'selected' : ''}}>random_head</option>
                                                                <option value="tls1.2_ticket_auth" {{$node->single_obfs == 'tls1.2_ticket_auth' ? 'selected' : ''}}>tls1.2_ticket_auth</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- V2 vless trojan 设置部分 -->
                                                <div class="v2-setting {{$node->type != 1 ? '' : 'hidden'}}">
                                                    <div class="form-group">
                                                        <label for="v2_alter_id" class="col-md-3 control-label">v2_alter_id</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_alter_id" value="{{$node->v2_alter_id}}" id="v2_alter_id" placeholder="0">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_port" class="col-md-3 control-label">v2_port</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_port" value="{{$node->v2_port}}" id="v2_port" placeholder="443">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_flow" class="col-md-3 control-label">v2_flow</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_flow" value="{{$node->v2_flow}}" id="v2_flow" placeholder="">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="v2_fp" class="col-md-3 control-label">v2_fp</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_fp" value="{{$node->v2_fp}}" id="v2_fp" placeholder="">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_method" class="col-md-3 control-label">v2_method</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="v2_method" id="v2_method">
                                                                <option value="none" @if($node->v2_method == 'none') selected @endif>none</option>
                                                                <option value="auto" @if($node->v2_method == 'auto') selected @endif>auto</option>
                                                                <option value="aes-128-gcm" @if($node->v2_method == 'aes-128-gcm') selected @endif>aes-128-gcm</option>
                                                                <option value="chacha20-poly1305" @if($node->v2_method == 'chacha20-poly1305') selected @endif>chacha20-poly1305</option>
                                                            </select>
                                                            <span class="help-block"> 加密方式 </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_net" class="col-md-3 control-label">v2_net</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="v2_net" id="v2_net">
                                                                <option value="tcp" @if($node->v2_net == 'tcp') selected @endif>TCP</option>
                                                                <option value="kcp" @if($node->v2_net == 'kcp') selected @endif>mKCP（kcp）</option>
                                                                <option value="ws" @if($node->v2_net == 'ws') selected @endif>WebSocket（ws）</option>
                                                                <option value="h2" @if($node->v2_net == 'h2') selected @endif>HTTP/2（h2）</option>
                                                                <option value="quic" @if($node->v2_net == 'quic') selected @endif>Quic</option>
                                                                <option value="grpc" @if($node->v2_net == 'grpc') selected @endif>Grpc</option>
                                                            </select>
                                                            <span class="help-block"> 传输协议  </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_type" class="col-md-3 control-label">v2_type</label>
                                                        <div class="col-md-8">
                                                            <select class="form-control" name="v2_type" id="v2_type">
                                                                <option value="none" @if($node->v2_type == 'none') selected @endif>无伪装</option>
                                                                <option value="http" @if($node->v2_type == 'http') selected @endif>HTTP数据流</option>
                                                                <option value="srtp" @if($node->v2_type == 'srtp') selected @endif>视频通话数据 (SRTP)</option>
                                                                <option value="utp" @if($node->v2_type == 'utp') selected @endif>BT下载数据 (uTP)</option>
                                                                <option value="wechat-video" @if($node->v2_type == 'wechat-video') selected @endif>微信视频通话</option>
                                                                <option value="dtls" @if($node->v2_type == 'dtls') selected @endif>DTLS1.2数据包</option>
                                                                <option value="wireguard" @if($node->v2_type == 'wireguard') selected @endif>WireGuard数据包</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_host" class="col-md-3 control-label">v2_host</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_host" value="{{$node->v2_host}}" id="v2_host">
                                                            <span class="help-block"> host / ws host/ h2 host / QUIC加密 </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_path" class="col-md-3 control-label">v2_path</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_path" value="{{$node->v2_path}}" id="v2_path">
                                                            <span class="help-block"> ws path / h2 path /Quic 加密密钥 / kcp seed / gRPC serviceName</span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_ " class="col-md-3 control-label">v2_tls</label>
                                                        <div class="col-md-8">
                                                            <div class="mt-radio-inline">
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="v2_tls" value="0" @if($node->v2_tls == 0) checked @endif> 否
                                                                    <span></span>
                                                                </label>
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="v2_tls" value="1" @if($node->v2_tls == 1) checked @endif> Tls
                                                                    <span></span>
                                                                </label>
                                                                <label class="mt-radio">
                                                                    <input type="radio" name="v2_tls" value="2" @if($node->v2_tls == 2) checked @endif> XTls
                                                                    <span></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_sni" class="col-md-3 control-label">v2_sni</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_sni" value="{{$node->v2_sni}}" id="v2_sni">
                                                            <span class="help-block"> v2_sni 域名分流 </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_alpn" class="col-md-3 control-label">v2_alpn</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_alpn" value="{{$node->v2_alpn}}" id="v2_alpn">
                                                            <span class="help-block"> v2_alpn </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_encryption" class="col-md-3 control-label">v2_encryption</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_encryption" value="{{$node->v2_encryption}}" id="v2_encryption">
                                                            <span class="help-block"> Vless特有 默认 none </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                    <label for="is_transit" class="col-md-3 control-label">is_transit</label>
                                                    <div class="col-md-8">
                                                        <div class="mt-radio-inline">
                                                            <label class="mt-radio">
                                                                <input type="radio" name="is_transit" value="1" {{$node->is_transit == '1' ? 'checked' : ''}}> 支持CND中转
                                                                <span></span>
                                                            </label>
                                                            <label class="mt-radio">
                                                                <input type="radio" name="is_transit" value="0" {{$node->is_transit == '0' ? 'checked' : ''}}> 否
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_insider_port" class="col-md-3 control-label">v2_insider_port</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_insider_port" value="{{$node->v2_insider_port}}" id="v2_insider_port" placeholder="10550">
                                                            <span class="help-block"> 内部监听 </span>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="v2_outsider_port" class="col-md-3 control-label">v2_outsider_port</label>
                                                        <div class="col-md-8">
                                                            <input type="text" class="form-control" name="v2_outsider_port" value="{{$node->v2_outsider_port}}" id="v2_outsider_port" placeholder="443">
                                                            <span class="help-block"> 外部覆盖 </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn green">提 交</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <!-- END FORM-->
                    </div>
                </div>
                <!-- END PAGE BASE CONTENT -->
            </div>
        </div>
    </div>
    <!-- END CONTENT BODY -->
@endsection
@section('script')
    <script src="/assets/global/plugins/select2/js/select2.full.min.js" type="text/javascript"></script>

    <script type="text/javascript">
        // 用户标签选择器
        $('#labels').select2({
            theme: 'bootstrap',
            placeholder: '设置后则可见相同标签的节点',
            allowClear: true,
            width:'100%'
        });

        // ajax同步提交
        function do_submit() {
            var _token = '{{csrf_token()}}';
            var id = '{{Request::get('id')}}';
            var name = $('#name').val();
            var labels = $("#labels").val();
            var group_id = $("#group_id option:selected").val();
            var country_code = $("#country_code option:selected").val();
            var server = $('#server').val();
            var ip = $('#ip').val();
            var ipv6 = $('#ipv6').val();
            var desc = $('#desc').val();
            var method = $('#method').val();
            var traffic_rate = $('#traffic_rate').val();
            var node_cost = $('#node_cost').val();
            var protocol = $('#protocol').val();
            var protocol_param = $('#protocol_param').val();
            var obfs = $('#obfs').val();
            var obfs_param = $('#obfs_param').val();
            var bandwidth = $('#bandwidth').val();
            var traffic = $('#traffic').val();
            var traffic_limit = $('#traffic_limit').val();
            var monitor_url = $('#monitor_url').val();
            var is_subscribe = $("input:radio[name='is_subscribe']:checked").val();
            var is_nat = $("input:radio[name='is_nat']:checked").val();
            var is_transit = $("input:radio[name='is_transit']:checked").val();
            var ssh_port = $('#ssh_port').val();
            var compatible = $("input:radio[name='compatible']:checked").val();
            var single = $("input:radio[name='single']:checked").val();
            var single_force = $('#single_force').val();
            var single_port = $('#single_port').val();
            var single_passwd = $('#single_passwd').val();
            var single_method = $('#single_method').val();
            var single_protocol = $('#single_protocol').val();
            var single_obfs = $('#single_obfs').val();
            var sort = $('#sort').val();
            var level = $('#level').val();
            var node_group = $('#node_group').val();
            var status = $("input:radio[name='status']:checked").val();
            var is_tcp_check = $("input:radio[name='is_tcp_check']:checked").val();

            var service = $("input:radio[name='service']:checked").val();
            var v2_alter_id = $('#v2_alter_id').val();
            var v2_port = $('#v2_port').val();
            var v2_method = $("#v2_method option:selected").val();
            var v2_net = $('#v2_net').val();
            var v2_type = $('#v2_type').val();
            var v2_host = $('#v2_host').val();
            var v2_path = $('#v2_path').val();
            var v2_tls = $("input:radio[name='v2_tls']:checked").val();
            var v2_insider_port = $('#v2_insider_port').val();
            var v2_outsider_port = $('#v2_outsider_port').val();
            var node_uuid = $('#node_uuid').val();
            var v2_flow = $('#v2_flow').val();
            var v2_sni = $('#v2_sni').val();
            var v2_alpn = $('#v2_alpn').val();
            var v2_encryption = $('#v2_encryption').val();

            $.ajax({
                type: "POST",
                url: "/admin/editNode",
                async: false,
                data: {
                    _token:_token,
                    id: id,
                    name: name,
                    labels: labels,
                    group_id: group_id,
                    country_code: country_code,
                    server: server,
                    ip: ip,
                    ipv6: ipv6,
                    desc: desc,
                    method: method,
                    traffic_rate: traffic_rate,
                    node_cost: node_cost,
                    protocol: protocol,
                    protocol_param: protocol_param,
                    obfs: obfs,
                    obfs_param: obfs_param,
                    bandwidth: bandwidth,
                    traffic: traffic,
                    traffic_limit: traffic_limit,
                    monitor_url: monitor_url,
                    is_subscribe: is_subscribe,
                    is_nat: is_nat,
                    is_transit: is_transit,
                    ssh_port: ssh_port,
                    compatible: compatible,
                    single: single,
                    single_force: single_force,
                    single_port: single_port,
                    single_passwd: single_passwd,
                    single_method: single_method,
                    single_protocol: single_protocol,
                    single_obfs: single_obfs,
                    sort: sort,
                    level: level,
                    node_group: node_group,
                    status: status,
                    is_tcp_check: is_tcp_check,
                    type: service,
                    v2_alter_id: v2_alter_id,
                    v2_port: v2_port,
                    v2_method: v2_method,
                    v2_net: v2_net,
                    v2_type: v2_type,
                    v2_host: v2_host,
                    v2_path: v2_path,
                    v2_tls: v2_tls,
                    v2_insider_port: v2_insider_port,
                    v2_outsider_port: v2_outsider_port,
                    node_uuid: node_uuid,
                    v2_flow: v2_flow,
                    v2_sni: v2_sni,
                    v2_alpn: v2_alpn,
                    v2_encryption: v2_encryption
                },
                dataType: 'json',
                success: function (ret) {
                    layer.msg(ret.message, {time:1000}, function() {
                        if (ret.status == 'success') {
                            //window.location.href = '{{'/admin/nodeList?page=' . Request::get('page')}}';
                            //window.history.back();
                            window.location.replace(document.referrer);
                        }
                    });
                }
            });

            return false;
        }

        // 设置单端口多用户
        $("input:radio[name='single']").on('change', function() {
            var single = parseInt($(this).val());

            if (single) {
                $(".single-setting").removeClass('hidden');
            } else {
                $(".single-setting").removeClass('hidden');
                $(".single-setting").addClass('hidden');
            }
        });

        // 设置服务类型
        $("input:radio[name='service']").on('change', function() {
            var service = parseInt($(this).val());

            if (service === 1) {
                $(".ss-setting").removeClass('hidden');
                $(".v2-setting").addClass('hidden');
            } else {
                $(".ss-setting").addClass('hidden');
                $(".v2-setting").removeClass('hidden');
            }
        });

        // // 设置是否为NAT
        // $("input:radio[name='is_nat']").on('change', function() {
        //     var is_nat = parseInt($(this).val());

        //     if (is_nat === 1) {
        //         $("#ip").val("1.1.1.1").attr("readonly", "readonly");
        //         $("#server").attr("required", "required");
        //     } else {
        //         $("#ip").val("").removeAttr("readonly");
        //         $("#server").removeAttr("required");
        //     }
        // });

        // 服务条款
        function showTnc() {
            var content = '1.请勿直接复制黏贴以下配置，SSR(R)会报错的；'
                + '<br>2.确保服务器时间为CST；'
                + '<br>3.具体请看<a href="https://github.com/ssrpanel/SSRPanel/wiki/%E5%8D%95%E7%AB%AF%E5%8F%A3%E5%A4%9A%E7%94%A8%E6%88%B7%E7%9A%84%E5%9D%91" target="_blank">WIKI</a>；'
                + '<br>'
                + '<br>"additional_ports" : {'
                + '<br>&ensp;&ensp;&ensp;&ensp;"80": {'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"passwd": "password",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"method": "aes-128-ctr",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"protocol": "auth_aes128_md5",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"protocol_param": "#",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"obfs": "tls1.2_ticket_auth",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"obfs_param": ""'
                + '<br>&ensp;&ensp;&ensp;&ensp;},'
                + '<br>&ensp;&ensp;&ensp;&ensp;"443": {'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"passwd": "password",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"method": "aes-128-ctr",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"protocol": "auth_aes128_sha1",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"protocol_param": "#",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"obfs": "tls1.2_ticket_auth",'
                + '<br>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;"obfs_param": ""'
                + '<br>&ensp;&ensp;&ensp;&ensp;}'
                + '<br>},';

            layer.open({
                type: 1
                ,title: '[节点 user-config.json 配置示例]' //不显示标题栏
                ,closeBtn: false
                ,area: '400px;'
                ,shade: 0.8
                ,id: 'tnc' //设定一个id，防止重复弹出
                ,resize: false
                ,btn: ['确定']
                ,btnAlign: 'c'
                ,moveType: 1 //拖拽模式，0或者1
                ,content: '<div style="padding: 20px; line-height: 22px; background-color: #393D49; color: #fff; font-weight: 300;">' + content + '</div>'
                ,success: function(layero){
                    //
                }
            });
        }

        // 模式提示
        function showPortsOnlyConfig() {
            var content = '严格模式：'
                + '<br>'
                + '"additional_ports_only": "true"'
                + '<br><br>'
                + '兼容模式：'
                + '<br>'
                + '"additional_ports_only": "false"';

            layer.open({
                type: 1
                ,title: '[节点 user-config.json 配置示例]'
                ,closeBtn: false
                ,area: '400px;'
                ,shade: 0.8
                ,id: 'po-cfg' //设定一个id，防止重复弹出
                ,resize: false
                ,btn: ['确定']
                ,btnAlign: 'c'
                ,moveType: 1 //拖拽模式，0或者1
                ,content: '<div style="padding: 20px; line-height: 22px; background-color: #393D49; color: #fff; font-weight: 300;">' + content + '</div>'
                ,success: function(layero){
                    //
                }
            });
        }
    </script>
@endsection
