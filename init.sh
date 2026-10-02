#!/bin/sh
# ============================================================================
# NPanel 订阅页客户端软件 一键同步最新版 (来源: GitHub Releases)
#
# 用法:
#   ./init.sh                  手动运行, 同步清单中全部软件到最新版
#   ./init.sh v2rayN-win       只同步指定软件 (名称可多个, 空格分隔)
#
# 工作方式:
#   1. 查询 GitHub API releases/latest, 获取最新 tag / sha256 / 资产下载地址
#   2. 与下方"当前部署版本"比对: 版本未变且文件完好 -> 跳过下载 (等价 wget -N)
#   3. 版本有变化才下载 -> 体积 + sha256 双校验 -> 软链原子切换 -> 删除旧版本
#   4. 同步成功后把新版本号自动回写到本文件开头 (git diff 可见, 可提交留痕)
#   5. 任何一步失败都保留线上现有文件与软链不动, 不影响用户正在用的下载
#
# 强制重新下载: 把对应版本号改小或清空后运行即可
# 环境变量:
#   GITHUB_PROXY  GitHub 加速前缀(服务器直连不通时用), 如 https://gh-proxy.com/
# 依赖: curl awk sed, sha256sum(可选校验) | 必须在项目根目录运行
# ============================================================================

# ---- 当前部署版本 (脚本自动回写维护, 改小可强制重下) ----
V2RAYN_WIN_VER="7.25.4"
V2RAYN_MAC_VER="7.25.4"
V2RAYNG_ANDROID_VER="2.2.6"

DL_DIR="public/downloads"
GH_PROXY="${GITHUB_PROXY:-}"
SCRIPT_PATH=$(readlink -f "$0")

# 同步清单: 名称|GitHub仓库|资产匹配模式(ERE,锚定$)|对外稳定别名|版本变量名
# 模式说明: $ 锚定防止误匹配变体 (如 -desktop.zip / -fdroid.apk)
CATALOG="
v2rayN-win|2dust/v2rayN|v2rayN-windows-64\.zip$|v2rayN-windows-64.zip|V2RAYN_WIN_VER
v2rayN-mac|2dust/v2rayN|v2rayN-macos-arm64\.dmg$|v2rayN-macos-arm64.dmg|V2RAYN_MAC_VER
v2rayNG-android|2dust/v2rayNG|v2rayNG_[0-9.]+_arm64-v8a\.apk$|v2rayNG_arm64-v8a.apk|V2RAYNG_ANDROID_VER
"

# 注: v2rayU (V2rayU-64.dmg / V2rayU-arm64.dmg) 上游已停更, 作为静态文件直接
#     放在 public/downloads/ 供页面引用, 不参与本脚本的自动同步。

command -v curl >/dev/null 2>&1 || { echo 'error: curl 未安装'; exit 1; }
[ -d "$DL_DIR" ] || { echo "error: $DL_DIR 目录不存在, 请在项目根目录运行"; exit 1; }

# 从 releases/latest 的 JSON 中提取第一个匹配的资产, 输出 3 行: url sha256 size
# 原理: 资产对象内 digest/size 字段必然先于 browser_download_url 出现, 且
# uploader 嵌套对象中不含这三个键, 逐行扫描即可安全配对, 无需 jq
pick_asset() {
    printf '%s\n' "$1" | awk -v pat="$2" '
        match($0, /"digest": *"sha256:[a-f0-9]+"/)    { d=$0; sub(/.*"digest": *"/,"",d); sub(/".*/,"",d) }
        match($0, /"size": *[0-9]+/)                  { s=$0; sub(/.*"size": */,"",s); sub(/[^0-9].*/,"",s) }
        match($0, /"browser_download_url": *"[^"]+"/) { u=$0; sub(/.*"browser_download_url": *"/,"",u); sub(/".*/,"",u)
                                                        if (u ~ pat) { print u; print d; print s; exit } }'
}

update_item() {
    _name=$1; _repo=$2; _pat=$3; _alias=$4; _vervar=$5

    _json=$(curl -fsSL --max-time 30 "${GH_PROXY}https://api.github.com/repos/${_repo}/releases/latest")
    if [ -z "$_json" ]; then
        echo "[${_name}] GitHub API 查询失败, 跳过 (保留现有版本)"
        return 1
    fi
    set -- $(pick_asset "$_json" "$_pat")
    _url=${1:-}; _sha=${2:-}; _size=${3:-}
    if [ -z "$_url" ]; then
        echo "[${_name}] 未匹配到资产 (模式: ${_pat}), 跳过"
        return 1
    fi

    _tag=${_url#*/download/}; _tag=${_tag%%/*}
    _base=${_url##*/}
    _target="${_tag}_${_base}"

    eval "_cur=\$${_vervar}"
    if [ "$_cur" = "$_tag" ] && [ "$(readlink "$_alias" 2>/dev/null)" = "$_target" ] && [ -f "$_target" ]; then
        echo "[${_name}] 已是最新 ${_tag}, 无变化跳过下载"
        return 0
    fi

    echo "[${_name}] 发现新版本 ${_tag}, 开始下载: ${_base}"
    _tmp=".tmp.$$.${_alias}"
    if ! curl -fsSL --retry 3 --max-time 900 -o "$_tmp" "${GH_PROXY}${_url}"; then
        rm -f "$_tmp"
        echo "[${_name}] 下载失败, 保留现有版本"
        return 1
    fi

    # 校验 1: 与 GitHub API 报告的资产体积一致
    if [ -n "$_size" ] && [ "$(wc -c <"$_tmp" | tr -d ' ')" != "$_size" ]; then
        rm -f "$_tmp"; echo "[${_name}] 体积校验失败, 保留现有版本"; return 1
    fi
    # 校验 2: sha256 与 API 提供的 digest 一致
    if [ -n "$_sha" ] && command -v sha256sum >/dev/null 2>&1; then
        if ! echo "${_sha#sha256:}  $_tmp" | sha256sum -c - >/dev/null 2>&1; then
            rm -f "$_tmp"; echo "[${_name}] sha256 校验失败, 保留现有版本"; return 1
        fi
    fi

    mv -f "$_tmp" "$_target"
    ln -sfn "$_target" "$_alias"          # 原子切换: 用户侧下载链接始终有效
    for _old in *_"$_base"; do            # 不保留历史版本, 清理旧版文件
        [ "$_old" = "$_target" ] || rm -f -- "$_old"
    done
    sed -i "s|^${_vervar}=.*|${_vervar}=\"${_tag}\"|" "$SCRIPT_PATH"
    echo "[${_name}] 完成: ${_alias} -> ${_target}"
    return 0
}

cd "$DL_DIR" || exit 1
echo "== $(date '+%F %T') 开始同步订阅页客户端 =="
_rc=0
for _line in $CATALOG; do
    IFS='|' read -r _name _repo _pat _alias _vervar <<EOF
$_line
EOF
    # 指定了参数则只同步指定的软件
    if [ $# -gt 0 ]; then
        case " $* " in
            *" $_name "*) ;;
            *) continue ;;
        esac
    fi
    update_item "$_name" "$_repo" "$_pat" "$_alias" "$_vervar" || _rc=1
done
echo "== 同步结束 =="
exit $_rc
