"""Exercise a disposable container only. Creates and cancels a fictional booking."""
import datetime as dt
import http.cookiejar
import json
import sys
import time
import urllib.error
import urllib.parse
import urllib.request


base = sys.argv[1].rstrip('/') if len(sys.argv) > 1 else 'http://127.0.0.1:18080'
if not base.startswith(('http://127.0.0.1:', 'http://localhost:')):
    raise SystemExit('This smoke test may only write to a local disposable demo.')
browser = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def read(path):
    with browser.open(base + path, timeout=5) as response:
        return response.read().decode('utf-8')


def get(action, **query):
    return json.loads(read('/backend/customer-api.php?' + urllib.parse.urlencode(
        {'action': action, **query})))


def post(action, payload, expected=200):
    token = get('get-csrf')['data']['token']
    request = urllib.request.Request(
        base + '/backend/customer-api.php?' + urllib.parse.urlencode({'action': action}),
        data=json.dumps(payload).encode('utf-8'),
        headers={'Content-Type': 'application/json', 'X-CSRF-Token': token}, method='POST')
    with browser.open(request, timeout=15) as response:
        assert response.status == expected, (action, response.status)
        result = json.load(response)
        assert result['success'] is True, action
        return result['data']


for attempt in range(180):
    try:
        if json.loads(read('/health'))['status'] == 'ok':
            break
    except (OSError, ValueError, KeyError):
        time.sleep(1)
else:
    raise SystemExit('Demo failed to become healthy within 180 seconds.')

capabilities = get('get-csrf')['data']['capabilities']
assert capabilities['yangmatch_stay_search'] is True
assert capabilities['ephemeral_demo'] is True
assert get('check-auth')['ephemeral_demo'] is True
assert json.loads(read('/api/reception/session'))['enabled'] is False
assert 'portfolio-demo-notice' in read('/customer/js/ui.js')
assert 'YangHomestay' in read('/customer/index.html')
assert '<svg' in read('/assets/favicon.svg')
assert get('units')['success'] is True

# An explicitly fictional account avoids any existing seed login or real user data.
email = 'container-smoke@example.test'
password = 'Only-Disposable-Demo-123'
post('register', {
    'full_name': 'Khach Thu Nghiem', 'email': email, 'phone': '0900000000',
    'password': password, 'confirm_password': password,
}, expected=201)
post('login', {'email': email, 'password': password})
assert get('check-auth')['authenticated'] is True
today = dt.datetime.now(dt.timezone(dt.timedelta(hours=7))).date()
check_in = str(today + dt.timedelta(days=37))
check_out = str(today + dt.timedelta(days=39))
matches = post('yangmatch', {
    'q1': 'couple', 'q2': 'quiet', 'q3': [], 'q4': 0,
    'check_in': check_in, 'check_out': check_out, 'guests': 2,
})
assert matches['pricing_basis'] == 'stay'
assert len(matches['matches']) > 0
unit_id = matches['matches'][0]['unit']['id']
quote = get('booking-quote', unit_id=unit_id, check_in=check_in,
            check_out=check_out, guests=2)['data']
assert quote['number_of_nights'] == 2
assert len(quote['nights']) == 2
booking = post('create-booking', {
    'unit_id': unit_id, 'check_in': check_in, 'check_out': check_out,
    'adults': 2, 'children': 0, 'special_requests': 'Disposable container smoke test',
}, expected=201)
assert booking['total_amount'] == quote['total_amount']
assert booking['status'] == 'pending'
cancelled = post('cancel-booking', {'id': booking['id']})
assert cancelled['status'] == 'cancelled'
print('PASS: health, demo notice metadata, reception disabled, catalog, CSRF, login, '
      'trip-aware YangMatch, nightly quote, atomic booking and cancellation.')
