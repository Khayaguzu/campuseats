"""Check the shared API contract between the website and Android WebView.

Run from the Website directory:
    python tests/shared_client_contract.py

This is a source-level regression check, not a replacement for integration tests
against WampServer, MySQL, or a real Android emulator.
"""
from pathlib import Path
import re

website = Path(__file__).resolve().parents[1]
project = website.parent
android = project / 'Android_App'
backend = (website / 'web' / 'api.php').read_text(encoding='utf-8')
frontend = (website / 'web' / 'assets' / 'app.js').read_text(encoding='utf-8')
android_main = (android / 'app' / 'src' / 'main' / 'java' / 'za' / 'ac' /
                'campuseats' / 'MainActivity.kt').read_text(encoding='utf-8')
gradle_properties = (android / 'gradle.properties').read_text(encoding='utf-8')
manifest = (android / 'app' / 'src' / 'main' / 'AndroidManifest.xml').read_text(encoding='utf-8')

# Both browser and Android run the same JavaScript, therefore the same API calls.
assert 'WebView(this)' in android_main
assert 'webView.loadUrl(siteUrl + "index.php")' in android_main
assert 'fetch(`api.php?action=${encodeURIComponent(action)}`, options)' in frontend
assert 'android.permission.INTERNET' in manifest
assert 'mysql:' not in android_main.lower(), 'Android must not connect directly to MySQL'

# Check that every literal API action used by the shared browser client exists.
backend_actions = set(re.findall(r"case\s+'([a-z_]+)':", backend))
client_actions = set(re.findall(r"\bapi\(['\"]([a-z_]+)['\"]", frontend))
required = {
    'bootstrap', 'catalog', 'register', 'login', 'logout', 'cart', 'cart_set',
    'checkout', 'orders', 'settings', 'password', 'payment_demo_complete',
    'payment_start', 'order_status', 'admin', 'product_save',
    'vendor_save', 'cancel'
}
missing = (client_actions | required) - backend_actions
assert not missing, 'Missing API actions: ' + ', '.join(sorted(missing))

# The hosted app and sign-in flow must share the same HTTPS backend.
properties = dict(re.findall(r'^(CAMPUS_[A-Z_]+)=(.+)$', gradle_properties, re.MULTILINE))
assert properties['CAMPUS_BASE_URL'].startswith('https://')
assert properties['CAMPUS_SSO_URL'] == properties['CAMPUS_BASE_URL']
assert 'role_request' not in backend_actions and 'user_approve' not in backend_actions
print('PASS: website and Android share the customer UI and matching hosted API')
