// YANGHOMESTAY — shared internal API client (Admin / Manager / Staff)
(function () {
  'use strict';

  var cfg = window.ADMIN_CONFIG || {};
  var scriptSrc = document.currentScript && document.currentScript.src ? document.currentScript.src : '';
  var API = cfg.API || (scriptSrc ? new URL('../../backend/api.php', scriptSrc).href : '');
  var csrfToken = null;

  function toast(msg, type) {
    type = type || 'info';
    var wrap = document.getElementById('toasts');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      wrap.id = 'toasts';
      document.body.appendChild(wrap);
    }
    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function () { el.remove(); }, 3800);
  }

  function fetchWithTimeout(url, opts) {
    if (!API) return Promise.reject(new Error('API path is not initialized'));
    if (typeof AbortController === 'undefined') return fetch(url, opts);
    var controller = new AbortController();
    var timer = setTimeout(function () { controller.abort(); }, 15000);
    opts = Object.assign({}, opts, { signal: controller.signal });
    return fetch(url, opts).finally(function () { clearTimeout(timer); });
  }

  function getCsrf() {
    if (csrfToken) return Promise.resolve(csrfToken);
    return fetchWithTimeout(API + '?action=get-csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        csrfToken = j && j.data && j.data.token ? j.data.token : '';
        return csrfToken;
      });
  }

  function request(method, action, params, body) {
    var url = API + '?action=' + encodeURIComponent(action);
    if (params) {
      Object.keys(params).forEach(function (k) {
        var v = params[k];
        if (v === undefined || v === null || v === '') return;
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(String(v));
      });
    }
    var opts = { method: method, credentials: 'same-origin', headers: { Accept: 'application/json' } };

    var start = Promise.resolve();
    if (method !== 'GET') {
      start = getCsrf().then(function (token) {
        opts.headers['Content-Type'] = 'application/json';
        if (token) opts.headers['X-CSRF-Token'] = token;
        if (body !== undefined) opts.body = JSON.stringify(body);
      });
    }

    return start.then(function () { return fetchWithTimeout(url, opts); }).then(function (r) {
      return r.json().catch(function () { return { success: false, message: 'Phản hồi máy chủ không hợp lệ' }; })
        .then(function (j) {
          j._status = r.status;
          if (r.status === 419) csrfToken = null;
          return j;
        });
    });
  }

  function handleError(j) {
    var msg = (j && j.message) ? j.message : 'Có lỗi xảy ra';
    if (j && j.errors) {
      var keys = Object.keys(j.errors);
      if (keys.length && j.errors[keys[0]]) msg = String(j.errors[keys[0]]);
    }
    toast(msg, 'error');
    return msg;
  }

  window.AdminAPI = {
    get: function (action, params) { return request('GET', action, params); },
    post: function (action, body) { return request('POST', action, null, body || {}); },
    patch: function (action, body) { return request('PATCH', action, null, body || {}); },
    delete: function (action, body) { return request('DELETE', action, null, body || {}); },
    toast: toast,
    handleError: handleError
  };
})();
