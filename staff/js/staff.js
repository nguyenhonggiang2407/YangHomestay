(function () {
  'use strict';
  var API = window.AdminAPI;
  var box = document.getElementById('content');
  var modalEl = null;
  var STATUS = { pending:'Chờ xác nhận', confirmed:'Đã xác nhận', checkin:'Đang ở', checkout:'Đã trả phòng', cancelled:'Đã hủy' };
  var sections = { today:['Ca làm hôm nay',loadToday], bookings:['Tìm booking',loadBookings], calendar:['Lịch phòng',loadCalendar], rooms:['Tình trạng phòng',loadRooms], customers:['Tìm khách hàng',loadCustomers] };

  function esc(v) { var d=document.createElement('div'); d.textContent=v==null?'':String(v); return d.innerHTML; }
  function fmtDate(v) { if(!v)return ''; var d=new Date(v.length===10?v+'T00:00:00':v); return d.toLocaleDateString('vi-VN'); }
  function state(msg) { box.innerHTML='<div class="state-box">'+esc(msg)+'</div>'; }
  function badge(s) { return '<span class="badge st-'+esc(s)+'">'+esc(STATUS[s]||s)+'</span>'; }

  function guard() {
    API.get('check-auth',{portal:'staff'}).then(function(j){
      if(!j.authenticated){ location.href='login.html'; return; } if(j.authorized===false&&j.redirect_portal){location.href='../'+j.redirect_portal+'/index.html';return;}
      var u=j.data||{};
      document.getElementById('admin-info').textContent=(u.full_name||u.username||'Nhân viên')+' · '+(u.role||'');
      document.getElementById('admin-user').innerHTML='<span class="role-pill">Lễ tân</span><span>'+esc(u.full_name||u.username||'')+'</span>';
      loadToday();
    }).catch(function(){ location.href='login.html'; });
  }
  document.getElementById('btn-logout').addEventListener('click',function(e){e.preventDefault();API.post('logout').finally(function(){location.href='login.html';});});
  document.getElementById('menu-btn').addEventListener('click',function(){document.getElementById('sidebar').classList.toggle('open');});
  document.getElementById('side-nav').addEventListener('click',function(e){var a=e.target.closest('[data-sec]');if(!a)return;e.preventDefault();var sec=a.getAttribute('data-sec');document.querySelectorAll('#side-nav [data-sec]').forEach(function(x){x.classList.toggle('active',x===a);});document.getElementById('page-title').textContent=sections[sec][0];sections[sec][1]();});

  function bookingCard(b, actionLabel) {
    var unit=b.unit&&b.unit.name?b.unit.name:'Unit #'+b.unit_id;
    return '<div class="ops-card"><div><b>'+esc(b.guest_name||'Khách')+'</b><div class="meta">'+esc(b.booking_reference)+' · '+esc(unit)+' · '+fmtDate(b.check_in)+' → '+fmtDate(b.check_out)+'</div><div class="meta">'+esc(b.guest_phone||'')+' · '+(b.total_guests||0)+' khách</div></div><div style="text-align:right">'+badge(b.status)+'<br><button class="btn btn-sm" data-view="'+b.id+'" style="margin-top:8px">'+esc(actionLabel||'Chi tiết')+'</button></div></div>';
  }

  function loadToday() {
    state('Đang tải ca làm hôm nay…');
    API.get('operations-today').then(function(j){
      if(!j.success){state(j.message);return;} var d=j.data;
      box.innerHTML='<div class="hero-strip"><div><div class="portal-kicker">Front Desk Operations</div><h3>Ưu tiên khách đến và khách trả phòng hôm nay</h3><p>'+esc(d.date)+' · thao tác trạng thái booking được kiểm tra lại ở backend.</p></div><span class="role-pill">STAFF</span></div>'+
      '<div class="kpi-grid"><div class="kpi"><div class="label">Khách đến hôm nay</div><div class="value">'+d.arrivals.length+'</div></div><div class="kpi"><div class="label">Khách trả hôm nay</div><div class="value">'+d.departures.length+'</div></div><div class="kpi"><div class="label">Booking chờ</div><div class="value">'+d.pending_bookings+'</div></div><div class="kpi"><div class="label">Phòng đang có khách</div><div class="value">'+d.occupied_units+'</div></div><div class="kpi"><div class="label">Phòng trống ước tính</div><div class="value">'+d.available_units+'</div><div class="sub">/'+d.active_units+' phòng active</div></div></div>'+
      '<div class="ops-columns"><div class="panel"><div class="panel-head"><h3>Khách đến hôm nay</h3></div><div class="ops-list">'+(d.arrivals.length?d.arrivals.map(function(b){return bookingCard(b,b.status==='confirmed'?'Nhận phòng':'Chi tiết');}).join(''):'<div class="state-box">Không có khách đến hôm nay</div>')+'</div></div><div class="panel"><div class="panel-head"><h3>Khách trả hôm nay</h3></div><div class="ops-list">'+(d.departures.length?d.departures.map(function(b){return bookingCard(b,b.status==='checkin'?'Trả phòng':'Chi tiết');}).join(''):'<div class="state-box">Không có khách trả hôm nay</div>')+'</div></div></div>';
      bindViews();
    }).catch(function(){state('Không tải được dữ liệu.');});
  }

  function loadBookings() {
    box.innerHTML='<div class="panel"><div class="filters"><input type="search" id="q" placeholder="Mã booking, tên, email, SĐT"><select id="st"><option value="">Tất cả trạng thái</option><option value="pending">Chờ xác nhận</option><option value="confirmed">Đã xác nhận</option><option value="checkin">Đang ở</option><option value="checkout">Đã trả</option><option value="cancelled">Đã hủy</option></select><button class="btn btn-primary" id="go">Tìm booking</button></div><div class="table-wrap" id="results"></div></div>';
    document.getElementById('go').addEventListener('click',fetchBookings);document.getElementById('q').addEventListener('keydown',function(e){if(e.key==='Enter')fetchBookings();});fetchBookings();
  }
  function fetchBookings(){var out=document.getElementById('results');out.innerHTML='<div class="state-box">Đang tìm…</div>';API.get('admin-bookings',{search:document.getElementById('q').value.trim(),status:document.getElementById('st').value,limit:50}).then(function(j){if(!j.success){out.innerHTML='<div class="state-box">'+esc(j.message)+'</div>';return;}var items=j.data.items||[];out.innerHTML=items.length?'<table><thead><tr><th>Mã</th><th>Khách</th><th>Phòng</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead><tbody>'+items.map(function(b){return '<tr><td><b>'+esc(b.booking_reference)+'</b></td><td>'+esc(b.guest_name)+'<br><small>'+esc(b.guest_phone||'')+'</small></td><td>'+esc(b.unit&&b.unit.name||('#'+b.unit_id))+'</td><td>'+fmtDate(b.check_in)+' → '+fmtDate(b.check_out)+'</td><td>'+badge(b.status)+'</td><td><button class="btn btn-sm" data-view="'+b.id+'">Chi tiết</button></td></tr>';}).join('')+'</tbody></table>':'<div class="state-box">Không tìm thấy booking</div>';bindViews();});}

  function bindViews(){document.querySelectorAll('[data-view]').forEach(function(btn){btn.addEventListener('click',function(){openBooking(Number(btn.getAttribute('data-view')));});});}
  function openBooking(id){API.get('admin-booking-detail',{id:id}).then(function(j){if(!j.success){API.handleError(j);return;}showBooking(j.data);});}
  function showBooking(b){var next={pending:[['confirmed','Xác nhận'],['cancelled','Hủy']],confirmed:[['checkin','Nhận phòng'],['cancelled','Hủy']],checkin:[['checkout','Trả phòng']]}[b.status]||[];openModal('<div class="form-grid"><div class="form-group"><label>Mã booking</label><b>'+esc(b.booking_reference)+'</b></div><div class="form-group"><label>Trạng thái</label>'+badge(b.status)+'</div><div class="form-group"><label>Khách</label>'+esc(b.guest_name||'')+'<br>'+esc(b.guest_phone||'')+'<br>'+esc(b.guest_email||'')+'</div><div class="form-group"><label>Phòng</label>'+esc(b.unit&&b.unit.name||'')+'</div><div class="form-group full"><label>Lưu trú</label>'+fmtDate(b.check_in)+' → '+fmtDate(b.check_out)+' · '+(b.total_guests||0)+' khách</div></div><div class="modal-actions" style="flex-wrap:wrap">'+next.map(function(n){return '<button class="btn btn-primary" data-next="'+n[0]+'">'+n[1]+'</button>';}).join('')+'<button class="btn" id="close">Đóng</button></div>','Chi tiết booking');document.getElementById('close').addEventListener('click',closeModal);modalEl.querySelectorAll('[data-next]').forEach(function(btn){btn.addEventListener('click',function(){btn.disabled=true;API.post('admin-booking-status',{id:b.id,status:btn.getAttribute('data-next')}).then(function(j){if(j.success){API.toast(j.message,'success');closeModal();loadToday();}else API.handleError(j);}).finally(function(){btn.disabled=false;});});});}

  function loadCalendar(){var now=new Date(),end=new Date();end.setDate(end.getDate()+14);var iso=function(d){return d.toISOString().slice(0,10);};state('Đang tải lịch phòng…');API.get('calendar',{start:iso(now),end:iso(end)}).then(function(j){if(!j.success){state(j.message);return;}var d=j.data;box.innerHTML='<div class="panel"><div class="panel-head"><h3>Lịch phòng 14 ngày</h3><span class="role-pill">'+esc(d.start)+' → '+esc(d.end)+'</span></div><div class="table-wrap"><table><thead><tr><th>Phòng</th><th>Booking đang hoạt động</th><th>Khoảng ngày</th><th>Trạng thái</th></tr></thead><tbody>'+d.units.map(function(u){var bs=d.bookings.filter(function(b){return b.unit_id===u.id;});if(!bs.length)return '<tr><td><b>'+esc(u.name)+'</b></td><td colspan="3"><span class="badge st-active">Trống trong khoảng xem</span></td></tr>';return bs.map(function(b,i){return '<tr><td>'+(i?'':'<b>'+esc(u.name)+'</b>')+'</td><td>'+esc(b.booking_reference)+' · '+esc(b.guest_name||'')+'</td><td>'+fmtDate(b.check_in)+' → '+fmtDate(b.check_out)+'</td><td>'+badge(b.status)+'</td></tr>';}).join('');}).join('')+'</tbody></table></div></div>';});}

  function loadRooms(){state('Đang tải tình trạng phòng…');API.get('admin-units',{limit:100}).then(function(j){if(!j.success){state(j.message);return;}var items=j.data.items||[];box.innerHTML='<div class="panel"><div class="panel-head"><h3>Danh sách phòng / unit</h3><span class="role-pill">Chỉ xem</span></div><div class="table-wrap"><table><thead><tr><th>Mã</th><th>Tên</th><th>Loại</th><th>Sức chứa</th><th>Trạng thái</th></tr></thead><tbody>'+items.map(function(u){return '<tr><td>'+esc(u.code||u.id)+'</td><td><b>'+esc(u.name)+'</b></td><td>'+esc(u.type||'')+'</td><td>'+esc(u.max_guests||'')+'</td><td><span class="badge st-'+esc(u.status)+'">'+esc(u.status)+'</span></td></tr>';}).join('')+'</tbody></table></div></div>';});}

  function loadCustomers(){box.innerHTML='<div class="panel"><div class="filters"><input type="search" id="cq" placeholder="Tên, email, SĐT"><button class="btn btn-primary" id="cgo">Tìm khách</button></div><div class="table-wrap" id="customers"></div></div>';document.getElementById('cgo').addEventListener('click',fetchCustomers);document.getElementById('cq').addEventListener('keydown',function(e){if(e.key==='Enter')fetchCustomers();});fetchCustomers();}
  function fetchCustomers(){var out=document.getElementById('customers');out.innerHTML='<div class="state-box">Đang tải…</div>';API.get('admin-customers',{search:document.getElementById('cq').value.trim(),limit:50}).then(function(j){if(!j.success){out.innerHTML='<div class="state-box">'+esc(j.message)+'</div>';return;}var items=j.data.items||[];out.innerHTML=items.length?'<table><thead><tr><th>Khách</th><th>Liên hệ</th><th>Số booking</th><th>Trạng thái</th></tr></thead><tbody>'+items.map(function(c){return '<tr><td><b>'+esc(c.full_name)+'</b></td><td>'+esc(c.phone||'')+'<br><small>'+esc(c.email||'')+'</small></td><td>'+esc(c.booking_count||0)+'</td><td><span class="badge st-'+esc(c.status)+'">'+esc(c.status)+'</span></td></tr>';}).join('')+'</tbody></table>':'<div class="state-box">Không tìm thấy khách hàng</div>';});}

  function openModal(html,title){closeModal();modalEl=document.createElement('div');modalEl.className='modal-backdrop';modalEl.innerHTML='<div class="modal"><button class="modal-close" aria-label="Đóng">×</button><h3>'+esc(title)+'</h3>'+html+'</div>';document.body.appendChild(modalEl);modalEl.querySelector('.modal-close').addEventListener('click',closeModal);modalEl.addEventListener('click',function(e){if(e.target===modalEl)closeModal();});}
  function closeModal(){if(modalEl){modalEl.remove();modalEl=null;}}
  guard();
})();
