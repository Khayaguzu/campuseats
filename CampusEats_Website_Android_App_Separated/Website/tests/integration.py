"""Exercise real PHP/MySQL behaviour against an isolated test installation.
Run: python tests/integration.py http://127.0.0.1:8088 admin@test.local 'password'
The database must contain schema.sql, seed.sql and the supplied admin account.
Never run this test against a production database; it creates records.
Enable payments.demo ONLY in an isolated local test server (no real funds).
The test server must be accessed on localhost, with PHP/Apache using a loopback address.
"""
import sys, uuid, datetime, urllib.request, urllib.error, http.cookiejar, json
BASE, ADMIN_EMAIL, ADMIN_PASSWORD = sys.argv[1:4]

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = self.call('bootstrap')[1]['csrf']

    def call(self, action, data=None, csrf=True):
        headers = {'Accept': 'application/json'}
        if data is not None:
            headers['Content-Type'] = 'application/json'
            if csrf:
                headers['X-CSRF-Token'] = self.csrf
        request = urllib.request.Request(BASE + '/api.php?action=' + action,
            data=None if data is None else json.dumps(data).encode(), headers=headers)
        try:
            response = self.opener.open(request)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read().decode()
        try:
            return response.status, json.loads(raw)
        except json.JSONDecodeError:
            raise AssertionError('Non-JSON server response: ' + raw[:2000])

checks = []
def check(condition, description):
    assert condition, description
    checks.append(description)
    print('PASS:', description)

suffix = uuid.uuid4().hex[:8]
admin = Client()
check(admin.call('login', {'email': ADMIN_EMAIL, 'password': ADMIN_PASSWORD})[0] == 200, 'Administrator can sign in')
student = Client()
status, data = student.call('register', {'name': 'Test Student', 'email': suffix+'@student.test', 'password': 'TestingPassword123!', 'role': 'student', 'student_number': suffix})
check(status == 201, 'Student registration persists to database')
student_id = data['user']['id']
check(student.call('admin')[0] == 403, 'Student cannot access administration')
check(student.call('settings', {}, csrf=False)[0] == 403, 'Missing CSRF token is rejected')
check(student.call('cart_set', {'product_id': 1, 'quantity': -1})[0] == 422, 'Negative basket quantities are rejected')
check(student.call('cart_set', {'product_id': 1, 'quantity': 99})[0] == 422, 'Basket stock limit is enforced')
student.call('cart_set', {'product_id': 1, 'quantity': 2})
second = Client()
second.call('login', {'email': suffix+'@student.test', 'password': 'TestingPassword123!'})
check(second.call('cart')[1]['items'][0]['quantity'] == 2, 'Basket is shared between independent client sessions')
pickup = (datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(minutes=20)).isoformat()
def checkout(client):
    payload = {'request_key': uuid.uuid4().hex, 'pickup_at': pickup, 'notes': 'Integration test', 'total_cents': 1, 'payment_method': 'demo'}
    status, result = client.call('checkout', payload)
    check(status == 201, 'Order checkout succeeds with server-authoritative prices')
    duplicate = client.call('checkout', payload)[1]
    check(duplicate['order_id'] == result['order_id'], 'Checkout retries do not create duplicate orders')
    return result['order_id']
