// ═════════════════════════════════════════════════════════════════════════
// YANGHOMESTAY — UI HELPERS
// Toast · formatVND · escape (textContent) · auth state · layout injection
// ═════════════════════════════════════════════════════════════════════════

(function () {
  'use strict';

  var CONFIG = window.APP_CONFIG;

  /* ── Toast ── */
  var toastWrap = null;
  var lastToastKey = '';
  var lastToastAt = 0;
  function ensureWrap() {
    if (!toastWrap) {
      toastWrap = document.createElement('div');
      toastWrap.className = 'toast-wrap';
      toastWrap.setAttribute('role', 'status');
      toastWrap.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastWrap);
    }
    return toastWrap;
  }
  function toast(message, type) {
    type = type || 'info';
    message = String(message || '');
    var now = Date.now();
    var key = type + '|' + message;
    // Chặn toast giống hệt bị nhân đôi khi nhiều request cùng lỗi trong một nhịp UI.
    if (key === lastToastKey && now - lastToastAt < 1400) return;
    lastToastKey = key;
    lastToastAt = now;

    var el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = message;
    ensureWrap().appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 3600);
    setTimeout(function () { el.remove(); }, 4000);
  }

  /* ── Money ── */
  function formatVND(n) {
    n = Number(n) || 0;
    return n.toLocaleString('vi-VN').replace(/,/g, '.') + ' ₫';
  }

  /* ── Dates ── */
  function fmtDate(iso) {
    if (!iso) return '';
    var d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso);
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function localDateISO(date) {
    date = date || new Date();
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function addDaysISO(iso, days) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(iso || '')) return '';
    var parts = iso.split('-').map(Number);
    var d = new Date(parts[0], parts[1] - 1, parts[2]);
    d.setDate(d.getDate() + Number(days || 0));
    return localDateISO(d);
  }

  function validateDateRange(checkIn, checkOut) {
    if (!checkIn && !checkOut) return '';
    if (!checkIn || !checkOut) return 'Vui lòng chọn cả ngày nhận và ngày trả phòng.';
    var today = localDateISO();
    if (checkIn < today) return 'Ngày nhận phòng phải là hôm nay hoặc trong tương lai.';
    if (checkOut <= checkIn) return 'Ngày trả phòng phải sau ngày nhận phòng.';
    return '';
  }

  /* ── Escape ── */
  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  /* ── Status labels ── */
  var STATUS_LABELS = {
    pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', checkin: 'Đang ở',
    checkout: 'Đã hoàn thành', cancelled: 'Đã hủy'
  };
  var PAY_LABELS = { unpaid: 'Chưa thanh toán', partial: 'Thanh toán một phần', paid: 'Đã thanh toán', refunded: 'Đã hoàn tiền' };

  function statusLabel(s) { return STATUS_LABELS[s] || s; }
  function payLabel(s) { return PAY_LABELS[s] || s; }

  var UNIT_TYPE_LABELS = {
    studio: 'Studio', apartment: 'Căn hộ', loft: 'Loft',
    couple: 'Căn đôi', family: 'Căn gia đình', villa: 'Villa', house: 'Nhà nguyên căn'
  };
  function unitTypeLabel(t) { return UNIT_TYPE_LABELS[t] || t; }


  /* ── Lightweight inline icons (no external runtime dependency) ── */
  var ICONS = {
    map: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    users: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    bed: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 4v16M22 11v9M2 16h20M6 11V7h6a4 4 0 0 1 4 4"/></svg>',
    heart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
    star: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.2L5.8 21 7 14.2l-5-4.9 6.9-1Z"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    home: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/></svg>',
    building: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v16M9 7h3M9 11h3M9 15h3M17 9h3v12M7 21h13"/></svg>',
    leaf: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 20A7 7 0 0 1 9.8 6.1C15 4 20 5 21 5c0 1 .8 6-2 10.3A7 7 0 0 1 11 20Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6.94C9.85 12.7 13.08 12 17 12"/></svg>',
    lake: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18M5 7l3-4 3 4M13 7l3-3 3 3M3 13c2 0 2-1 4-1s2 1 4 1 2-1 4-1 2 1 4 1 2-1 2-1M3 18c2 0 2-1 4-1s2 1 4 1 2-1 4-1 2 1 4 1 2-1 2-1"/></svg>',
    food: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3v7a3 3 0 0 0 3 3V3M4 7h3M7 13v8M15 3v18M15 3c4 2 5 6 5 9h-5"/></svg>',
    briefcase: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/></svg>',
    couple: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
    bath: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12V6a3 3 0 0 1 6 0M4 12h16v2a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Z"/><path d="M7 19v2M17 19v2"/></svg>',
    area: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg>',
    tag: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 13 11 22l-9-9V2h11l7 7a3 3 0 0 1 0 4Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
    check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
    alert: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>'
  };
  function icon(name, cls) { return '<span class="ui-icon ' + (cls || '') + '">' + (ICONS[name] || '') + '</span>'; }

  /* ── Auth state ── */
  var customer = null;
  var authLoaded = false;

  function checkAuth() {
    if (authLoaded) return Promise.resolve(customer);
    return window.YangAPI.get('check-auth').then(function (j) {
      // Lỗi 5xx/503 không đồng nghĩa user đã đăng xuất. Không cache "false"
      // để request sau có thể phục hồi khi backend/database trở lại.
      if (!j || j.success === false) {
        authLoaded = false;
        return null;
      }
      authLoaded = true;
      customer = j.authenticated ? (j.data || null) : null;
      return customer;
    }).catch(function () {
      authLoaded = false;
      return null;
    });
  }
  function getCustomer() { return customer; }
  function isLoggedIn() { return !!customer; }
  function setCustomer(c) { customer = c; authLoaded = true; }
  function clearAuthCache() { customer = null; authLoaded = false; }

  /* ── Layout injection ── */
  function injectHeader() {
    var el = document.getElementById('site-header');
    if (!el) return;
    var current = (location.pathname.split('/').pop() || 'index.html').toLowerCase();
    function active(files) { return files.indexOf(current) !== -1 ? ' active' : ''; }
    el.innerHTML =
      '<div class="container header-inner">' +
      '  <a class="brand" href="./" aria-label="YangHomestay - Trang chủ">' +
      '    <span class="brand-mark">Y</span><span class="brand-copy">YangHomestay<small>Hà Nội · Boutique stays</small></span>' +
      '  </a>' +
      '  <nav class="nav" id="main-nav" aria-label="Điều hướng chính">' +
      '    <a href="./#districts" class="nav-discover">Khám phá</a>' +
      '    <a href="homestays.html" class="' + active(['homestays.html','unit-detail.html','property-detail.html']) + '">Homestay</a>' +
      '    <a href="./#experiences" class="' + active(['experience-detail.html']) + '">Trải nghiệm Hà Nội</a>' +
      '    <a href="yangmatch.html" class="' + active(['yangmatch.html']) + '">YangMatch</a>' +
      '    <span class="nav-separator" aria-hidden="true"></span>' +
      '    <a class="nav-favorite" href="favorites.html">' + icon('heart') + '<span>Yêu thích</span></a>' +
      '    <span class="user-chip" id="nav-user"></span>' +
      '  </nav>' +
      '  <button class="hamburger" id="hamburger" type="button" aria-label="Mở menu" aria-expanded="false"><span></span><span></span><span></span></button>' +
      '</div>';

    var hamburger = document.getElementById('hamburger');
    var nav = document.getElementById('main-nav');
    if (hamburger && nav) {
      hamburger.addEventListener('click', function () {
        var opened = nav.classList.toggle('open');
        hamburger.classList.toggle('open', opened);
        hamburger.setAttribute('aria-expanded', opened ? 'true' : 'false');
      });
    }

    var userEl = document.getElementById('nav-user');
    checkAuth().then(function (c) {
      if (!userEl) return;
      if (c) {
        var initial = esc((c.full_name || c.email || 'Y').trim().charAt(0).toUpperCase());
        userEl.innerHTML = '<a class="avatar-link" href="profile.html" title="Hồ sơ"><span class="avatar-mini">' + initial + '</span><span class="avatar-name">' + esc(c.full_name || c.email) + '</span></a>' +
          '<a href="bookings.html">Đơn đặt</a>' +
          '<button type="button" id="nav-logout" class="nav-logout">Đăng xuất</button>';
        var lo = document.getElementById('nav-logout');
        if (lo) lo.addEventListener('click', function () {
          window.YangAPI.post('logout').then(function () { clearAuthCache(); location.href = 'index.html'; });
        });
      } else {
        userEl.innerHTML = '<a class="nav-login" href="login.html">Đăng nhập</a>';
      }
    });
  }

  function injectFooter() {
    var el = document.getElementById('site-footer');
    if (!el) return;
    el.innerHTML =
      '<div class="container">' +
      '<div class="footer-grid">' +
      '<div class="footer-brand"><a class="brand" href="./" style="color:#fff"><span class="brand-mark">Y</span><span>YangHomestay</span></a>' +
      '<p>Khám phá Hà Nội theo cách của bạn — những căn ở được chọn lọc cho từng nhịp sống.</p><span class="footer-note">Hà Nội · Việt Nam</span></div>' +
      '<div><h4>Khám phá</h4><ul><li><a href="homestays.html">Homestay</a></li><li><a href="./#districts">Khu vực</a></li><li><a href="yangmatch.html">YangMatch</a></li><li><a href="./#experiences">Trải nghiệm Hà Nội</a></li></ul></div>' +
      '<div><h4>Hỗ trợ</h4><ul><li><a href="contact.html">Liên hệ</a></li><li><a href="bookings.html">Đơn đặt của tôi</a></li><li><a href="favorites.html">Yêu thích</a></li><li><a href="contact.html#faq">Câu hỏi thường gặp</a></li></ul></div>' +
      '<div><h4>YangHomestay</h4><ul><li><a href="./#story">Về chúng tôi</a></li><li><a href="./#reviews">Đánh giá</a></li><li><a href="contact.html">Điều khoản & hỗ trợ</a></li><li><a href="contact.html">Quyền riêng tư</a></li></ul></div>' +
      '</div><div class="footer-bottom"><span>© ' + new Date().getFullYear() + ' YangHomestay</span><span>Ở đúng gu, sống đúng chất Hà Nội.</span></div></div>';
  }

  /* ── Standard error handling from API json ── */
  function handleError(j, fallbackMsg) {
    var msg = (j && j.message) ? j.message : (fallbackMsg || 'Có lỗi xảy ra, vui lòng thử lại');
    if (j && j.errors) {
      var first = Object.values(j.errors)[0];
      if (first) msg = String(first);
    }
    toast(msg, 'error');
    return msg;
  }

  /* ── Card renderers ── */
  function unitCard(u) {
    var img = u.cover_image && u.cover_image.image_url ? window.assetUrl(u.cover_image.image_url) : window.assetUrl('assets/images/homestays/fallback/unit-fallback.jpg');
    var district = u.property && u.property.district ? esc(u.property.district) : 'Hà Nội';
    var rating = Number(u.avg_rating) || 0;
    var reviews = Number(u.review_count) || 0;
    var badgeText = u.featured ? 'Được yêu thích' : (district === 'Tây Hồ' ? 'Gần Hồ Tây' : 'Căn chọn lọc');
    var ratingHtml = reviews > 0
      ? icon('star','star-icon') + '<strong>' + rating.toFixed(1) + '</strong><span>· ' + reviews + ' đánh giá</span>'
      : icon('star','star-icon') + '<span>Mới trên YangHomestay</span>';
    return '<article class="card stay-card">' +
      '<a class="card-media" href="unit-detail.html?id=' + u.id + '" aria-label="Xem ' + esc(u.name) + '">' +
      '<span class="badge">' + badgeText + '</span><img src="' + esc(img) + '" alt="' + esc(u.name) + '" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=window.assetUrl(\'assets/images/homestays/fallback/unit-fallback.jpg\')"></a>' +
      '<button class="heart-btn" data-unit="' + u.id + '" aria-label="Thêm vào yêu thích" title="Yêu thích">' + icon('heart') + '</button>' +
      '<div class="card-body">' +
      '<div class="card-topline"><span class="card-loc">' + icon('map') + district + ', Hà Nội</span><span class="card-rating">' + ratingHtml + '</span></div>' +
      '<h3 class="card-title"><a href="unit-detail.html?id=' + u.id + '">' + esc(u.name) + '</a></h3>' +
      '<div class="card-meta"><span>' + icon('users') + (u.max_guests || 2) + ' khách</span><span>' + icon('bed') + (u.bedrooms || 1) + ' phòng ngủ</span></div>' +
      '<div class="card-footer"><div class="card-price"><b>' + formatVND(u.base_price) + '</b><span>/ đêm</span></div>' +
      '<a class="card-link" href="unit-detail.html?id=' + u.id + '">Xem căn ' + icon('arrow') + '</a></div></div></article>';
  }

  function propertyCard(p) {
    var img = p.cover_image && p.cover_image.image_url ? window.assetUrl(p.cover_image.image_url) : '';
    var imgTag = img
      ? '<img src="' + esc(img) + '" alt="' + esc(p.name) + '" loading="lazy" onerror="this.onerror=null;this.src=window.assetUrl(\'assets/images/homestays/fallback/property-fallback.jpg\')">'
      : '<img src="' + window.assetUrl('assets/images/homestays/fallback/property-fallback.jpg') + '" alt="' + esc(p.name) + '" loading="lazy">';
    var badge = p.featured ? '<span class="badge badge-olive">Nổi bật</span>' : '';
    return (
      '<article class="card">' +
      '  <div class="card-media">' + badge + imgTag + '</div>' +
      '  <div class="card-body">' +
      '    <h3 class="card-title">' + esc(p.name) + '</h3>' +
      '    <div class="card-loc">' + icon('map') + esc(p.district || 'Hà Nội') + ', Hà Nội</div>' +
      '    <div class="card-meta">' + (p.unit_count || 0) + ' căn homestay</div>' +
      '    <div class="card-footer">' +
      '      <span style="font-size:13px;color:var(--earth-light)">' + esc(p.short_description || '') + '</span>' +
      '      <a class="btn btn-outline" href="property-detail.html?id=' + p.id + '">Khám phá</a>' +
      '    </div>' +
      '  </div>' +
      '</article>'
    );
  }

  /* ── Skeleton ── */
  function skeletonCard() {
    return (
      '<div class="card">' +
      '  <div class="card-media"><div class="skeleton" style="width:100%;height:100%"></div></div>' +
      '  <div class="card-body">' +
      '    <div class="skeleton" style="height:20px;width:70%"></div>' +
      '    <div class="skeleton" style="height:14px;width:45%"></div>' +
      '    <div class="skeleton" style="height:14px;width:55%"></div>' +
      '    <div class="skeleton" style="height:22px;width:40%"></div>' +
      '  </div>' +
      '</div>'
    );
  }

  window.YangUI = {
    toast: toast, formatVND: formatVND, fmtDate: fmtDate, localDateISO: localDateISO, addDaysISO: addDaysISO, validateDateRange: validateDateRange, esc: esc,
    statusLabel: statusLabel, payLabel: payLabel, unitTypeLabel: unitTypeLabel,
    checkAuth: checkAuth, getCustomer: getCustomer, isLoggedIn: isLoggedIn,
    setCustomer: setCustomer, clearAuthCache: clearAuthCache,
    injectHeader: injectHeader, injectFooter: injectFooter,
    handleError: handleError, icon: icon, unitCard: unitCard, propertyCard: propertyCard,
    skeletonCard: skeletonCard
  };
})();
