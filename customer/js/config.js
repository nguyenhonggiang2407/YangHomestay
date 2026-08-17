// ═════════════════════════════════════════════════════════════════════════
// YANGHOMESTAY — FRONTEND CONFIG
// Đường dẫn TƯƠNG ĐỐI tính từ vị trí file này — không hard-code domain
// (chạy được trên localhost, hosting, subfolder).
//   File này:  <project>/customer/js/config.js
//   API:       <project>/backend/
//   Assets:    <project>/assets/
// ═════════════════════════════════════════════════════════════════════════

(function () {
  'use strict';

  var scriptSrc = '';
  if (document.currentScript && document.currentScript.src) {
    scriptSrc = document.currentScript.src;
  }

  var base;
  if (scriptSrc) {
    // customer/js/config.js -> lên 2 cấp -> <project>/
    base = new URL('../../', scriptSrc).href;
  } else {
    base = '../';
  }

  window.APP_CONFIG = {
    BASE: base,                                        // <project>/
    API_BASE: base + 'backend/',
    CUSTOMER_API: base + 'backend/customer-api.php',   // API khách hàng
    ADMIN_API: base + 'backend/api.php',               // API admin
    ASSETS: base + 'assets/'
  };

  /**
   * Map đường dẫn ảnh từ database (relative 'assets/...')
   * về URL tuyệt đối. URL http(s)/data giữ nguyên.
   */
  window.assetUrl = function (url) {
    if (!url) return '';
    if (/^https?:\/\//i.test(url) || url.startsWith('data:')) return url;
    var u = url.replace(/^\/+/, '');
    if (u.indexOf('assets/') === 0) {
      return window.APP_CONFIG.BASE + u;
    }
    return window.APP_CONFIG.BASE + u;
  };
})();
