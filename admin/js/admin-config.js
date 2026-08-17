// ═════════════════════════════════════════════════════════════════════════
// YANGHOMESTAY — ADMIN CONFIG
// Đường dẫn TƯƠNG ĐỐI — không hard-code domain.
//   File này: <project>/admin/js/admin-config.js
//   API:      <project>/backend/api.php
// ═════════════════════════════════════════════════════════════════════════

(function () {
  'use strict';
  var scriptSrc = document.currentScript && document.currentScript.src ? document.currentScript.src : '';
  var apiUrl = '';
  if (scriptSrc) {
    // admin/js/xxx.js -> lên 2 cấp -> <project>/backend/api.php
    apiUrl = new URL('../../backend/api.php', scriptSrc).href;
  }
  window.ADMIN_CONFIG = { API: apiUrl };
})();
