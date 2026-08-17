// YANGHOMESTAY — shared internal portal login
(function () {
  'use strict';

  var form = document.getElementById('login-form');
  if (!form) return;
  var portal = document.body.getAttribute('data-portal') || 'admin';

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btn');
    var navigating = false;
    btn.disabled = true;
    btn.textContent = 'Đang đăng nhập…';

    window.AdminAPI.post('login', {
      identifier: document.getElementById('identifier').value.trim(),
      password: document.getElementById('password').value,
      portal: portal
    }).then(function (j) {
      if (!j.success) {
        window.AdminAPI.handleError(j);
        return;
      }
      navigating = true;
      window.AdminAPI.toast('Đăng nhập thành công', 'success');
      setTimeout(function () { location.href = 'index.html'; }, 250);
    }).catch(function (err) {
      var msg = err && err.name === 'AbortError' ? 'Máy chủ phản hồi quá chậm. Vui lòng thử lại.' : 'Không kết nối được máy chủ';
      window.AdminAPI.toast(msg, 'error');
    }).finally(function () {
      if (!navigating) {
        btn.disabled = false;
        btn.textContent = 'Đăng nhập';
      }
    });
  });
})();
