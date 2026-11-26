const byId = (id) => document.getElementById(id);
const dateFormat = new Intl.DateTimeFormat("vi-VN", {
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
  timeZone: "UTC",
});
const dayFormat = new Intl.DateTimeFormat("vi-VN", { weekday: "short", timeZone: "UTC" });
const todayFormat = new Intl.DateTimeFormat("sv-SE", { timeZone: "Asia/Ho_Chi_Minh" });
const bookingStatuses = new Map([
  ["pending", "Chờ xác nhận"],
  ["confirmed", "Đã xác nhận"],
  ["checkin", "Đã nhận phòng"],
  ["checkout", "Đã trả phòng"],
  ["cancelled", "Đã hủy"],
]);
const transitions = new Map([
  ["pending", { status: "confirmed", label: "Xác nhận đơn" }],
  ["confirmed", { status: "checkin", label: "Nhận phòng", dateField: "check_in" }],
  ["checkin", { status: "checkout", label: "Trả phòng", dateField: "check_out" }],
]);
const state = { session: null, calendar: null, loadingCalendar: null, busy: false };

function shiftDate(date, days) {
  const time = new Date(date + "T00:00:00Z");
  time.setUTCDate(time.getUTCDate() + days);
  return time.toISOString().slice(0, 10);
}

function formatDate(date) {
  return dateFormat.format(new Date(date + "T00:00:00Z"));
}

function element(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}

async function request(path, { body, signal } = {}) {
  const options = { cache: "no-store", headers: { Accept: "application/json" }, signal };
  if (body !== undefined) {
    options.method = "POST";
    options.headers["Content-Type"] = "application/json";
    options.headers["X-CSRF-Token"] = state.session.csrf;
    options.body = JSON.stringify(body);
  }
  const response = await fetch("/api/reception" + path, options);
  const payload = response.status === 204 ? {} : await response.json().catch(() => ({}));
  if (!response.ok) {
    throw Object.assign(new Error(payload.message || "Không thể thực hiện yêu cầu."), {
      status: response.status,
    });
  }
  return payload;
}

function notice(message, error = false) {
  const node = byId("notice");
  node.hidden = !message;
  node.dataset.tone = error ? "error" : "success";
  node.textContent = message;
}

function applySession(session) {
  state.session = session;
  const enabled = session.enabled !== false;
  const signedIn = enabled && session.authenticated;
  byId("sign-in").hidden = !enabled || signedIn;
  byId("dashboard").hidden = !signedIn;
  byId("account").hidden = !signedIn;
  byId("connection").hidden = enabled;
  byId("account-name").textContent = signedIn ? session.name || "" : "";
  byId("block-form").hidden = !session.can_manage_blocks;
  byId("retry").hidden = true;
  if (!enabled) byId("connection-status").textContent = "Cổng lễ tân đang tắt.";
  if (!signedIn) {
    state.loadingCalendar?.abort();
    state.loadingCalendar = null;
    state.calendar = null;
    byId("calendar").setAttribute("aria-busy", "false");
    byId("calendar-unit").value = "";
    byId("block-form").reset();
    renderCalendar();
  }
}

async function start() {
  byId("retry").hidden = true;
  byId("connection-status").textContent = "Đang kiểm tra phiên làm việc…";
  try {
    applySession(await request("/session"));
    if (state.session.authenticated && state.session.enabled !== false) await loadCalendar();
  } catch {
    byId("connection-status").textContent = "Không thể kiểm tra phiên làm việc.";
    byId("retry").hidden = false;
  }
}

function fillUnitOptions(units) {
  const filter = byId("calendar-unit");
  const unit = byId("block-unit");
  const savedFilter = filter.value;
  const savedUnit = unit.value;
  filter.replaceChildren(new Option("Tất cả căn", ""));
  unit.replaceChildren(new Option("Chọn căn", ""));
  for (const item of units) {
    const name = item.district ? item.name + " · " + item.district : item.name;
    filter.add(new Option(name, item.id));
    unit.add(new Option(name, item.id));
  }
  filter.value = units.some((item) => String(item.id) === savedFilter) ? savedFilter : "";
  unit.value = units.some((item) => String(item.id) === savedUnit) ? savedUnit : filter.value;
}

function setBlockDates(today, proposedStart) {
  const start = byId("block-start");
  const end = byId("block-end");
  start.min = today;
  if (!start.value) start.value = proposedStart < today ? today : proposedStart;
  end.min = shiftDate(start.value || today, 1);
  if (!end.value) end.value = end.min;
}

