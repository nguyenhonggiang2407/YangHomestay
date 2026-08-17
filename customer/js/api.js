// ═════════════════════════════════════════════════════════════════════════
// YANGHOMESTAY — API CLIENT
// Mọi request qua PHP backend (KHÔNG gọi Supabase trực tiếp từ browser).
// - GET: không cần CSRF
// - POST/PATCH/DELETE: tự lấy CSRF token (get-csrf) và gửi header X-CSRF-Token
// ═════════════════════════════════════════════════════════════════════════

(function () {
  'use strict';

  var CONFIG = window.APP_CONFIG;
  var csrfToken = null;
  var csrfPromise = null;

  function fetchWithTimeout(url, opts) {
    if (typeof AbortController === 'undefined') return fetch(url, opts);
    var controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, 15000);
    opts = Object.assign({}, opts || {}, { signal: controller.signal });
    return fetch(url, opts).finally(function () { clearTimeout(timer); });
  }

  function getCsrf() {
    if (csrfToken) return Promise.resolve(csrfToken);
    if (csrfPromise) return csrfPromise;
    csrfPromise = fetchWithTimeout(CONFIG.CUSTOMER_API + '?action=get-csrf', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        csrfToken = j && j.data && j.data.token ? j.data.token : '';
        return csrfToken;
      })
      .catch(function () { return ''; })
      .finally(function () { csrfPromise = null; });
    return csrfPromise;
  }

  function request(method, action, params, body) {
    var url = CONFIG.CUSTOMER_API + '?action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (k) {
        var v = params[k];
        if (v === undefined || v === null || v === '') return;
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(String(v));
      });
    }

    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    };

    if (method === 'GET') {
      return fetchWithTimeout(url, opts).then(parse);
    }

    // POST/PATCH/DELETE → CSRF
    return getCsrf().then(function (token) {
      opts.headers['Content-Type'] = 'application/json';
      if (token) opts.headers['X-CSRF-Token'] = token;
      if (body !== undefined) opts.body = JSON.stringify(body);
      return fetchWithTimeout(url, opts).then(parse);
    });
  }

  function parse(r) {
    if (r.status === 419) csrfToken = null;
    return r.json().catch(function () {
      return { success: false, message: 'Lỗi kết nối máy chủ' };
    }).then(function (j) {
      j._status = r.status;
      return j;
    });
  }

  window.YangAPI = {
    get: function (action, params) { return request('GET', action, params); },
    post: function (action, body) { return request('POST', action, null, body || {}); },
    patch: function (action, body) { return request('PATCH', action, null, body || {}); },
    del: function (action, body) { return request('DELETE', action, null, body || {}); }
  };
})();
