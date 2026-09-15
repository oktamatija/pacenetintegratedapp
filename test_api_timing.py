import urllib.request
import urllib.parse
import http.cookiejar
import json
import time

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. Login
login_data = json.dumps({'user': 'owner', 'pass': '1234'}).encode('utf-8')
req = urllib.request.Request('https://hy0045.my.id/api/auth.php?action=login', data=login_data, headers={'Content-Type': 'application/json'})
resp = opener.open(req)
print("Login result:", resp.read().decode('utf-8'))

# 2. Timing test for routers.php (run 3 times)
for i in range(1, 4):
    t0 = time.time()
    req = urllib.request.Request('https://hy0045.my.id/api/routers.php')
    resp = opener.open(req)
    raw = resp.read().decode('utf-8')
    t1 = time.time()
    data = json.loads(raw)
    print(f"\n[Test #{i}] /api/routers.php took {t1-t0:.2f} seconds")
    routers = data.get('data', {}).get('routers', [])
    for r in routers:
        print(f"   * {r.get('session')}: online={r.get('online')}, ip={r.get('vpn_ip')}, cpu={r.get('cpu_load')}%, active={r.get('active_sessions')}, rx={r.get('total_rx_human')}")
    time.sleep(1)