async function loadCalendar() {
  const from = byId("calendar-from").value;
  state.loadingCalendar?.abort();
  const loading = new AbortController();
  state.loadingCalendar = loading;
  byId("calendar").setAttribute("aria-busy", "true");
  setCalendarMessage("Đang tải lịch phòng…");
  updateButtons();
  try {
    const calendar = await request("/calendar?from=" + encodeURIComponent(from), {
      signal: loading.signal,
    });
    if (state.loadingCalendar !== loading) return;
    state.calendar = calendar;
    fillUnitOptions(calendar.units);
    setBlockDates(calendar.today || todayFormat.format(new Date()), calendar.from);
    renderCalendar();
  } catch (error) {
    if (state.loadingCalendar !== loading) return;
    if (error.status === 401) await recoverSession();
    state.calendar = null;
    renderCalendar(error.status ? error.message : "Không thể tải lịch phòng.");
  } finally {
    if (state.loadingCalendar === loading) {
      state.loadingCalendar = null;
      byId("calendar").setAttribute("aria-busy", "false");
      updateButtons();
    }
  }
}

function setCalendarMessage(message) {
  const status = byId("calendar-status");
  status.textContent = message;
  status.hidden = !message;
}

function statusPill(status) {
  return element("span", bookingStatuses.get(status) || status, "pill " + status);
}

function renderCalendar(error) {
  const calendar = state.calendar;
  const selected = byId("calendar-unit").value;
  const units = (calendar?.units || []).filter((unit) => !selected || String(unit.id) === selected);
  const unitIds = new Set(units.map(({ id }) => id));
  const bookings = (calendar?.bookings || []).filter((booking) => unitIds.has(booking.unit_id));
  const blocks = (calendar?.blocks || []).filter((block) => unitIds.has(block.unit_id));

  const head = document.querySelector("#calendar-grid thead");
  const body = document.querySelector("#calendar-grid tbody");
  head.replaceChildren();
  body.replaceChildren();
  byId("calendar-grid").hidden = !calendar || !units.length;
  byId("week-range").textContent = calendar ? formatDate(calendar.from) + " – " + formatDate(shiftDate(calendar.to, -1)) : "";
  setCalendarMessage(error || (calendar && !units.length ? "Chưa có căn để hiển thị." : ""));

  if (calendar && units.length) {
    const days = Array.from({ length: 7 }, (_, index) => shiftDate(calendar.from, index));
    const heading = element("tr");
    const unitHeading = element("th", "Căn");
    unitHeading.scope = "col";
    heading.append(unitHeading);
    for (const day of days) {
      const column = element("th", dayFormat.format(new Date(day + "T00:00:00Z")));
      const label = element("time", formatDate(day).slice(0, 5), "date-label");
      label.dateTime = day;
      column.scope = "col";
      column.append(label);
      heading.append(column);
    }
    head.append(heading);
    for (const unit of units) {
      const row = element("tr");
      const label = element("th", unit.name);
      label.scope = "row";
      if (unit.district) label.append(element("span", unit.district, "district"));
      row.append(label);
      renderUnitDays(row, days, bookings.filter((item) => item.unit_id === unit.id),
        blocks.filter((item) => item.unit_id === unit.id));
      body.append(row);
    }
  }

  const unitNames = new Map(units.map((unit) => [unit.id, unit.name]));
  renderBookings(bookings, unitNames);
  renderBlocks(blocks, unitNames);
  updateButtons();
}

function renderUnitDays(row, days, bookings, blocks) {
  for (const day of days) {
    const cell = element("td");
    const occupied = bookings.filter((booking) =>
      booking.status !== "cancelled" && booking.status !== "checkout"
      && booking.check_in <= day && day < booking.check_out);
    if (blocks.some((block) => block.start_date <= day && day < block.end_date)) {
      cell.append(element("span", "Đóng bán", "pill blocked"));
    }
    for (const booking of occupied) cell.append(statusPill(booking.status));
    if (!cell.hasChildNodes()) cell.append(element("span", "Trống", "cell-open"));
    row.append(cell);
  }
}

function actionButton(label, handler) {
  const button = element("button", label, "button secondary");
  button.type = "button";
  button.dataset.write = "";
  button.addEventListener("click", handler);
  return button;
}

function renderBookings(bookings, unitNames) {
  const list = byId("booking-list");
  list.replaceChildren();
  byId("booking-empty").hidden = bookings.length > 0;
  const today = state.calendar?.today || todayFormat.format(new Date());
  for (const booking of bookings) {
    const row = element("li", undefined, "entry");
    row.dataset.bookingId = booking.id;
    const detail = element("div");
    detail.append(
      element("strong", booking.booking_reference),
      element("span", unitNames.get(booking.unit_id)),
      element("span", formatDate(booking.check_in) + " → " + formatDate(booking.check_out)),
      statusPill(booking.status),
    );
    row.append(detail);
    const transition = transitions.get(booking.status);
    if (transition) {
      if (transition.status === "checkin" && today >= booking.check_out) {
        detail.append(element("span", "Kỳ lưu trú đã kết thúc"));
      } else if (transition.dateField && today < booking[transition.dateField]) {
        detail.append(element("span", "Có thể " + transition.label.toLocaleLowerCase("vi-VN")
          + " từ " + formatDate(booking[transition.dateField])));
      } else {
        row.append(actionButton(transition.label, () => write(
          "/bookings/" + booking.id + "/status",
          { expected_status: booking.status, status: transition.status },
          "Đã cập nhật đơn " + booking.booking_reference + ".",
          "booking-title",
        )));
      }
    }
    list.append(row);
  }
}

