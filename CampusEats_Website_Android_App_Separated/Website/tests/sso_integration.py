"""SSO/session regression tests on an isolated local database, never production.
Google itself is not mocked or contacted. Fixtures exercise the real ticket endpoint.
Run with CAMPUS_SSO_TEST=1 after importing schema.sql into the test database.
"""
import base64
import hashlib
import http.cookiejar
import json
import os
import pathlib
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

assert os.environ.get('CAMPUS_SSO_TEST') == '1', 'Requires explicit isolated-test opt-in'
ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = 'http://127.0.0.1:8088'


def fixture(code):
    bootstrap = json.dumps(str(ROOT / 'server/bootstrap.php'))
    return subprocess.check_output(['php', '-r', f'require {bootstrap};' + code], text=True).strip()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())

    def request(self, path, data=None, csrf=None):
        headers = {}
        if isinstance(data, dict):
            data = json.dumps(data).encode()
            headers['Content-Type'] = 'application/json'
        elif isinstance(data, str):
            data = data.encode()
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        if csrf:
            headers['X-CSRF-Token'] = csrf
        try:
            response = self.opener.open(urllib.request.Request(BASE + path, data=data, headers=headers))
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read().decode()

    def bootstrap(self):
        return json.loads(self.request('/api.php?action=bootstrap')[2])


user_id = int(fixture("query('INSERT INTO users(name,email) VALUES (?,?)', ['SSO test', 'sso-' . bin2hex(random_bytes(6)) . '@example.test']); echo $pdo->lastInsertId();"))


def ticket(expired=False):
    value = secrets.token_hex(32)
    verifier = base64.urlsafe_b64encode(secrets.token_bytes(32)).rstrip(b'=').decode()
    challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode()
    expiry = 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)' if expired else 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)'
    fixture("query('INSERT INTO mobile_tickets(ticket_hash,user_id,challenge,expires_at) VALUES (?,?,?," + expiry + ")', [" + json.dumps(hashlib.sha256(value.encode()).hexdigest()) + ',' + str(user_id) + ',' + json.dumps(challenge) + ']);')
    return value, verifier


def redeem(client, value, verifier):
    return client.request('/sso.php?mode=redeem', urllib.parse.urlencode({'ticket': value, 'verifier': verifier}))[0]


web, app = Client(), Client()
assert web.bootstrap()['user'] is None
status, headers, _ = web.request('/sso.php')
assert status == 302 and headers['Location'].startswith('https://accounts.google.com/')
state = urllib.parse.parse_qs(urllib.parse.urlparse(headers['Location']).query)['state'][0]
assert web.request('/sso.php?mode=callback&state=wrong&code=unused')[0] == 400
assert web.request('/sso.php?mode=callback&error=access_denied&state=' + state)[0] == 400
assert web.request('/sso.php?mode=callback&error=access_denied&state=' + state)[0] == 400
assert app.request('/sso.php?mode=redeem')[0] == 400
assert redeem(app, 'invalid', 'invalid') == 400
value, verifier = ticket()
assert redeem(app, value, 'A' * 43) == 400
assert redeem(app, value, verifier) == 303
assert app.bootstrap()['user']['id'] == user_id
assert redeem(Client(), value, verifier) == 400
expired, proof = ticket(True)
assert redeem(Client(), expired, proof) == 400
web_ticket, web_proof = ticket()
assert redeem(web, web_ticket, web_proof) == 303
assert web.bootstrap()['user']['id'] == app.bootstrap()['user']['id']
csrf = app.bootstrap()['csrf']
assert app.request('/api.php?action=logout', {}, csrf)[0] == 200
assert app.bootstrap()['user'] is None
assert app.request('/api.php?action=orders')[0] == 401
assert web.bootstrap()['user']['id'] == user_id  # Local logout leaves other client signed in.
print('PASS: OAuth state/cancellation, malformed/wrong/expired/replayed tickets, shared identity, logout and protected access')
