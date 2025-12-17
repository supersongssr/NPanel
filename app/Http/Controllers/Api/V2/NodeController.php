<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Log;
use App\Http\Models\SsNode;

/**
 * API v2 节点管理控制器
 *
 * Class NodeController
 * @package App\Http\Controllers\Api\V2
 */
class NodeController extends Controller
{
    /**
     * 获取可用的节点ID
     * 返回超过30天没有心跳或数据上报或活动的节点的id
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableNodeId(Request $request)
    {
        // 验证token
        if ($request->get('token') != env('API_TOKEN')) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Invalid token'
            ], 401);
        }

        try {
            // 查找超过30天没有心跳的节点
            $node = SsNode::query()
                ->where('id', '>', 99)  // 排除系统节点
                ->where('heartbeat_at', '<', date('Y-m-d H:i:s', time() - 2592000))  // 30天 = 30*24*3600 = 2592000秒
                ->where('status', 0)  // 只找正常状态的节点
                ->orderBy('id', 'asc')
                ->first();

            if (!$node) {
                return response()->json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'No available node found'
                ], 404);
            }

            // 更新节点心跳时间
            $node->heartbeat_at = date('Y-m-d H:i:s');
            $node->save();

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'node_id' => $node->id
                ],
                'message' => 'Available node ID retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Get available node ID failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * 节点配置信息上报
     * 更新节点的配置信息
     *
     * @param Request $request
     * @param int $id 节点ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportConfig(Request $request, $id)
    {
        // 验证token
        if ($request->get('token') != env('API_TOKEN')) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Invalid token'
            ], 401);
        }

        try {
            // 查找或创建节点
            $node = SsNode::query()->where('id', $id)->first();
            if (!$node) {
                $node = new SsNode();
                $node->id = $id;
            }

            // 更新节点基本信息
            $request->get('node_name') && $node->name = $request->get('node_name');
            $request->get('node_country_code') && $node->country_code = strtolower($request->get('node_country_code'));
            if ($request->get('node_country_code') && $request->get('node_name')) {
                $node->name = $node->isotoemoji($request->get('node_country_code')) . $node->name;
            }
            $request->get('node_info') && $node->info = $request->get('node_info');
            $request->get('node_from') && $node->desc = ',from:' . $request->get('node_from');
            $request->get('node_expire') && $node->desc .= ',expire:' . $request->get('node_expire');
            $request->get('node_cost') != '' && $node->node_cost = $request->get('node_cost');
            $request->get('node_level') && $node->level = $request->get('node_level');
            $request->get('node_group') && $node->node_group = $request->get('node_group');
            $request->get('node_traffic_limit') && $node->traffic_limit = $request->get('node_traffic_limit') * 1024 * 1024 * 1024;
            $request->get('node_sort') != '' && $node->sort = $request->get('node_sort');
            $request->get('node_traffic_rate') && $node->traffic_rate = $request->get('node_traffic_rate');
            $request->get('node_bandwidth') && $node->bandwidth = $request->get('node_bandwidth');

            // 更新IP信息
            if ($request->get('node_ip') || $request->get('node_ipv6')) {
                $node->ip = $request->get('node_ip');
                $node->ipv6 = $request->get('node_ipv6');
            }

            // 更新节点解锁信息
            if ($request->get('node_unlock')) {
                $_a = str_replace(',', '&', $request->get('node_unlock'));
                $_a = str_replace('=', '=', $_a);
                $node->node_unlock = $_a;
            }

            // 更新V2协议配置
            if ($request->get('v2')) {
                $request->get('v2') == 'ss' && $node->type = 1;
                $request->get('v2') == 'vmess' && $node->type = 2;
                $request->get('v2') == 'vless' && $node->type = 3;
                $request->get('v2') == 'trojan' && $node->type = 4;
                
                $node->server = $request->get('v2_add');
                $node->v2_port = $request->get('v2_port');
                $node->v2_alter_id = $request->get('v2_aid');
                $node->v2_method = $request->get('v2_scy');
                $node->v2_net = $request->get('v2_net');
                $node->v2_type = $request->get('v2_type');
                $node->v2_host = $request->get('v2_host');
                $node->v2_path = $request->get('v2_path');
                
                // TLS配置
                empty($request->get('v2_tls')) && $node->v2_tls = 0;
                $request->get('v2_tls') == 'tls' && $node->v2_tls = 1;
                $request->get('v2_tls') == 'xtls' && $node->v2_tls = 2;
                
                $node->v2_sni = $request->get('v2_sni');
                $node->v2_alpn = $request->get('v2_alpn');
                $node->v2_encryption = $request->get('v2_ecpt') ?: 'none';
                $node->v2_flow = $request->get('v2_flow');
                $node->node_uuid = $request->get('v2_uuid');
                $request->get('v2_cdn') != '' ? $node->v2_cdn = $request->get('v2_cdn') : $node->v2_cdn = '';
                $request->get('v2_cdn_ip') && $node->v2_cdn_ip = $request->get('v2_cdn_ip');
                $node->v2_mode = $request->get('v2_mode');
                $node->v2_servicename = $request->get('v2_servicename');
                $node->v2_fp = $request->get('v2_fp');

                // 克隆节点配置
                if ($request->get('v2_id')) {
                    $node->is_clone = $request->get('v2_id') != $id ? $request->get('v2_id') : 0;
                }
            }

            // 更新心跳时间
            $node->heartbeat_at = date('Y-m-d H:i:s');

            if (!$node->save()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 500,
                    'message' => 'Failed to save node configuration'
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'node_id' => $node->id
                ],
                'message' => 'Node configuration updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Report node config failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * 节点状态信息上报
     * 接收节点的流量变动信息、status变动、订阅变动等（每小时或实时上报）
     *
     * @param Request $request
     * @param int $id 节点ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportStatus(Request $request, $id)
    {
        // 验证token
        if ($request->get('token') != env('API_TOKEN')) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Invalid token'
            ], 401);
        }

        try {
            // 查找节点
            $node = SsNode::query()->where('id', $id)->first();
            if (!$node) {
                return response()->json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Node not found'
                ], 404);
            }

            // 获取客户端IP
            $ip = getClientIp();

            // 更新节点心跳时间
            $node->heartbeat_at = date('Y-m-d H:i:s');

            // 审核上报的IP，是否和记录的一致
            if ($node->ip != $ip && $node->ipv6 != $ip) {
                $node->desc .= '_' . $ip;
                $node->sort -= 100;
            }

            // 更新节点状态
            $request->get('status') == 0 && $node->status = 0;
            $request->get('status') == 1 && $node->status = 1;
            $request->get('health') == 0 && $node->is_subscribe = 0;
            $request->get('health') == 1 && $node->is_subscribe = 1;

            // 更新流量和负载信息
            $node->node_online = $request->get('online');
            $node->traffic = $request->get('traffic');
            $node->traffic_used = $request->get('traffic_used');
            $node->traffic_used_daily = $request->get('traffic_used_daily');
            $node->traffic_left = $request->get('traffic_left');
            $node->traffic_left_daily = $request->get('traffic_left_daily');
            $node->node_onload = $request->get('daily');

            if (!$node->save()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 500,
                    'message' => 'Failed to update node'
                ], 500);
            }

            // 写入节点在线人数日志
            $online_log = new \App\Http\Models\SsNodeOnlineLog();
            $online_log->node_id = $id;
            $online_log->online_user = $request->get('online');
            $online_log->log_time = time();

            if (!$online_log->save()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 500,
                    'message' => 'Failed to save online log'
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'node_id' => $node->id
                ],
                'message' => 'Node status information updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Report node status failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}