function renderBlocks(blocks, unitNames) {
  const list = byId("block-list");
  const reasons = new Map(Array.from(byId("block-reason").options, (option) => [option.value, option.text]));
  list.replaceChildren();
  byId("block-empty").hidden = blocks.length > 0;
  for (const block of blocks) {
    const row = element("li", undefined, "entry");
    row.dataset.blockId = block.id;
    const detail = element("div");
    detail.append(
      element("strong", unitNames.get(block.unit_id)),
      element("span", formatDate(block.start_date) + " → " + formatDate(block.end_date)),
      element("span", reasons.get(block.reason) || block.reason, "pill blocked"),
    );
    row.append(detail);
    if (state.session?.can_manage_blocks) {
      row.append(actionButton("Mở lại ngày", () => write(
        "/blocks/" + block.id + "/remove", {}, "Đã mở lại ngày.", "block-title",
      )));
    }
    list.append(row);
  }
}

function updateButtons() {
  const calendarDisabled = state.busy || !!state.loadingCalendar;
  for (const button of document.querySelectorAll("[data-write]")) {
    button.disabled = calendarDisabled;
  }
  byId("logout").disabled = state.busy;
  document.querySelector("#login-form button").disabled = state.busy;
  byId("block-submit").disabled = calendarDisabled || !state.calendar?.units.length;
}

async function recoverSession() {
  try {
    applySession(await request("/session"));
    if (state.session.authenticated) renderCalendar();
  } catch {
    applySession({ authenticated: false, enabled: false });
    byId("connection-status").textContent = "Không thể kiểm tra lại phiên làm việc.";
    byId("retry").hidden = false;
  }
}

async function write(path, body, message, focusId) {
  if (state.busy) return;
  state.busy = true;
  notice("");
  updateButtons();
  try {
    await request(path, { body });
    if (path === "/login" || path === "/logout") {
      applySession(await request("/session"));
    }
    if (state.session.authenticated) await loadCalendar();
    if (focusId && !byId(focusId).closest("[hidden]")) byId(focusId).focus();
    notice(message);
  } catch (error) {
    if (error.status === 419 || (path !== "/login" && (error.status === 401 || error.status === 403))) {
      await recoverSession();
    }
    if (error.status === 409 && state.session.authenticated) await loadCalendar();
    notice(error.status ? error.message : "Không thể kết nối với máy chủ.", true);
  } finally {
    state.busy = false;
    updateButtons();
  }
}

function moveCalendar(date) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < "0001-01-01" || date > "9999-12-24") return;
  byId("calendar-from").value = date;
  notice("");
  loadCalendar();
}

byId("calendar-from").value = todayFormat.format(new Date());
byId("retry").addEventListener("click", start);
byId("today").addEventListener("click", () => moveCalendar(state.calendar?.today || todayFormat.format(new Date())));
byId("previous-week").addEventListener("click", () =>
  moveCalendar(shiftDate(byId("calendar-from").value || todayFormat.format(new Date()), -7)));
byId("next-week").addEventListener("click", () =>
  moveCalendar(shiftDate(byId("calendar-from").value || todayFormat.format(new Date()), 7)));
byId("calendar-form").addEventListener("submit", (event) => {
  event.preventDefault();
  moveCalendar(byId("calendar-from").value);
});
byId("calendar-unit").addEventListener("change", () => {
  byId("block-unit").value = byId("calendar-unit").value;
  renderCalendar();
});
byId("login-form").addEventListener("submit", (event) => {
  event.preventDefault();
  const username = byId("username").value.trim();
  const password = byId("password").value;
  byId("password").value = "";
  write("/login", { username, password }, "", "dashboard-title");
});
byId("logout").addEventListener("click", () => write("/logout", {}, "", "username"));
byId("block-start").addEventListener("change", () => {
  const start = byId("block-start");
  const end = byId("block-end");
  end.min = start.value ? shiftDate(start.value, 1) : "";
  if (end.value < end.min) end.value = end.min;
});
byId("block-form").addEventListener("submit", (event) => {
  event.preventDefault();
  write("/blocks", {
    unit_id: Number(byId("block-unit").value),
    start_date: byId("block-start").value,
    end_date: byId("block-end").value,
    reason: byId("block-reason").value,
  }, "Đã đóng ngày đã chọn.", "block-title");
});

start();
