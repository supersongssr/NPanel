/**
 * PoW — 平滑型工作量证明 (Inline Web Worker + Web Crypto SHA-256)
 *
 * 生命周期:
 *   1. powInit()  — 页面加载后调用, 自动请求 challenge 并开始后台计算
 *   2. 表单提交时 powConsume(callback) — 取出已算好的 nonce 附加到请求, 然后自动重算下一个
 */
var PoW = (function () {

    var _challenge = null;   // {timestamp, salt, difficulty, signature}
    var _nonce     = null;   // 算好的 nonce (string)
    var _computing = false;
    var _worker    = null;

    // Worker 源码: 使用 Web Crypto API (crypto.subtle.digest) 进行 SHA-256
    // 分批异步计算, 每批 BATCH 个 Promise.all, 完成后 setTimeout 让出控制权
    var WORKER_SRC = [
        'var enc = new TextEncoder();',
        '',
        'function hexFromBuffer(buf) {',
        '  var arr = new Uint8Array(buf);',
        '  var hex = "";',
        '  for (var i = 0; i < arr.length; i++) hex += ("0" + arr[i].toString(16)).slice(-2);',
        '  return hex;',
        '}',
        '',
        'self.onmessage = function(e) {',
        '  var d = e.data;',
        '  var salt = d.salt, difficulty = d.difficulty;',
        '  var threshold = Math.floor(16777215 / difficulty);',
        '  var nonce = 0;',
        '  var BATCH = 256;',
        '',
        '  function runBatch() {',
        '    var promises = [];',
        '    var startNonce = nonce;',
        '    var end = nonce + BATCH;',
        '',
        '    for (var i = startNonce; i < end; i++) {',
        '      (function(n) {',
        '        var buf = enc.encode(salt + n);',
        '        promises.push(',
        '          crypto.subtle.digest("SHA-256", buf).then(function(hashBuf) {',
        '            var hex = hexFromBuffer(hashBuf);',
        '            var val = parseInt(hex.substring(0, 6), 16);',
        '            return { nonce: n, val: val };',
        '          })',
        '        );',
        '      })(i);',
        '    }',
        '    nonce = end;',
        '',
        '    Promise.all(promises).then(function(results) {',
        '      for (var i = 0; i < results.length; i++) {',
        '        if (results[i].val <= threshold) {',
        '          self.postMessage({ nonce: results[i].nonce, status: "ok" });',
        '          return;',
        '        }',
        '      }',
        '      setTimeout(runBatch, 0);',
        '    });',
        '  }',
        '',
        '  runBatch();',
        '};'
    ].join('\n');


    function createWorker(callback) {
        if (_worker) {
            _worker.terminate();
        }
        var blob = new Blob([WORKER_SRC], {type: 'application/javascript'});
        var url = URL.createObjectURL(blob);
        try {
            _worker = new Worker(url);
        } catch (err) {
            _computing = false;
            console.error('PoW: Worker creation failed:', err);
            return;
        }
        _worker.onmessage = function (e) {
            if (e.data.status === 'ok') {
                _nonce = String(e.data.nonce);
                _computing = false;
                URL.revokeObjectURL(url);
                if (callback) callback();
            }
        };
        _worker.onerror = function (e) {
            _computing = false;
            console.error('PoW Worker error:', e.message || e);
        };
    }

    function startCompute(callback) {
        if (!_challenge) return;
        _computing = true;
        _nonce = null;
        createWorker(callback);
        _worker.postMessage({
            salt: _challenge.salt,
            difficulty: _challenge.difficulty
        });
    }

    /**
     * 页面加载后调用 — 请求 challenge 并开始计算
     */
    function init() {
        var csrf = getCsrf();
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/auth/pow-challenge', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    _challenge = JSON.parse(xhr.responseText);
                    startCompute(function () {
                        // 计算完成, 等待表单提交
                    });
                } catch (e) {
                    console.error('PoW challenge parse error:', e);
                }
            }
        };
        xhr.send('_token=' + encodeURIComponent(csrf));
    }

    function getCsrf() {
        var el = document.querySelector('input[name="_token"]');
        if (el) return el.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        return '';
    }

    /**
     * 表单提交时调用 — 取出 nonce 注入隐藏字段, 然后自动重算
     * @param {Function} doneCb — nonce 就绪后的回调 (nonce 可能还没算完, 等算完再调)
     */
    function consume(doneCb) {
        if (_nonce && _challenge) {
            injectFields(_nonce);
            requestNewChallenge();
            if (doneCb) doneCb();
            return true;
        } else if (_computing) {
            _worker.onmessage = function (e) {
                if (e.data.status === 'ok') {
                    _nonce = String(e.data.nonce);
                    _computing = false;
                    injectFields(_nonce);
                    requestNewChallenge();
                    if (doneCb) doneCb();
                }
            };
            return false;
        }
        return false;
    }

    function injectFields(nonce) {
        setVal('_pow_nonce', nonce);
        setVal('_pow_ts', String(_challenge.timestamp));
        setVal('_pow_salt', _challenge.salt);
        setVal('_pow_diff', String(_challenge.difficulty));
        setVal('_pow_sig', _challenge.signature);
    }

    function setVal(name, val) {
        var el = document.getElementById(name);
        if (el) el.value = val;
    }

    function requestNewChallenge() {
        _nonce = null;
        var csrf = getCsrf();
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/auth/pow-challenge', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    _challenge = JSON.parse(xhr.responseText);
                    startCompute(function () {});
                } catch (e) {}
            }
        };
        xhr.send('_token=' + encodeURIComponent(csrf));
    }

    /**
     * 获取当前 PoW 参数用于 AJAX POST (如 sendCode)
     */
    function getParams() {
        if (!_nonce || !_challenge) return null;
        var params = {
            _pow_nonce: _nonce,
            _pow_ts: String(_challenge.timestamp),
            _pow_salt: _challenge.salt,
            _pow_diff: String(_challenge.difficulty),
            _pow_sig: _challenge.signature
        };
        requestNewChallenge();
        return params;
    }

    /**
     * AJAX 场景: 等 nonce 就绪后调用回调
     */
    function consumeForAjax(doneCb) {
        if (_nonce && _challenge) {
            var params = getParams();
            if (doneCb) doneCb(params);
        } else if (_computing) {
            _worker.onmessage = function (e) {
                if (e.data.status === 'ok') {
                    _nonce = String(e.data.nonce);
                    _computing = false;
                    var params = getParams();
                    if (doneCb) doneCb(params);
                }
            };
        }
    }

    return {
        init: init,
        consume: consume,
        consumeForAjax: consumeForAjax,
        getParams: getParams,
        isReady: function () { return _nonce !== null && _challenge !== null; }
    };
})();
