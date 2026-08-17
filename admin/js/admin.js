// ═════════════════════════════════════════════════════════════════════════
// YANGHOMESTAY — ADMIN SPA
// Sections: dashboard · calendar · properties · units · bookings · customers
//           · reviews · pricing · promotions · revenue · contacts
// Mọi dữ liệu từ Supabase qua PHP backend (backend/api.php).
// ═════════════════════════════════════════════════════════════════════════

(function () {
  'use strict';

  var API = window.AdminAPI;
  var current = 'dashboard';
  var SECTIONS = {
    dashboard: { title: 'Dashboard', load: loadDashboard },
    calendar: { title: 'Lịch đặt phòng', load: loadCalendar },
    properties: { title: 'Homestay', load: loadProperties },
    units: { title: 'Căn homestay', load: loadUnits },
    bookings: { title: 'Đặt phòng', load: loadBookings },
    customers: { title: 'Khách hàng', load: loadCustomers },
    reviews: { title: 'Đánh giá', load: loadReviews },
    pricing: { title: 'Quản lý giá', load: loadPricing },
    promotions: { title: 'Ưu đãi', load: loadPromotions },
    revenue: { title: 'Doanh thu', load: loadRevenue },
    contacts: { title: 'Liên hệ', load: loadContacts },
    personnel: { title: 'Nhân sự nội bộ', load: loadPersonnel },
    audit: { title: 'Audit Logs', load: loadAudit },
    settings: { title: 'Cài đặt hệ thống', load: loadSettings }
  };

  var STATUS_LABELS = {
    pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', checkin: 'Đang ở',
    checkout: 'Đã hoàn thành', cancelled: 'Đã hủy'
  };
  var PAY_LABELS = { unpaid: 'Chưa thanh toán', partial: 'Một phần', paid: 'Đã thanh toán', refunded: 'Đã hoàn tiền' };

  function fmtVND(n) { return (Number(n) || 0).toLocaleString('vi-VN').replace(/,/g, '.') + ' ₫'; }
  function fmtDate(iso) {
    if (!iso) return '';
    var d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso);
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  /* ═══════════════════════════════════
     AUTH GUARD
     ═══════════════════════════════════ */
  function guard() {
    var portal = document.body.getAttribute('data-portal') || 'admin';
    API.get('check-auth', { portal: portal }).then(function (j) {
      if (!j.authenticated) { location.href = 'login.html'; return; }
      if (j.authorized === false && j.redirect_portal) { location.href = '../' + j.redirect_portal + '/index.html'; return; }
      var a = j.data || {};
      document.getElementById('admin-info').textContent = a.full_name || a.username || '';
      document.getElementById('admin-user').textContent = (a.full_name || a.username) + ' · ' + (a.role || '');
      document.getElementById('btn-logout').addEventListener('click', function (e) {
        e.preventDefault();
        API.post('logout').then(function () { location.href = 'login.html'; });
      });
      navigate(current);
    }).catch(function () { location.href = 'login.html'; });
  }

  /* ═══════════════════════════════════
     ROUTER
     ═══════════════════════════════════ */
  function navigate(sec) {
    if (!SECTIONS[sec]) sec = 'dashboard';
    current = sec;
    document.querySelectorAll('#side-nav [data-sec]').forEach(function (a) {
      a.classList.toggle('active', a.getAttribute('data-sec') === sec);
    });
    document.getElementById('page-title').textContent = SECTIONS[sec].title;
    SECTIONS[sec].load();
    document.getElementById('sidebar').classList.remove('open');
  }

  document.getElementById('side-nav').addEventListener('click', function (e) {
    var a = e.target.closest('[data-sec]');
    if (a && a.getAttribute('data-sec')) { e.preventDefault(); navigate(a.getAttribute('data-sec')); }
  });
  document.getElementById('menu-btn').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('open');
  });

  /* ═══════════════════════════════════
     MODAL HELPER
     ═══════════════════════════════════ */
  var modalEl = null;
  function openModal(html, title) {
    closeModal();
    modalEl = document.createElement('div');
    modalEl.className = 'modal-backdrop';
    modalEl.innerHTML = '<div class="modal"><button class="modal-close" id="m-close">×</button><h3>' + esc(title || '') + '</h3><div id="m-body">' + html + '</div></div>';
    document.body.appendChild(modalEl);
    modalEl.addEventListener('click', function (e) { if (e.target === modalEl) closeModal(); });
    document.getElementById('m-close').addEventListener('click', closeModal);
    return modalEl;
  }
  function closeModal() { if (modalEl) { modalEl.remove(); modalEl = null; } }

  function contentBox() { return document.getElementById('content'); }

  /* ═══════════════════════════════════
     DASHBOARD
     ═══════════════════════════════════ */
  function loadDashboard() {
    var box = contentBox();
    box.innerHTML = '<div class="state-box"><div class="skeleton" style="height:40px;max-width:300px;margin:0 auto"></div></div>';
    API.get('dashboard').then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box"><h3>' + esc(j.message) + '</h3></div>'; return; }
      var d = j.data;
      var bs = d.bookings_by_status || {};
      var kpis = [
        { label: 'Tổng homestay', value: d.properties_total, icon: '' },
        { label: 'Tổng căn', value: d.units_total, icon: '' },
        { label: 'Đặt chờ xác nhận', value: d.pending_bookings, icon: '' },
        { label: 'Đã xác nhận', value: d.confirmed_bookings, icon: '' },
        { label: 'Check-in hôm nay', value: d.checkins_today, icon: '' },
        { label: 'Check-out hôm nay', value: d.checkouts_today, icon: '' },
        { label: 'Doanh thu hôm nay', value: fmtVND(d.revenue_today), icon: '', small: true },
        { label: 'Doanh thu tháng', value: fmtVND(d.revenue_month), icon: '', small: true },
        { label: 'Doanh thu năm', value: fmtVND(d.revenue_year), icon: '', small: true },
        { label: 'Occupancy tháng', value: d.occupancy_rate + '%', icon: '' },
        { label: 'Tỷ lệ hủy', value: d.cancellation_rate + '%', icon: '' }
      ];
      var html = '<div class="kpi-grid">' + kpis.map(function (k) {
        return '<div class="kpi"><div class="label">' + k.label + '</div><div class="value' + (k.small ? ' small' : '') + '">' + k.value + '</div></div>';
      }).join('') + '</div>';

      // revenue 6 months chart
      html += '<div class="panel"><div class="panel-head"><h3>Doanh thu 6 tháng (đã thanh toán)</h3></div><div class="bars" id="rev-chart">' +
        (d.revenue_6m || []).map(function (m) {
          var max = Math.max.apply(null, (d.revenue_6m || []).map(function (x) { return x.revenue; })) || 1;
          var h = Math.max(3, Math.round(m.revenue / max * 100));
          return '<div class="bar-col"><div class="bar-val">' + (m.revenue ? (Math.round(m.revenue / 100000) / 10) + 'tr' : '0') + '</div><div class="bar" style="height:' + h + '%"></div><div class="bar-label">' + esc(m.label) + '</div></div>';
        }).join('') + '</div></div>';

      // booking status distribution
      html += '<div class="panel"><div class="panel-head"><h3>Trạng thái đặt phòng</h3></div><div class="kpi-grid" style="grid-template-columns:repeat(5,1fr)">' +
        ['pending', 'confirmed', 'checkin', 'checkout', 'cancelled'].map(function (s) {
          return '<div class="kpi"><div class="label">' + (STATUS_LABELS[s] || s) + '</div><div class="value small">' + (bs[s] || 0) + '</div></div>';
        }).join('') + '</div></div>';
      box.innerHTML = html;
    });
  }

  /* ═══════════════════════════════════
     CALENDAR
     ═══════════════════════════════════ */
  function loadCalendar() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="filters"><input type="date" id="cal-start" value="' + iso(-14) + '"><span>→</span><input type="date" id="cal-end" value="' + iso(45) + '"><button class="btn" id="cal-go">Hiển thị</button></div><div class="cal-scroll" id="cal-body"><div class="state-box">Đang tải…</div></div></div>';

    function iso(offset) {
      var d = new Date();
      d.setDate(d.getDate() + offset);
      return d.toISOString().split('T')[0];
    }

    function render() {
      var start = document.getElementById('cal-start').value;
      var end = document.getElementById('cal-end').value;
      API.get('calendar', { start: start, end: end }).then(function (j) {
        if (!j.success) { document.getElementById('cal-body').innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
        var d = j.data;
        var dates = [];
        var cur = new Date(start + 'T00:00:00');
        var last = new Date(end + 'T00:00:00');
        while (cur <= last) {
          dates.push(cur.toISOString().split('T')[0]);
          cur.setDate(cur.getDate() + 1);
        }
        var header = '<tr><th style="min-width:140px;text-align:left">Căn</th>' + dates.map(function (dd) {
          var dt = new Date(dd + 'T00:00:00');
          return '<th>' + dt.getDate() + '<br><small style="color:var(--muted)">' + ['CN','T2','T3','T4','T5','T6','T7'][dt.getDay()] + '</small></th>';
        }).join('') + '</tr>';

        var rows = d.units.map(function (u) {
          var cells = dates.map(function (dd) {
            // block?
            var block = d.blocks.find(function (b) { return b.unit_id === u.id && dd >= b.start_date && dd < b.end_date; });
            if (block) {
              var width = nights(block.start_date, block.end_date) * 100 / dates.length;
              var left = dates.indexOf(block.start_date) * 100 / dates.length;
              return '<td><div class="cal-bar cal-blocked" style="left:' + left + '%;width:' + width + '%" title="Chặn: ' + esc(block.reason || '') + '">Chặn</div></td>';
            }
            // bookings overlapping this date
            var bks = d.bookings.filter(function (b) { return b.unit_id === u.id && dd >= b.check_in && dd < b.check_out; });
            if (bks.length) {
              var b = bks[0];
              var w = nights(b.check_in, b.check_out) * 100 / dates.length;
              var l = dates.indexOf(b.check_in) * 100 / dates.length;
              return '<td><div class="cal-bar cal-' + b.status + '" style="left:' + l + '%;width:' + w + '%" data-bid="' + b.id + '" title="' + esc(b.guest_name || '') + ' · ' + esc(b.booking_reference) + '">' + (b.total_guests || '') + ' kh</div></td>';
            }
            return '<td></td>';
          }).join('');
          return '<tr><td style="font-weight:600">' + esc(u.name) + '</td>' + cells + '</tr>';
        }).join('');

        document.getElementById('cal-body').innerHTML = '<table class="cal-table"><thead>' + header + '</thead><tbody>' + rows + '</tbody></table>';

        document.querySelectorAll('#cal-body .cal-bar[data-bid]').forEach(function (bar) {
          bar.addEventListener('click', function () {
            API.get('admin-booking-detail', { id: bar.getAttribute('data-bid') }).then(function (bj) {
              if (bj.success) showBookingDetail(bj.data);
            });
          });
        });
      });
    }
    function nights(a, b) { return Math.round((new Date(b) - new Date(a)) / 86400000); }
    document.getElementById('cal-go').addEventListener('click', render);
    render();
  }

  /* ═══════════════════════════════════
     PROPERTIES
     ═══════════════════════════════════ */
  function loadProperties() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="panel-head"><h3>Danh sách homestay</h3><button class="btn btn-primary" id="p-new">+ Thêm homestay</button></div><div class="table-wrap" id="p-table"><div class="state-box">Đang tải…</div></div></div>';
    fetchProperties();
    document.getElementById('p-new').addEventListener('click', function () { propertyForm(null); });
  }

  function fetchProperties() {
    API.get('admin-properties', { page: 1, limit: 50 }).then(function (j) {
      var box = document.getElementById('p-table');
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>ID</th><th>Mã</th><th>Tên</th><th>Quận</th><th>Nổi bật</th><th>Trạng thái</th><th></th></tr></thead><tbody>' +
        items.map(function (p) {
          return '<tr><td>' + p.id + '</td><td>' + esc(p.code) + '</td><td><b>' + esc(p.name) + '</b></td><td>' + esc(p.district || '') + '</td>' +
            '<td>' + (p.featured ? '' : '—') + '</td><td><span class="badge st-' + (p.status || 'inactive') + '">' + esc(p.status) + '</span></td>' +
            '<td style="white-space:nowrap"><button class="btn btn-sm" data-act="edit" data-id="' + p.id + '">Sửa</button> ' +
            '<button class="btn btn-sm" data-act="status" data-id="' + p.id + '" data-status="' + (p.status === 'active' ? 'inactive' : 'active') + '">' + (p.status === 'active' ? 'Ẩn' : 'Hiện') + '</button></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Chưa có homestay</div>';
      bindTableActions(box, { edit: function (btn) { propertyForm(Number(btn.getAttribute('data-id'))); }, status: function (btn) { setPropertyStatus(btn); } });
    });
  }

  function setPropertyStatus(btn) {
    var id = btn.getAttribute('data-id');
    var status = btn.getAttribute('data-status');
    API.post('admin-property-status', { id: Number(id), status: status }).then(function (j) {
      if (j.success) { API.toast('Đã cập nhật', 'success'); fetchProperties(); } else API.handleError(j);
    });
  }

  function propertyForm(id) {
    var title = id ? 'Sửa homestay' : 'Thêm homestay';
    var p = { code: '', name: '', district: '', ward: '', address: '', latitude: '', longitude: '', short_description: '', description: '', story: '', checkin_time: '14:00:00', checkout_time: '12:00:00', status: 'active', featured: false };
    var apply = function (data) { renderForm(data); };
    if (id) {
      API.get('admin-property-get', { id: id }).then(function (j) { if (j.success) apply(j.data); });
    } else {
      apply(p);
    }

    function renderForm(data) {
      openModal(
        '<div class="form-grid">' +
        '<div class="form-group"><label>Mã (code)</label><input id="f-code" value="' + esc(data.code || '') + '" maxlength="20"></div>' +
        '<div class="form-group"><label>Tên</label><input id="f-name" value="' + esc(data.name || '') + '" maxlength="150"></div>' +
        '<div class="form-group"><label>Quận</label><input id="f-district" value="' + esc(data.district || '') + '" maxlength="100"></div>' +
        '<div class="form-group"><label>Phường</label><input id="f-ward" value="' + esc(data.ward || '') + '" maxlength="100"></div>' +
        '<div class="form-group full"><label>Địa chỉ</label><input id="f-address" value="' + esc(data.address || '') + '" maxlength="255"></div>' +
        '<div class="form-group"><label>Latitude</label><input id="f-lat" value="' + esc(data.latitude != null ? data.latitude : '') + '"></div>' +
        '<div class="form-group"><label>Longitude</label><input id="f-lng" value="' + esc(data.longitude != null ? data.longitude : '') + '"></div>' +
        '<div class="form-group"><label>Check-in</label><input type="time" id="f-ci" value="' + esc(data.checkin_time || '14:00:00') + '"></div>' +
        '<div class="form-group"><label>Check-out</label><input type="time" id="f-co" value="' + esc(data.checkout_time || '12:00:00') + '"></div>' +
        '<div class="form-group"><label>Trạng thái</label><select id="f-status"><option value="active"' + (data.status === 'active' ? ' selected' : '') + '>Active</option><option value="inactive"' + (data.status === 'inactive' ? ' selected' : '') + '>Inactive</option><option value="maintenance"' + (data.status === 'maintenance' ? ' selected' : '') + '>Maintenance</option></select></div>' +
        '<div class="form-group"><label>Nổi bật</label><select id="f-featured"><option value="1"' + (data.featured ? ' selected' : '') + '>Có</option><option value="0"' + (!data.featured ? ' selected' : '') + '>Không</option></select></div>' +
        '<div class="form-group full"><label>Mô tả ngắn</label><input id="f-short" value="' + esc(data.short_description || '') + '" maxlength="255"></div>' +
        '<div class="form-group full"><label>Mô tả</label><textarea id="f-desc">' + esc(data.description || '') + '</textarea></div>' +
        '<div class="form-group full"><label>Câu chuyện</label><textarea id="f-story">' + esc(data.story || '') + '</textarea></div>' +
        '</div>' +
        '<div class="modal-actions"><button class="btn" id="m-cancel">Hủy</button><button class="btn btn-primary" id="m-save">Lưu</button></div>',
        title
      );
      document.getElementById('m-cancel').addEventListener('click', closeModal);
      document.getElementById('m-save').addEventListener('click', function () {
        var body = {
          code: document.getElementById('f-code').value.trim(),
          name: document.getElementById('f-name').value.trim(),
          district: document.getElementById('f-district').value.trim(),
          ward: document.getElementById('f-ward').value.trim(),
          address: document.getElementById('f-address').value.trim(),
          latitude: document.getElementById('f-lat').value,
          longitude: document.getElementById('f-lng').value,
          checkin_time: document.getElementById('f-ci').value,
          checkout_time: document.getElementById('f-co').value,
          status: document.getElementById('f-status').value,
          featured: document.getElementById('f-featured').value === '1',
          short_description: document.getElementById('f-short').value.trim(),
          description: document.getElementById('f-desc').value.trim(),
          story: document.getElementById('f-story').value.trim()
        };
        if (id) body.id = id;
        API.post('admin-property-save', body).then(function (j) {
          if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchProperties(); } else API.handleError(j);
        });
      });
    }
  }

  /* ═══════════════════════════════════
     UNITS
     ═══════════════════════════════════ */
  var unitPropFilter = 0;
  function loadUnits() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="panel-head"><h3>Căn homestay</h3><div style="display:flex;gap:10px"><select id="u-prop-filter" style="padding:8px 11px;border:1px solid var(--line);border-radius:8px"><option value="0">Tất cả homestay</option></select><button class="btn btn-primary" id="u-new">+ Thêm căn</button></div></div><div class="table-wrap" id="u-table"><div class="state-box">Đang tải…</div></div></div>';
    loadPropOptions();
    document.getElementById('u-prop-filter').addEventListener('change', function () {
      unitPropFilter = Number(this.value);
      fetchUnits();
    });
    document.getElementById('u-new').addEventListener('click', function () { unitForm(null); });
    fetchUnits();
  }

  function loadPropOptions() {
    API.get('admin-properties', { page: 1, limit: 50 }).then(function (j) {
      var sel = document.getElementById('u-prop-filter');
      if (!sel) return;
      var opts = '<option value="0">Tất cả homestay</option>';
      (j.data.items || []).forEach(function (p) { opts += '<option value="' + p.id + '">' + esc(p.name) + '</option>'; });
      sel.innerHTML = opts;
    });
  }

  function fetchUnits() {
    var box = document.getElementById('u-table');
    API.get('admin-units', unitPropFilter ? { property_id: unitPropFilter } : null).then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>ID</th><th>Mã</th><th>Tên</th><th>Loại</th><th>Giá/đêm</th><th>Khách</th><th>Trạng thái</th><th></th></tr></thead><tbody>' +
        items.map(function (u) {
          return '<tr><td>' + u.id + '</td><td>' + esc(u.code) + '</td><td><b>' + esc(u.name) + '</b></td><td>' + esc(u.type) + '</td>' +
            '<td>' + fmtVND(u.base_price) + '</td><td>' + u.max_guests + '</td>' +
            '<td><span class="badge st-' + (u.status || 'inactive') + '">' + esc(u.status) + '</span></td>' +
            '<td style="white-space:nowrap"><button class="btn btn-sm" data-act="edit" data-id="' + u.id + '">Sửa</button> ' +
            '<button class="btn btn-sm" data-act="status" data-id="' + u.id + '" data-status="' + (u.status === 'active' ? 'inactive' : 'active') + '">' + (u.status === 'active' ? 'Ẩn' : 'Hiện') + '</button></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Chưa có căn homestay</div>';
      bindTableActions(box, { edit: function (btn) { unitForm(Number(btn.getAttribute('data-id'))); }, status: function (btn) { setUnitStatus(btn); } });
    });
  }

  function setUnitStatus(btn) {
    API.post('admin-unit-status', { id: Number(btn.getAttribute('data-id')), status: btn.getAttribute('data-status') }).then(function (j) {
      if (j.success) { API.toast('Đã cập nhật', 'success'); fetchUnits(); } else API.handleError(j);
    });
  }

  function unitForm(id) {
    var title = id ? 'Sửa căn homestay' : 'Thêm căn homestay';
    var apply = function (data) { renderForm(data); };
    if (id) {
      API.get('admin-unit-get', { id: id }).then(function (j) { if (j.success) apply(j.data); });
    } else {
      apply({ property_id: unitPropFilter || '', code: '', name: '', type: 'studio', base_price: '', cleaning_fee: 0, max_guests: 2, bedrooms: 1, beds: 1, bathrooms: 1, area_m2: '', short_description: '', description: '', house_rules: '', status: 'active', featured: false, amenity_ids: [], tags: [], images: [] });
    }

    function renderForm(data) {
      openModal(
        '<div class="form-grid">' +
        '<div class="form-group"><label>Homestay</label><select id="f-prop">' + propOptions(Number(data.property_id || 0)) + '</select></div>' +
        '<div class="form-group"><label>Mã căn</label><input id="f-code" value="' + esc(data.code || '') + '" maxlength="20"></div>' +
        '<div class="form-group full"><label>Tên căn</label><input id="f-name" value="' + esc(data.name || '') + '" maxlength="150"></div>' +
        '<div class="form-group"><label>Loại</label><select id="f-type">' + ['studio', 'apartment', 'loft', 'couple', 'family', 'villa', 'house'].map(function (t) { return '<option value="' + t + '"' + (data.type === t ? ' selected' : '') + '>' + t + '</option>'; }).join('') + '</select></div>' +
        '<div class="form-group"><label>Giá / đêm (VND)</label><input type="number" id="f-price" value="' + esc(data.base_price || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Phí vệ sinh</label><input type="number" id="f-cleaning" value="' + esc(data.cleaning_fee || 0) + '" min="0"></div>' +
        '<div class="form-group"><label>Số khách tối đa</label><input type="number" id="f-guests" value="' + esc(data.max_guests || 2) + '" min="1" max="20"></div>' +
        '<div class="form-group"><label>Phòng ngủ</label><input type="number" id="f-br" value="' + esc(data.bedrooms || 1) + '" min="0"></div>' +
        '<div class="form-group"><label>Giường</label><input type="number" id="f-beds" value="' + esc(data.beds || 1) + '" min="0"></div>' +
        '<div class="form-group"><label>Phòng tắm</label><input type="number" id="f-bath" value="' + esc(data.bathrooms || 1) + '" min="0"></div>' +
        '<div class="form-group"><label>Diện tích (m²)</label><input type="number" id="f-area" value="' + esc(data.area_m2 || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Trạng thái</label><select id="f-status"><option value="active"' + (data.status === 'active' ? ' selected' : '') + '>Active</option><option value="inactive"' + (data.status === 'inactive' ? ' selected' : '') + '>Inactive</option><option value="maintenance"' + (data.status === 'maintenance' ? ' selected' : '') + '>Maintenance</option></select></div>' +
        '<div class="form-group"><label>Nổi bật</label><select id="f-featured"><option value="1"' + (data.featured ? ' selected' : '') + '>Có</option><option value="0"' + (!data.featured ? ' selected' : '') + '>Không</option></select></div>' +
        '<div class="form-group full"><label>Mô tả ngắn</label><input id="f-short" value="' + esc(data.short_description || '') + '" maxlength="255"></div>' +
        '<div class="form-group full"><label>Mô tả</label><textarea id="f-desc">' + esc(data.description || '') + '</textarea></div>' +
        '<div class="form-group full"><label>Nội quy</label><textarea id="f-rules">' + esc(data.house_rules || '') + '</textarea></div>' +
        '<div class="form-group full"><label>Ảnh (image_url, mỗi dòng một ảnh, dòng đầu là ảnh bìa)</label><textarea id="f-images">' + esc((data.images || []).map(function (i) { return i.image_url; }).join('\n')) + '</textarea></div>' +
        '</div>' +
        '<div class="modal-actions"><button class="btn" id="m-cancel">Hủy</button><button class="btn btn-primary" id="m-save">Lưu</button></div>',
        title
      );
      document.getElementById('m-cancel').addEventListener('click', closeModal);
      document.getElementById('m-save').addEventListener('click', function () {
        var images = document.getElementById('f-images').value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean)
          .map(function (url, idx) { return { image_url: url, is_cover: idx === 0, display_order: idx }; });
        var body = {
          property_id: Number(document.getElementById('f-prop').value) || 0,
          code: document.getElementById('f-code').value.trim(),
          name: document.getElementById('f-name').value.trim(),
          type: document.getElementById('f-type').value,
          base_price: Number(document.getElementById('f-price').value) || 0,
          cleaning_fee: Number(document.getElementById('f-cleaning').value) || 0,
          max_guests: Number(document.getElementById('f-guests').value) || 2,
          bedrooms: Number(document.getElementById('f-br').value) || 0,
          beds: Number(document.getElementById('f-beds').value) || 0,
          bathrooms: Number(document.getElementById('f-bath').value) || 0,
          area_m2: document.getElementById('f-area').value,
          status: document.getElementById('f-status').value,
          featured: document.getElementById('f-featured').value === '1',
          short_description: document.getElementById('f-short').value.trim(),
          description: document.getElementById('f-desc').value.trim(),
          house_rules: document.getElementById('f-rules').value.trim(),
          images: images,
          amenity_ids: data.amenity_ids || [],
          tags: data.tags || []
        };
        if (id) body.id = id;
        API.post('admin-unit-save', body).then(function (j) {
          if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchUnits(); } else API.handleError(j);
        });
      });
    }
  }

  function propOptions(selected) {
    var opts = '<option value="0">Chọn homestay</option>';
    var data = window.__PROPS__ || [];
    data.forEach(function (p) { opts += '<option value="' + p.id + '"' + (p.id === selected ? ' selected' : '') + '>' + esc(p.name) + '</option>'; });
    return opts;
  }
  // preload properties for unit form
  API.get('admin-properties', { page: 1, limit: 50 }).then(function (j) { window.__PROPS__ = j.data.items || []; });

  /* ═══════════════════════════════════
     BOOKINGS
     ═══════════════════════════════════ */
  function loadBookings() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="filters">' +
      '<input type="search" id="b-search" placeholder="Tìm theo mã / tên khách…">' +
      '<select id="b-status"><option value="">Tất cả trạng thái</option><option value="pending">Chờ xác nhận</option><option value="confirmed">Đã xác nhận</option><option value="checkin">Đang ở</option><option value="checkout">Hoàn thành</option><option value="cancelled">Đã hủy</option></select>' +
      '<select id="b-pay"><option value="">Tất cả thanh toán</option><option value="unpaid">Chưa thanh toán</option><option value="partial">Một phần</option><option value="paid">Đã thanh toán</option><option value="refunded">Đã hoàn tiền</option></select>' +
      '<button class="btn" id="b-go">Lọc</button></div>' +
      '<div class="table-wrap" id="b-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('b-go').addEventListener('click', fetchBookings);
    document.getElementById('b-search').addEventListener('keydown', function (e) { if (e.key === 'Enter') fetchBookings(); });
    fetchBookings();
  }

  function fetchBookings() {
    var box = document.getElementById('b-table');
    API.get('admin-bookings', {
      search: document.getElementById('b-search').value.trim(),
      status: document.getElementById('b-status').value,
      payment_status: document.getElementById('b-pay').value,
      page: 1, limit: 50
    }).then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>Mã</th><th>Khách</th><th>Căn</th><th>Ngày</th><th>Tổng</th><th>Trạng thái</th><th>Thanh toán</th><th></th></tr></thead><tbody>' +
        items.map(function (b) {
          return '<tr><td><b>' + esc(b.booking_reference) + '</b></td><td>' + esc(b.guest_name || b.guest_email) + '</td>' +
            '<td>' + esc(b.unit && b.unit.name || '') + '</td>' +
            '<td>' + fmtDate(b.check_in) + ' → ' + fmtDate(b.check_out) + '</td>' +
            '<td>' + fmtVND(b.total_amount) + '</td>' +
            '<td><span class="badge st-' + b.status + '">' + (STATUS_LABELS[b.status] || b.status) + '</span></td>' +
            '<td class="' + (b.payment_status === 'paid' ? 'pay-paid' : 'pay-unpaid') + '">' + (PAY_LABELS[b.payment_status] || b.payment_status) + '</td>' +
            '<td><button class="btn btn-sm" data-act="view" data-id="' + b.id + '">Chi tiết</button></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Không có đặt phòng</div>';
      bindTableActions(box, { view: function (btn) { viewBooking(Number(btn.getAttribute('data-id'))); } });
    });
  }

  function viewBooking(id) {
    API.get('admin-booking-detail', { id: id }).then(function (j) {
      if (j.success) showBookingDetail(j.data);
    });
  }

  function showBookingDetail(b) {
    var nights = (b.nights || []).map(function (n) { return fmtDate(n.stay_date) + ' ' + fmtVND(n.nightly_rate); }).join('<br>');
    var items = (b.price_items || []).map(function (it) { return esc(it.label) + ': ' + fmtVND(it.amount); }).join('<br>');

    // allowed next statuses per state machine
    var next = {
      pending: [['confirmed', 'Xác nhận'], ['cancelled', 'Hủy']],
      confirmed: [['checkin', 'Check-in'], ['cancelled', 'Hủy']],
      checkin: [['checkout', 'Check-out']]
    }[b.status] || [];

    openModal(
      '<div class="form-grid">' +
      '<div class="form-group"><label>Mã đặt chỗ</label><div><b>' + esc(b.booking_reference) + '</b></div></div>' +
      '<div class="form-group"><label>Trạng thái</label><div><span class="badge st-' + b.status + '">' + (STATUS_LABELS[b.status] || b.status) + '</span></div></div>' +
      '<div class="form-group"><label>Khách</label><div>' + esc(b.guest_name || '') + '<br>' + esc(b.guest_email || '') + '<br>' + esc(b.guest_phone || '') + '</div></div>' +
      '<div class="form-group"><label>Căn</label><div>' + esc(b.unit && b.unit.name || '') + '</div></div>' +
      '<div class="form-group"><label>Ngày</label><div>' + fmtDate(b.check_in) + ' → ' + fmtDate(b.check_out) + ' (' + b.number_of_nights + ' đêm)</div></div>' +
      '<div class="form-group"><label>Khách ở</label><div>' + (b.total_guests || '') + ' người</div></div>' +
      '<div class="form-group"><label>Thanh toán</label><select id="b-pay-update"><option value="unpaid"' + (b.payment_status === 'unpaid' ? ' selected' : '') + '>Chưa thanh toán</option><option value="partial"' + (b.payment_status === 'partial' ? ' selected' : '') + '>Một phần</option><option value="paid"' + (b.payment_status === 'paid' ? ' selected' : '') + '>Đã thanh toán</option><option value="refunded"' + (b.payment_status === 'refunded' ? ' selected' : '') + '>Đã hoàn tiền</option></select></div>' +
      '<div class="form-group"><label>Tổng tiền</label><div><b>' + fmtVND(b.total_amount) + '</b></div></div>' +
      '<div class="form-group"><label>Các đêm</label><div style="font-size:12px">' + nights + '</div></div>' +
      '<div class="form-group"><label>Chi tiết giá</label><div style="font-size:12px">' + items + '</div></div>' +
      (b.special_requests ? '<div class="form-group full"><label>Yêu cầu</label><div>' + esc(b.special_requests) + '</div></div>' : '') +
      '</div>' +
      '<div class="modal-actions" style="flex-wrap:wrap">' +
      '<button class="btn" id="m-pay">Cập nhật thanh toán</button>' +
      next.map(function (n) { return '<button class="btn btn-primary" data-next="' + n[0] + '">' + esc(n[1]) + '</button>'; }).join('') +
      '<button class="btn" id="m-close2">Đóng</button>' +
      '</div>',
      'Chi tiết đặt phòng'
    );
    document.getElementById('m-close2').addEventListener('click', closeModal);
    document.getElementById('m-pay').addEventListener('click', function () {
      API.post('admin-booking-payment', { id: b.id, payment_status: document.getElementById('b-pay-update').value }).then(function (j) {
        if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchBookings(); } else API.handleError(j);
      });
    });
    modalEl.querySelectorAll('[data-next]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var nextStatus = btn.getAttribute('data-next');
        API.post('admin-booking-status', { id: b.id, status: nextStatus }).then(function (j) {
          if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchBookings(); } else API.handleError(j);
        });
      });
    });
  }

  /* ═══════════════════════════════════
     CUSTOMERS
     ═══════════════════════════════════ */
  function loadCustomers() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="filters"><input type="search" id="c-search" placeholder="Tìm tên / email / điện thoại…"><button class="btn" id="c-go">Tìm</button></div><div class="table-wrap" id="c-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('c-go').addEventListener('click', fetchCustomers);
    document.getElementById('c-search').addEventListener('keydown', function (e) { if (e.key === 'Enter') fetchCustomers(); });
    fetchCustomers();
  }

  function fetchCustomers() {
    var box = document.getElementById('c-table');
    API.get('admin-customers', { search: document.getElementById('c-search').value.trim(), page: 1, limit: 50 }).then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>ID</th><th>Tên</th><th>Email</th><th>Điện thoại</th><th>Ngày tham gia</th><th>Đặt phòng</th><th>Tổng chi</th><th>Trạng thái</th></tr></thead><tbody>' +
        items.map(function (c) {
          return '<tr><td>' + c.id + '</td><td><b>' + esc(c.full_name) + '</b></td><td>' + esc(c.email) + '</td><td>' + esc(c.phone || '') + '</td>' +
            '<td>' + fmtDate(c.created_at) + '</td><td>' + (c.booking_count || 0) + '</td><td>' + fmtVND(c.total_spend || 0) + '</td>' +
            '<td><span class="badge st-' + (c.status || 'inactive') + '">' + esc(c.status) + '</span></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Không có khách hàng</div>';
    });
  }

  /* ═══════════════════════════════════
     REVIEWS
     ═══════════════════════════════════ */
  function loadReviews() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="filters"><select id="r-status"><option value="">Tất cả</option><option value="pending">Chờ duyệt</option><option value="approved">Đã duyệt</option><option value="rejected">Từ chối</option></select><button class="btn" id="r-go">Lọc</button></div><div class="table-wrap" id="r-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('r-go').addEventListener('click', fetchReviews);
    fetchReviews();
  }

  function fetchReviews() {
    var box = document.getElementById('r-table');
    API.get('admin-reviews', { status: document.getElementById('r-status').value }).then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>ID</th><th>Khách</th><th>Căn</th><th>Điểm</th><th>Nội dung</th><th>Trạng thái</th><th></th></tr></thead><tbody>' +
        items.map(function (r) {
          return '<tr><td>' + r.id + '</td><td>' + esc(r.customer_name || '') + '</td><td>' + esc(r.unit_name || '') + '</td>' +
            '<td><b>' + (Number(r.overall_rating) || 0).toFixed(1) + '</b></td>' +
            '<td style="max-width:280px"><b>' + esc(r.title || '') + '</b><br><small style="color:var(--muted)">' + esc((r.comment || '').slice(0, 90)) + '</small></td>' +
            '<td><span class="badge st-' + (r.status === 'approved' ? 'active' : r.status === 'rejected' ? 'cancelled' : 'pending') + '">' + esc(r.status) + '</span></td>' +
            '<td style="white-space:nowrap">' +
            (r.status === 'pending' ? '<button class="btn btn-sm btn-olive" data-act="approve" data-id="' + r.id + '">Duyệt</button> <button class="btn btn-sm" data-act="reject" data-id="' + r.id + '">Từ chối</button>' : '') +
            '</td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Không có đánh giá</div>';
      bindTableActions(box, {
        approve: function (btn) { moderate(btn.getAttribute('data-id'), 'approved'); },
        reject: function (btn) { moderate(btn.getAttribute('data-id'), 'rejected'); }
      });
    });
  }

  function moderate(id, status) {
    API.post('admin-review-moderate', { id: Number(id), status: status }).then(function (j) {
      if (j.success) { API.toast(j.message, 'success'); fetchReviews(); } else API.handleError(j);
    });
  }

  /* ═══════════════════════════════════
     PRICING (rate rules)
     ═══════════════════════════════════ */
  function loadPricing() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="panel-head"><h3>Luật giá (rate rules)</h3><button class="btn btn-primary" id="r-new">+ Thêm luật</button></div>' +
      '<p style="color:var(--muted);font-size:12px">Quy tắc: priority cao hơn thắng. Cùng priority → rule cụ thể hơn (có day_of_week / khoảng ngày) thắng. day_of_week: 1=CN … 7=T7. end_date EXCLUSIVE.</p>' +
      '<div class="table-wrap" id="rr-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('r-new').addEventListener('click', function () { rateRuleForm(null); });
    fetchRateRules();
  }

  function fetchRateRules() {
    var box = document.getElementById('rr-table');
    API.get('admin-rate-rules').then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>ID</th><th>Tên</th><th>Căn</th><th>Ngày</th><th>Thứ</th><th>Giá</th><th>Priority</th><th>Active</th><th></th></tr></thead><tbody>' +
        items.map(function (r) {
          return '<tr><td>' + r.id + '</td><td><b>' + esc(r.name) + '</b></td><td>' + esc(r.unit_name || '') + '</td>' +
            '<td>' + (r.start_date ? r.start_date + ' → ' + (r.end_date || '∞') : 'Mọi ngày') + '</td>' +
            '<td>' + (r.day_of_week || '—') + '</td><td>' + fmtVND(r.price) + '</td>' +
            '<td>' + r.priority + '</td><td>' + (r.active ? '✅' : '—') + '</td>' +
            '<td style="white-space:nowrap"><button class="btn btn-sm" data-act="edit" data-id="' + r.id + '">Sửa</button> <button class="btn btn-sm btn-danger" data-act="del" data-id="' + r.id + '">Xóa</button></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Chưa có luật giá — dùng base_price mặc định</div>';
      bindTableActions(box, {
        edit: function (btn) { rateRuleForm(Number(btn.getAttribute('data-id'))); },
        del: function (btn) {
          if (!confirm('Xóa luật giá này?')) return;
          API.post('admin-rate-rule-delete', { id: Number(btn.getAttribute('data-id')) }).then(function (j) {
            if (j.success) { API.toast('Đã xóa', 'success'); fetchRateRules(); } else API.handleError(j);
          });
        }
      });
    });
  }

  function rateRuleForm(id) {
    var units = window.__PROPS_UNITS__ || [];
    var title = id ? 'Sửa luật giá' : 'Thêm luật giá';
    var apply = function (data) { renderForm(data); };
    if (id) {
      API.get('admin-rate-rules').then(function (j) {
        var item = (j.data.items || []).find(function (r) { return r.id === id; });
        if (item) apply(item);
      });
    } else {
      apply({ unit_id: units.length ? units[0].id : 0, name: '', start_date: '', end_date: '', day_of_week: '', price: '', priority: 0, active: true });
    }
    function renderForm(data) {
      openModal(
        '<div class="form-grid">' +
        '<div class="form-group"><label>Căn</label><select id="f-unit">' + units.map(function (u) { return '<option value="' + u.id + '"' + (u.id === data.unit_id ? ' selected' : '') + '>' + esc(u.name) + '</option>'; }).join('') + '</select></div>' +
        '<div class="form-group"><label>Tên</label><input id="f-name" value="' + esc(data.name || '') + '" maxlength="150"></div>' +
        '<div class="form-group"><label>Start date (inclusive)</label><input type="date" id="f-start" value="' + esc(data.start_date || '') + '"></div>' +
        '<div class="form-group"><label>End date (exclusive)</label><input type="date" id="f-end" value="' + esc(data.end_date || '') + '"></div>' +
        '<div class="form-group"><label>Thứ (1=CN…7=T7)</label><input type="number" id="f-dow" value="' + esc(data.day_of_week || '') + '" min="1" max="7"></div>' +
        '<div class="form-group"><label>Giá (VND)</label><input type="number" id="f-price" value="' + esc(data.price || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Priority</label><input type="number" id="f-priority" value="' + esc(data.priority || 0) + '"></div>' +
        '<div class="form-group"><label>Active</label><select id="f-active"><option value="1"' + (data.active ? ' selected' : '') + '>Có</option><option value="0"' + (!data.active ? ' selected' : '') + '>Không</option></select></div>' +
        '</div>' +
        '<div class="modal-actions"><button class="btn" id="m-cancel">Hủy</button><button class="btn btn-primary" id="m-save">Lưu</button></div>',
        title
      );
      document.getElementById('m-cancel').addEventListener('click', closeModal);
      document.getElementById('m-save').addEventListener('click', function () {
        var body = {
          unit_id: Number(document.getElementById('f-unit').value) || 0,
          name: document.getElementById('f-name').value.trim(),
          start_date: document.getElementById('f-start').value,
          end_date: document.getElementById('f-end').value,
          day_of_week: document.getElementById('f-dow').value,
          price: Number(document.getElementById('f-price').value) || 0,
          priority: Number(document.getElementById('f-priority').value) || 0,
          active: document.getElementById('f-active').value === '1'
        };
        if (id) body.id = id;
        API.post('admin-rate-rule-save', body).then(function (j) {
          if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchRateRules(); } else API.handleError(j);
        });
      });
    }
  }

  // preload units for rate rule form
  API.get('admin-units').then(function (j) { window.__PROPS_UNITS__ = j.data.items || []; });

  /* ═══════════════════════════════════
     PROMOTIONS
     ═══════════════════════════════════ */
  function loadPromotions() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="panel-head"><h3>Mã ưu đãi</h3><button class="btn btn-primary" id="pr-new">+ Thêm mã</button></div><div class="table-wrap" id="pr-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('pr-new').addEventListener('click', function () { promoForm(null); });
    fetchPromotions();
  }

  function fetchPromotions() {
    var box = document.getElementById('pr-table');
    API.get('admin-promotions').then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>Mã</th><th>Tên</th><th>Loại</th><th>Giá trị</th><th>Hiệu lực</th><th>Giới hạn</th><th>Active</th><th></th></tr></thead><tbody>' +
        items.map(function (p) {
          return '<tr><td><b>' + esc(p.code) + '</b></td><td>' + esc(p.name) + '</td>' +
            '<td>' + esc(p.discount_type) + '</td><td>' + (p.discount_type === 'percentage' ? p.discount_value + '%' : fmtVND(p.discount_value)) + '</td>' +
            '<td>' + (p.valid_from || '') + ' → ' + (p.valid_until || '') + '</td>' +
            '<td>' + (p.usage_limit || '∞') + ' (mỗi khách: ' + (p.usage_limit_per_customer || '∞') + ')</td>' +
            '<td>' + (p.active ? '✅' : '—') + '</td>' +
            '<td><button class="btn btn-sm" data-act="edit" data-id="' + p.id + '">Sửa</button></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Chưa có mã ưu đãi</div>';
      bindTableActions(box, { edit: function (btn) { promoForm(Number(btn.getAttribute('data-id'))); } });
    });
  }

  function promoForm(id) {
    var title = id ? 'Sửa mã ưu đãi' : 'Thêm mã ưu đãi';
    var apply = function (data) { renderForm(data); };
    if (id) {
      API.get('admin-promotions').then(function (j) {
        var item = (j.data.items || []).find(function (p) { return p.id === id; });
        if (item) apply(item);
      });
    } else {
      apply({ code: '', name: '', description: '', discount_type: 'percentage', discount_value: '', minimum_booking_value: '', maximum_discount: '', valid_from: new Date().toISOString().split('T')[0], valid_until: '', usage_limit: '', usage_limit_per_customer: '', active: true });
    }
    function renderForm(data) {
      openModal(
        '<div class="form-grid">' +
        '<div class="form-group"><label>Mã</label><input id="f-code" value="' + esc(data.code || '') + '" maxlength="40"></div>' +
        '<div class="form-group"><label>Tên</label><input id="f-name" value="' + esc(data.name || '') + '" maxlength="150"></div>' +
        '<div class="form-group"><label>Loại</label><select id="f-type"><option value="percentage"' + (data.discount_type === 'percentage' ? ' selected' : '') + '>Phần trăm</option><option value="fixed_amount"' + (data.discount_type === 'fixed_amount' ? ' selected' : '') + '>Số tiền</option></select></div>' +
        '<div class="form-group"><label>Giá trị</label><input type="number" id="f-value" value="' + esc(data.discount_value || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Giá trị tối thiểu đơn (VND)</label><input type="number" id="f-min" value="' + esc(data.minimum_booking_value || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Giảm tối đa (VND)</label><input type="number" id="f-max" value="' + esc(data.maximum_discount || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Valid from</label><input type="date" id="f-from" value="' + esc(data.valid_from || '') + '"></div>' +
        '<div class="form-group"><label>Valid until</label><input type="date" id="f-until" value="' + esc(data.valid_until || '') + '"></div>' +
        '<div class="form-group"><label>Giới hạn tổng lượt</label><input type="number" id="f-limit" value="' + esc(data.usage_limit || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Giới hạn mỗi khách</label><input type="number" id="f-per" value="' + esc(data.usage_limit_per_customer || '') + '" min="0"></div>' +
        '<div class="form-group"><label>Active</label><select id="f-active"><option value="1"' + (data.active ? ' selected' : '') + '>Có</option><option value="0"' + (!data.active ? ' selected' : '') + '>Không</option></select></div>' +
        '<div class="form-group full"><label>Mô tả</label><input id="f-desc" value="' + esc(data.description || '') + '" maxlength="500"></div>' +
        '</div>' +
        '<div class="modal-actions"><button class="btn" id="m-cancel">Hủy</button><button class="btn btn-primary" id="m-save">Lưu</button></div>',
        title
      );
      document.getElementById('m-cancel').addEventListener('click', closeModal);
      document.getElementById('m-save').addEventListener('click', function () {
        var body = {
          code: document.getElementById('f-code').value.trim(),
          name: document.getElementById('f-name').value.trim(),
          description: document.getElementById('f-desc').value.trim(),
          discount_type: document.getElementById('f-type').value,
          discount_value: Number(document.getElementById('f-value').value) || 0,
          minimum_booking_value: document.getElementById('f-min').value,
          maximum_discount: document.getElementById('f-max').value,
          valid_from: document.getElementById('f-from').value,
          valid_until: document.getElementById('f-until').value,
          usage_limit: document.getElementById('f-limit').value,
          usage_limit_per_customer: document.getElementById('f-per').value,
          active: document.getElementById('f-active').value === '1'
        };
        if (id) body.id = id;
        API.post('admin-promotion-save', body).then(function (j) {
          if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchPromotions(); } else API.handleError(j);
        });
      });
    }
  }

  /* ═══════════════════════════════════
     REVENUE
     ═══════════════════════════════════ */
  function loadRevenue() {
    var box = contentBox();
    box.innerHTML = '<div class="state-box"><div class="skeleton" style="height:40px;max-width:300px;margin:0 auto"></div></div>';
    API.get('revenue').then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var d = j.data;
      var max = Math.max.apply(null, d.monthly.map(function (m) { return m.revenue; })) || 1;
      var html = '<div class="kpi-grid">' +
        '<div class="kpi"><div class="label">Tổng doanh thu (đã thanh toán)</div><div class="value">' + fmtVND(d.paid_total) + '</div><div class="sub">' + d.paid_bookings + ' đơn đã thanh toán</div></div>' +
        '</div>';
      html += '<div class="panel"><div class="panel-head"><h3>Doanh thu 12 tháng</h3></div><div class="bars">' +
        d.monthly.map(function (m) {
          var h = Math.max(3, Math.round(m.revenue / max * 100));
          return '<div class="bar-col"><div class="bar-val">' + (m.revenue ? (Math.round(m.revenue / 100000) / 10) + 'tr' : '0') + '</div><div class="bar" style="height:' + h + '%"></div><div class="bar-label">' + esc(m.label) + '</div></div>';
        }).join('') + '</div></div>';
      html += '<div class="panel"><div class="panel-head"><h3>Chi tiết tháng</h3></div><div class="table-wrap"><table><thead><tr><th>Tháng</th><th>Doanh thu</th></tr></thead><tbody>' +
        d.monthly.slice().reverse().map(function (m) {
          return '<tr><td>' + esc(m.label) + '</td><td>' + fmtVND(m.revenue) + '</td></tr>';
        }).join('') + '</tbody></table></div></div>';
      box.innerHTML = html;
    });
  }

  /* ═══════════════════════════════════
     CONTACTS
     ═══════════════════════════════════ */
  function loadContacts() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="filters"><select id="ct-status"><option value="">Tất cả</option><option value="new">Mới</option><option value="read">Đã đọc</option><option value="replied">Đã trả lời</option><option value="closed">Đã đóng</option></select><button class="btn" id="ct-go">Lọc</button></div><div class="table-wrap" id="ct-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('ct-go').addEventListener('click', fetchContacts);
    fetchContacts();
  }

  function fetchContacts() {
    var box = document.getElementById('ct-table');
    API.get('admin-contacts', { status: document.getElementById('ct-status').value }).then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data || [];
      box.innerHTML = items.length ? '<table><thead><tr><th>ID</th><th>Tên</th><th>Email</th><th>Điện thoại</th><th>Tiêu đề</th><th>Nội dung</th><th>Trạng thái</th><th></th></tr></thead><tbody>' +
        items.map(function (c) {
          return '<tr><td>' + c.id + '</td><td><b>' + esc(c.full_name) + '</b></td><td>' + esc(c.email) + '</td><td>' + esc(c.phone || '') + '</td>' +
            '<td>' + esc(c.subject) + '</td><td style="max-width:240px"><small style="color:var(--muted)">' + esc((c.message || '').slice(0, 80)) + '</small></td>' +
            '<td><span class="badge st-' + c.status + '">' + esc(c.status) + '</span></td>' +
            '<td><select data-id="' + c.id + '" style="padding:5px;border:1px solid var(--line);border-radius:6px">' +
            ['new', 'read', 'replied', 'closed'].map(function (s) { return '<option value="' + s + '"' + (c.status === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') +
            '</select></td></tr>';
        }).join('') + '</tbody></table>'
        : '<div class="state-box">Không có tin nhắn</div>';
      box.querySelectorAll('select[data-id]').forEach(function (sel) {
        sel.addEventListener('change', function () {
          API.post('admin-contact-status', { id: Number(sel.getAttribute('data-id')), status: sel.value }).then(function (j) {
            if (j.success) API.toast('Đã cập nhật', 'success'); else API.handleError(j);
          });
        });
      });
    });
  }


  /* ═══════════════════════════════════
     INTERNAL ACCOUNTS / AUDIT / SETTINGS
     ═══════════════════════════════════ */
  function loadPersonnel() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="panel-head"><div><h3>Nhân sự nội bộ</h3><div class="form-hint">Chỉ Super Admin có quyền tạo/sửa role nội bộ.</div></div><button class="btn btn-primary" id="ia-new">+ Tạo tài khoản</button></div><div class="table-wrap" id="ia-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('ia-new').addEventListener('click', function () { internalAccountForm(null); });
    fetchPersonnel();
  }

  function fetchPersonnel() {
    var table = document.getElementById('ia-table');
    API.get('internal-accounts').then(function (j) {
      if (!j.success) { table.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      table.innerHTML = '<table><thead><tr><th>Tài khoản</th><th>Họ tên</th><th>Role</th><th>Trạng thái</th><th>Đăng nhập cuối</th><th></th></tr></thead><tbody>' +
        items.map(function (a) {
          return '<tr><td><b>' + esc(a.username) + '</b><br><small>' + esc(a.email) + '</small></td><td>' + esc(a.full_name) + '</td><td><span class="role-pill">' + esc(a.role) + '</span></td><td><span class="badge st-' + esc(a.status) + '">' + esc(a.status) + '</span></td><td>' + esc(a.last_login_at ? fmtDate(a.last_login_at) : '—') + '</td><td><button class="btn btn-sm" data-edit-account="' + a.id + '">Sửa</button></td></tr>';
        }).join('') + '</tbody></table>';
      table.querySelectorAll('[data-edit-account]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = Number(btn.getAttribute('data-edit-account'));
          internalAccountForm(items.find(function (x) { return x.id === id; }) || null);
        });
      });
    });
  }

  function internalAccountForm(data) {
    data = data || { role: 'staff', status: 'active' };
    var isEdit = !!data.id;
    openModal(
      '<div class="form-grid">' +
      '<div class="form-group"><label>Username</label><input id="ia-user" maxlength="50" value="' + esc(data.username || '') + '"></div>' +
      '<div class="form-group"><label>Email</label><input id="ia-email" type="email" maxlength="100" value="' + esc(data.email || '') + '"></div>' +
      '<div class="form-group full"><label>Họ tên</label><input id="ia-name" maxlength="100" value="' + esc(data.full_name || '') + '"></div>' +
      '<div class="form-group"><label>Role</label><select id="ia-role"><option value="super_admin"' + (data.role === 'super_admin' ? ' selected' : '') + '>Super Admin</option><option value="manager"' + (data.role === 'manager' ? ' selected' : '') + '>Manager</option><option value="staff"' + (data.role === 'staff' ? ' selected' : '') + '>Staff / Lễ tân</option></select></div>' +
      '<div class="form-group"><label>Trạng thái</label><select id="ia-status"><option value="active"' + (data.status === 'active' ? ' selected' : '') + '>active</option><option value="inactive"' + (data.status === 'inactive' ? ' selected' : '') + '>inactive</option></select></div>' +
      '<div class="form-group full"><label>' + (isEdit ? 'Mật khẩu mới (để trống nếu giữ nguyên)' : 'Mật khẩu') + '</label><input id="ia-pass" type="password" autocomplete="new-password" minlength="8"><div class="form-hint">Tối thiểu 8 ký tự; backend lưu bằng password_hash(), không lưu plaintext.</div></div>' +
      '</div><div class="modal-actions"><button class="btn" id="ia-cancel">Hủy</button><button class="btn btn-primary" id="ia-save">Lưu</button></div>',
      isEdit ? 'Sửa tài khoản nội bộ' : 'Tạo tài khoản nội bộ'
    );
    document.getElementById('ia-cancel').addEventListener('click', closeModal);
    document.getElementById('ia-save').addEventListener('click', function () {
      var btn = this; btn.disabled = true;
      var body = { username: document.getElementById('ia-user').value.trim(), email: document.getElementById('ia-email').value.trim(), full_name: document.getElementById('ia-name').value.trim(), role: document.getElementById('ia-role').value, status: document.getElementById('ia-status').value, password: document.getElementById('ia-pass').value };
      if (isEdit) body.id = data.id;
      API.post('internal-account-save', body).then(function (j) {
        if (j.success) { API.toast(j.message, 'success'); closeModal(); fetchPersonnel(); } else API.handleError(j);
      }).catch(function () { API.toast('Không kết nối được máy chủ', 'error'); }).finally(function () { btn.disabled = false; });
    });
  }

  function loadAudit() {
    var box = contentBox();
    box.innerHTML = '<div class="panel"><div class="panel-head"><div><h3>Audit Logs</h3><div class="form-hint">Nhật ký các thao tác quản trị quan trọng.</div></div><button class="btn" id="audit-refresh">Làm mới</button></div><div class="table-wrap" id="audit-table"><div class="state-box">Đang tải…</div></div></div>';
    document.getElementById('audit-refresh').addEventListener('click', fetchAudit);
    fetchAudit();
  }

  function fetchAudit() {
    var table = document.getElementById('audit-table');
    API.get('audit-logs', { page: 1, limit: 100 }).then(function (j) {
      if (!j.success) { table.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var items = j.data.items || [];
      table.innerHTML = items.length ? '<table><thead><tr><th>Thời gian</th><th>Người thao tác</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead><tbody>' + items.map(function (a) {
        var actor = a.actor ? ((a.actor.full_name || a.actor.username) + ' · ' + a.actor.role) : ('Admin #' + (a.admin_id || '—'));
        return '<tr><td>' + esc(a.created_at ? new Date(a.created_at).toLocaleString('vi-VN') : '') + '</td><td>' + esc(actor) + '</td><td><b>' + esc(a.action) + '</b></td><td>' + esc(a.entity_type) + ' #' + esc(a.entity_id || '') + '</td><td>' + esc(a.ip_address || '') + '</td></tr>';
      }).join('') + '</tbody></table>' : '<div class="state-box">Chưa có audit log</div>';
    });
  }

  function loadSettings() {
    var box = contentBox();
    box.innerHTML = '<div class="state-box">Đang tải…</div>';
    API.get('system-settings').then(function (j) {
      if (!j.success) { box.innerHTML = '<div class="state-box">' + esc(j.message) + '</div>'; return; }
      var d = j.data || {};
      box.innerHTML = '<div class="panel"><div class="panel-head"><h3>Cài đặt & an toàn production</h3><span class="role-pill">Super Admin only</span></div>' +
        '<div class="security-note"><b>Supabase secret không hiển thị trong portal.</b><br><span class="form-hint">Vị trí cấu hình: ' + esc(d.supabase_secret_location || '') + '</span></div>' +
        '<div style="margin-top:18px"><h4>Session</h4><p>HttpOnly: <b>ON</b> · SameSite: <b>Lax</b> · Secure cookie khi HTTPS: <b>ON</b></p></div>' +
        '<div><h4>Trước production</h4><ul>' + (d.production_notes || []).map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul></div></div>';
    });
  }

  /* ═══════════════════════════════════
     HELPERS
     ═══════════════════════════════════ */
  function bindTableActions(box, actions) {
    box.querySelectorAll('button[data-act]').forEach(function (btn) {
      var act = btn.getAttribute('data-act');
      if (actions[act]) {
        btn.addEventListener('click', function () { actions[act](btn); });
      }
    });
  }

  /* ═══════════════════════════════════
     BOOT
     ═══════════════════════════════════ */
  guard();
})();