order_id = checkout(student)
order = student.call('orders')[1]['orders'][0]
check(order['discount_cents'] == 0 and order['total_cents'] == 9000, 'Unverified student receives no discount; supplied total is ignored')
check(student.call('cancel', {'id': order_id})[0] == 200, 'Customer can cancel a placed order')
check(student.call('catalog')[1]['products'][0]['stock'] == 40, 'Cancellation restores the exact reserved quantity')
admin.call('user_approve', {'id': student_id})
student.call('cart_set', {'product_id': 1, 'quantity': 2})
order_id = checkout(student)
order = student.call('orders')[1]['orders'][0]
check(order['discount_cents'] == 900 and order['total_cents'] == 8100, 'Verified student receives exactly 10 percent off')
check(admin.call('order_status', {'id': order_id, 'status': 'collected'})[0] == 422, 'Invalid order status jumps are rejected')
check(admin.call('order_status', {'id': order_id, 'status': 'preparing'})[0] == 422, 'Unpaid orders cannot enter food preparation')
check(student.call('payment_verify', {'id': order_id})[0] == 404, 'Manual administrator payment approval endpoint is removed')
check(admin.call('payment_verify', {'id': order_id})[0] == 404, 'Administrators cannot manually declare payments successful')
check(admin.call('payment_demo_complete', {'id': order_id})[0] == 422, 'Administrators cannot simulate payment on another user order')
status, receipt = student.call('payment_demo_complete', {'id': order_id})
check(status == 200 and receipt.get('demo') is True and receipt.get('collection_number', '').startswith('CE'), 'User completes a clearly labelled simulated payment and receives collection code')
check(student.call('payment_demo_complete', {'id': order_id})[0] == 200, 'Retrying same simulated payment is idempotent')
check(admin.call('order_status', {'id': order_id, 'status': 'preparing'})[0] == 200, 'Administrator can prepare a verified paid order')
check(student.call('cancel', {'id': order_id})[0] == 422, 'Customers cannot cancel after preparation starts')
admin.call('order_status', {'id': order_id, 'status': 'ready'})
admin.call('order_status', {'id': order_id, 'status': 'collected'})
check(student.call('orders')[1]['orders'][0]['payment_status'] == 'paid', 'Payment remains verified after collection')
check(student.call('cancel', {'id': order_id})[0] == 422, 'Paid collected orders cannot be cancelled without a refund workflow')
student.call('settings', {'name': 'Updated Student', 'phone': '0110000000', 'student_number': suffix, 'theme': 'dark', 'order_updates': False})
check(second.call('bootstrap')[1]['user']['theme'] == 'dark', 'Account settings are shared between clients')
student.call('password', {'current_password': 'TestingPassword123!', 'new_password': 'ChangedPassword123!'})
check(Client().call('login', {'email': suffix+'@student.test', 'password': 'ChangedPassword123!'})[0] == 200, 'Password changes permit subsequent sign-in')
requester = Client()
status, data = requester.call('register', {'name': 'Admin Requester', 'email': suffix+'@admin.test', 'password': 'TestingPassword123!', 'role': 'admin'})
check(status == 201 and data['user']['role'] != 'admin', 'Public registration cannot grant administrator privileges')
check(requester.call('cancel', {'id': order_id})[0] == 404, 'A different account cannot cancel another user order')
lecturer = Client()
status, data = lecturer.call('register', {'name': 'Lecturer', 'email': suffix+'@lecturer.test', 'password': 'TestingPassword123!', 'role': 'lecturer'})
check(status == 201 and data['user']['role'] == 'lecturer', 'Lecturer registration works')
lecturer.call('cart_set', {'product_id': 2, 'quantity': 1})
lecturer_order_id = checkout(lecturer)
check(lecturer.call('orders')[1]['orders'][0]['discount_cents'] == 0, 'Lecturers pay the regular selling price')
check(admin.call('order_status', {'id': lecturer_order_id, 'status': 'preparing'})[0] == 422, 'Unpaid lecturer orders cannot be prepared')
check(lecturer.call('payment_demo_complete', {'id': lecturer_order_id})[0] == 200, 'Lecturer can complete simulated checkout without any administrator approval')
check(lecturer.call('payment_start', {'id': lecturer_order_id})[0] == 422, 'Simulated test orders cannot start PayFast payments')
product = admin.call('admin')[1]['products'][0]
product['stock'] = 0
admin.call('product_save', product)
check(lecturer.call('cart_set', {'product_id': 1, 'quantity': 1})[0] == 422, 'Sold-out products cannot be added')
product['stock'] = 38
admin.call('product_save', product)
check(admin.call('vendor_save', {'id': 1, 'name': 'The Grill Spot', 'location': 'Main courtyard, counter 1', 'is_open': False})[0] == 200, 'Administrator can close a vendor')
check(lecturer.call('cart_set', {'product_id': 1, 'quantity': 1})[0] == 422, 'Closed vendors cannot accept new basket items')
admin.call('vendor_save', {'id': 1, 'name': 'The Grill Spot', 'location': 'Main courtyard, counter 1', 'is_open': True})
print(f'\n{len(checks)} checks passed.')
