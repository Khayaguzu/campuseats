<?php
/** Same-origin JSON API used by both the browser and the Android application. */
require __DIR__ . '/../server/bootstrap.php';
require __DIR__ . '/../server/payments.php';
$action = $_GET['action'] ?? 'bootstrap';
$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if ($method === 'POST')
{
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
    {
        fail('Your session changed. Refresh and try again.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input))
    {
        fail('A JSON object is required.', 400);
    }
}
elseif ($method !== 'GET')
{
    fail('Method not allowed.', 405);
}
$readActions = ['bootstrap', 'catalog', 'cart', 'orders', 'admin'];
if ($method !== 'POST' && !in_array($action, $readActions, true))
{
    fail('Use POST for this operation.', 405);
}
try
{
    switch ($action)
    {
        case 'bootstrap':
            $sessionUser = currentUser();
            respond(['user' => $sessionUser, 'csrf' => $_SESSION['csrf'],
                'sso_enabled' => $config['google_client_id'] !== '' && $config['google_client_secret'] !== '',
                'vendors' => query('SELECT * FROM vendors ORDER BY id')->fetchAll(),
                'payment_options' => paymentOptions($config, $sessionUser)]);
        case 'catalog':
            respond(['products' => query('SELECT p.*,v.name AS vendor_name,v.location,v.is_open FROM products p JOIN vendors v ON v.id=p.vendor_id WHERE p.active=1 ORDER BY p.id')->fetchAll()]);
        case 'register':
            rateLimit('register', 10);
            $name = textField($input, 'name', 100);
            $email = strtolower(textField($input, 'email', 190));
            $password = (string)($input['password'] ?? '');
            $role = $input['role'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || (strlen($password) < 12 || strlen($password) > 72))
            {
                fail('Use a valid email and a password of 12 to 72 characters.');
            }
            if (!in_array($role, ['student', 'lecturer', 'admin'], true))
            {
                fail('Choose a valid account type.');
            }
            $number = textField($input, 'student_number', 40, $role === 'student');
            if (query('SELECT id FROM users WHERE email=?', [$email])->fetch())
            {
                fail('This email is already registered. Please sign in.');
            }
            query('INSERT INTO users(name,email,password_hash,role,requested_role,student_number) VALUES (?,?,?,?,?,?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role === 'admin' ? 'lecturer' : $role, $role, $number]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            respond(['user' => currentUser()], 201);
        case 'login':
            rateLimit('login');
            $email = strtolower(textField($input, 'email', 190));
            $user = query('SELECT * FROM users WHERE email=?', [$email])->fetch();
            if (!$user || !$user['password_hash'] || !password_verify((string)($input['password'] ?? ''), $user['password_hash']))
            {
                fail('The email or password is incorrect.', 401);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            respond(['user' => currentUser()]);
        case 'logout':
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            respond(['ok' => true]);
        case 'settings':
            $user = requireUser();
            $name = textField($input, 'name', 100);
            $phone = textField($input, 'phone', 30, false);
            $theme = $input['theme'] ?? 'light';
            if (!in_array($theme, ['light', 'dark'], true))
            {
                fail('Invalid theme.');
            }
            $number = textField($input, 'student_number', 40, $user['role'] === 'student');
            // Changing the student identifier invalidates the previous verification.
            $verified = $number === $user['student_number'] ? (int)$user['verified'] : 0;
            query('UPDATE users SET name=?,phone=?,theme=?,student_number=?,verified=?,order_updates=? WHERE id=?',
                [$name, $phone, $theme, $number, $verified, !empty($input['order_updates']) ? 1 : 0, $user['id']]);
            respond(['user' => currentUser()]);
        case 'password':
            $user = requireUser();
            rateLimit('password');
            $hash = query('SELECT password_hash FROM users WHERE id=?', [$user['id']])->fetchColumn();
            if ($hash && !password_verify((string)($input['current_password'] ?? ''), $hash))
            {
                fail('Current password is incorrect.');
            }
            $password = (string)($input['new_password'] ?? '');
            if ((strlen($password) < 12 || strlen($password) > 72))
            {
                fail('Use at least 12 characters.');
            }
            query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            respond(['ok' => true]);
        case 'cart':
            $user = requireUser();
            respond(['items' => query('SELECT c.quantity,p.*,v.name AS vendor_name,v.is_open FROM cart c JOIN products p ON p.id=c.product_id JOIN vendors v ON v.id=p.vendor_id WHERE c.user_id=? ORDER BY p.id', [$user['id']])->fetchAll()]);
        case 'cart_set':
            $user = requireUser();
            $id = integerField($input, 'product_id', 1, 2147483647);
            $qty = integerField($input, 'quantity', 0, 99);
            $product = query('SELECT p.*,v.is_open FROM products p JOIN vendors v ON v.id=p.vendor_id WHERE p.id=?', [$id])->fetch();
            if ($qty === 0)
            {
                query('DELETE FROM cart WHERE user_id=? AND product_id=?', [$user['id'], $id]);
            }
            else
            {
                if (!$product || !$product['active'] || !$product['is_open'] || $product['stock'] < $qty)
                {
                    fail('This quantity is not currently available.');
                }
                query('INSERT INTO cart(user_id,product_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)', [$user['id'], $id, $qty]);
            }
            respond(['ok' => true]);
        case 'checkout':
            $user = requireUser();
            requirePaymentSchema($pdo);
            $key = textField($input, 'request_key', 36);
            if (!preg_match('/^[a-zA-Z0-9-]{16,36}$/', $key))
            {
                fail('Invalid checkout request identifier.');
            }
            $pickup = strtotime(textField($input, 'pickup_at', 40));
            if (!$pickup || $pickup < time() + 300 || $pickup > time() + 86400)
            {
                fail('Choose a collection time between 5 minutes and 24 hours from now.');
            }
            $notes = textField($input, 'notes', 500, false);
            $method = (string)($input['payment_method'] ?? '');
            $available = paymentOptions($config, $user);
            if (!in_array($method, ['demo', 'payfast'], true) || empty($available[$method]))
            {
                fail('Checkout is not enabled for this account. Contact the project administrator.');
            }
            $pdo->beginTransaction();
            // Serialize checkout per customer and lock inventory to prevent overselling.
            $user = query('SELECT * FROM users WHERE id=? FOR UPDATE', [$user['id']])->fetch();
            $old = query('SELECT id FROM orders WHERE user_id=? AND request_key=?', [$user['id'], $key])->fetch();
            if ($old)
            {
                $pdo->commit();
                respond(['order_id' => $old['id'], 'payment_pending' => true]);
            }
            $items = query('SELECT c.quantity,p.*,v.name AS vendor_name,v.location,v.is_open FROM cart c JOIN products p ON p.id=c.product_id JOIN vendors v ON v.id=p.vendor_id WHERE c.user_id=? ORDER BY p.id FOR UPDATE', [$user['id']])->fetchAll();
            if (!$items)
            {
                $pdo->rollBack();
                fail('Your basket is empty.');
            }
            $subtotal = 0;
            foreach ($items as $item)
            {
                if (!$item['active'] || !$item['is_open'] || $item['stock'] < $item['quantity'])
                {
                    $pdo->rollBack();
                    fail($item['name'] . ' is unavailable in this quantity. Update your basket.');
                }
                $subtotal += (int)$item['price_cents'] * (int)$item['quantity'];
            }
            // All monetary calculations use integer cents; client prices are ignored.
            $discount = $user['role'] === 'student' && $user['verified'] ? intdiv($subtotal + 5, 10) : 0;
            if ($method === 'payfast' && ($subtotal - $discount) < 500)
            {
                $pdo->rollBack();
                fail('PayFast requires a minimum order total of R5.00.');
            }
            query('INSERT INTO orders(user_id,subtotal_cents,discount_cents,total_cents,pickup_at,notes,request_key) VALUES (?,?,?,?,?,?,?)',
                [$user['id'], $subtotal, $discount, $subtotal-$discount, gmdate('Y-m-d H:i:s', $pickup), $notes, $key]);
            $orderId = (int)$pdo->lastInsertId();
            foreach ($items as $item)
            {
                query('INSERT INTO order_items(order_id,product_id,name,vendor_name,location,quantity,price_cents) VALUES (?,?,?,?,?,?,?)',
                    [$orderId, $item['id'], $item['name'], $item['vendor_name'], $item['location'], $item['quantity'], $item['price_cents']]);
                query('UPDATE products SET stock=stock-? WHERE id=?', [$item['quantity'], $item['id']]);
            }
            // New orders always begin unpaid. The existing stock reservation and order ID remain intact.
            query('INSERT INTO order_payments(order_id,method,bank_reference) VALUES (?,?,?)',
                [$orderId, $method, null]);
            query('DELETE FROM cart WHERE user_id=?', [$user['id']]);
            $pdo->commit();
            // Internal database ID is returned to start payment, but is not a valid collection code.
            respond(['order_id' => $orderId, 'payment_pending' => true], 201);
        case 'payment_demo_complete':
            // Non-monetary project checkout; locally restricted or explicitly allowlisted on HTTPS.
            $user = requireUser();
            requirePaymentSchema($pdo);
            if (!paymentOptions($config, $user)['demo'])
            {
                fail('Checkout is not enabled for this account.', 403);
            }
            // The customer re-enters CampusEats credentials before payment completion.
            requirePaymentLogin($user, $input);
            $id = integerField($input, 'id', 1, 2147483647);
            $pdo->beginTransaction();
            $order = query('SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE', [$id, $user['id']])->fetch();
            $payment = query('SELECT * FROM order_payments WHERE order_id=? FOR UPDATE', [$id])->fetch();
            if (!$order || !$payment || $payment['method'] !== 'demo'
                || $order['status'] === 'cancelled')
            {
                $pdo->rollBack();
                fail('This order cannot use this checkout method.', 422);
            }
            if ($payment['state'] === 'paid' && $order['payment_status'] === 'paid')
            {
                $pdo->commit();
                respond(['paid' => true, 'collection_number' => 'CE' . str_pad((string)$id, 6, '0', STR_PAD_LEFT), 'demo' => true]);
            }
            if ($payment['state'] !== 'pending' || $order['payment_status'] !== 'unpaid')
            {
                $pdo->rollBack();
                fail('The order is not awaiting a test payment.');
            }
            $testReference = 'DEMO-' . $id . '-' . bin2hex(random_bytes(8));
            query('UPDATE order_payments SET state=?,gateway_payment_id=?,confirmed_at=UTC_TIMESTAMP() WHERE order_id=?', ['paid', $testReference, $id]);
            query('UPDATE orders SET payment_status=? WHERE id=?', ['paid', $id]);
            $pdo->commit();
            respond(['paid' => true, 'collection_number' => 'CE' . str_pad((string)$id, 6, '0', STR_PAD_LEFT), 'demo' => true]);
        case 'payment_start':
            $user = requireUser();
            requirePaymentSchema($pdo);
            if (!paymentOptions($config)['payfast'])
            {
                fail('Online payment is not currently available.');
            }
            // Require the same account's password before generating a gateway link.
            requirePaymentLogin($user, $input);
            $id = integerField($input, 'id', 1, 2147483647);
            $pdo->beginTransaction();
            $order = query('SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE', [$id, $user['id']])->fetch();
            $payment = query('SELECT * FROM order_payments WHERE order_id=? FOR UPDATE', [$id])->fetch();
            if (!$order || !$payment || $payment['method'] !== 'payfast' || $payment['state'] !== 'pending'
                || $order['status'] === 'cancelled' || $order['payment_status'] !== 'unpaid')
            {
                $pdo->rollBack();
                fail('This order cannot be paid using PayFast.');
            }
            if ((int)$order['total_cents'] < 500)
            {
                $pdo->rollBack();
                fail('PayFast payments require an order total of at least R5.00.');
            }
            // The short-lived token allows the Android app to continue in an external browser
            // without sharing its authenticated WebView cookies or exposing the order to strangers.
            $token = bin2hex(random_bytes(32));
            query('UPDATE order_payments SET payment_token_hash=?,payment_token_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE) WHERE order_id=?',
                [hash('sha256', $token), $id]);
            $pdo->commit();
            respond(['payment_url' => 'pay.php?token=' . $token]);
        case 'orders':
            $user = requireUser();
            requirePaymentSchema($pdo);
            $orders = query('SELECT o.*,p.method AS payment_method,p.state AS payment_state,p.bank_reference
                FROM orders o LEFT JOIN order_payments p ON p.order_id=o.id
                WHERE o.user_id=? ORDER BY o.id DESC LIMIT 100', [$user['id']])->fetchAll();
            foreach ($orders as &$order)
            {
                $order['items'] = query('SELECT * FROM order_items WHERE order_id=?', [$order['id']])->fetchAll();
            }
            unset($order);
            respond(['orders' => $orders]);
        case 'cancel':
        case 'order_status':
            $user = requireUser($action === 'order_status');
            $id = integerField($input, 'id', 1, 2147483647);
            $pdo->beginTransaction();
            $order = query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$id])->fetch();
            if (!$order || ($action === 'cancel' && (int)$order['user_id'] !== (int)$user['id']))
            {
                $pdo->rollBack();
                fail('Order not found.', 404);
            }
            $status = $action === 'cancel' ? 'cancelled' : ($input['status'] ?? '');
            $next = ['placed' => ['preparing','cancelled'], 'preparing' => ['ready','cancelled'], 'ready' => ['collected'], 'collected' => [], 'cancelled' => []];
            if (!in_array($status, $next[$order['status']], true) || ($action === 'cancel' && $order['status'] !== 'placed'))
            {
                $pdo->rollBack();
                fail('This order can no longer make that status change.');
            }
            // Never prepare, hand out, or mark a new/legacy order collected until paid.
            if ($status !== 'cancelled' && $order['payment_status'] !== 'paid')
            {
                $pdo->rollBack();
                fail('Payment must be verified before this order can be prepared or collected.');
            }
            // Refunds cannot be handled by changing an order status. Protect already-paid orders.
            if ($status === 'cancelled' && $order['payment_status'] === 'paid')
            {
                $pdo->rollBack();
                fail('Paid orders require an administrator-managed refund before cancellation.');
            }
            if ($status === 'cancelled')
            {
                foreach (query('SELECT * FROM order_items WHERE order_id=? ORDER BY product_id', [$id])->fetchAll() as $item)
                {
                    query('UPDATE products SET stock=stock+? WHERE id=?', [$item['quantity'], $item['product_id']]);
                }
            }
            query('UPDATE orders SET status=? WHERE id=?', [$status, $id]);
            if ($action === 'order_status')
            {
                query('INSERT INTO audit_log(admin_id,action,target_id) VALUES (?,?,?)', [$user['id'], 'order_' . $status, $id]);
            }
            $pdo->commit();
            respond(['ok' => true]);
        case 'admin':
            requireUser(true);
            requirePaymentSchema($pdo);
            $orders = query('SELECT o.*,u.name AS customer,u.email,p.method AS payment_method,p.state AS payment_state,p.bank_reference
                FROM orders o JOIN users u ON u.id=o.user_id
                LEFT JOIN order_payments p ON p.order_id=o.id ORDER BY o.id DESC LIMIT 100')->fetchAll();
            foreach ($orders as &$order)
            {
                $order['items'] = query('SELECT * FROM order_items WHERE order_id=?', [$order['id']])->fetchAll();
            }
            unset($order);
            respond(['products' => query('SELECT * FROM products ORDER BY id')->fetchAll(),
                'users' => query('SELECT id,name,email,role,requested_role,student_number,verified FROM users ORDER BY id DESC')->fetchAll(),
                'orders' => $orders,
                'stats' => query("SELECT COUNT(*) AS orders,COALESCE(SUM(CASE WHEN o.payment_status='paid' AND (p.method IS NULL OR p.method <> 'demo') THEN o.total_cents ELSE 0 END),0) AS revenue_cents FROM orders o LEFT JOIN order_payments p ON p.order_id=o.id")->fetch(),
                'images' => array_map('basename', glob(__DIR__ . '/assets/food/*.jpg'))]);
        case 'product_save':
            $user = requireUser(true);
            $id = integerField($input, 'id', 0, 2147483647);
            $vendor = integerField($input, 'vendor_id', 1, 2147483647);
            if (!query('SELECT id FROM vendors WHERE id=?', [$vendor])->fetch())
            {
                fail('Vendor not found.');
            }
            $image = textField($input, 'image', 120);
            if (basename($image) !== $image || !is_file(__DIR__ . '/assets/food/' . $image) || !preg_match('/\.(jpg|png|webp)$/i', $image))
            {
                fail('Choose an existing food image.');
            }
            $values = [$vendor, textField($input, 'name', 120), textField($input, 'description', 500), textField($input, 'category', 40),
                integerField($input, 'price_cents', 1, 1000000), integerField($input, 'stock', 0, 100000), $image, !empty($input['active']) ? 1 : 0];
            if ($id)
            {
                // array_merge() preserves the same positional SQL parameters on PHP 7.2 and newer.
                query('UPDATE products SET vendor_id=?,name=?,description=?,category=?,price_cents=?,stock=?,image=?,active=? WHERE id=?', array_merge($values, [$id]));
            }
            else
            {
                query('INSERT INTO products(vendor_id,name,description,category,price_cents,stock,image,active) VALUES (?,?,?,?,?,?,?,?)', $values);
                $id = (int)$pdo->lastInsertId();
            }
            query('INSERT INTO audit_log(admin_id,action,target_id) VALUES (?,?,?)', [$user['id'], 'product_save', $id]);
            respond(['ok' => true]);
        case 'vendor_save':
            $user = requireUser(true);
            $id = integerField($input, 'id', 1, 2147483647);
            query('UPDATE vendors SET name=?,location=?,is_open=? WHERE id=?', [textField($input, 'name', 100), textField($input, 'location', 150), !empty($input['is_open']) ? 1 : 0, $id]);
            query('INSERT INTO audit_log(admin_id,action,target_id) VALUES (?,?,?)', [$user['id'], 'vendor_save', $id]);
            respond(['ok' => true]);
        case 'user_approve':
            $user = requireUser(true);
            $id = integerField($input, 'id', 1, 2147483647);
            if ($id === (int)$user['id'])
            {
                fail('An administrator cannot approve their own account.');
            }
            $target = query('SELECT * FROM users WHERE id=?', [$id])->fetch();
            if (!$target)
            {
                fail('User not found.', 404);
            }
            query('UPDATE users SET role=requested_role,verified=1 WHERE id=?', [$id]);
            query('INSERT INTO audit_log(admin_id,action,target_id) VALUES (?,?,?)', [$user['id'], 'user_approve', $id]);
            respond(['ok' => true]);
        case 'role_request':
            $user = requireUser();
            if ($user['role'] === 'admin')
            {
                fail('Administrator roles are managed by another administrator.');
            }
            $role = $input['role'] ?? '';
            if (!in_array($role, ['student','lecturer','admin'], true))
            {
                fail('Invalid role.');
            }
            $number = textField($input, 'student_number', 40, $role === 'student');
            query('UPDATE users SET requested_role=?,role=?,verified=0,student_number=? WHERE id=?', [$role, $role === 'admin' ? 'lecturer' : $role, $number, $user['id']]);
            respond(['user' => currentUser()]);
        default:
            fail('Endpoint not found.', 404);
    }
}
catch (Throwable $error)
{
    if ($pdo->inTransaction())
    {
        $pdo->rollBack();
    }
    error_log((string)$error);
    fail('The request could not be completed. Please refresh and try again.', 500);
}
