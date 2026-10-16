'use strict';
/** Shared presentation layer. The database is the authority for roles, stock,
 * prices, baskets and orders; no sensitive state is stored in localStorage. */
const state = { user: null, csrf: '', products: [], vendors: [], cart: [], sso: false, paymentOptions: { eft: null, payfast: false, demo: false }, category: 'All', search: '', vendor: '', adminTab: 'orders', admin: null };
const $ = (selector) => document.querySelector(selector);
const money = (cents) => new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(Number(cents) / 100);
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const dateText = (value) => new Date(value.replace(' ', 'T') + 'Z').toLocaleString('en-ZA', { timeZone: 'Africa/Johannesburg', dateStyle: 'medium', timeStyle: 'short' });
// HTML datetime-local expects device-local time rather than a UTC timestamp.
const localDateTime = (timestamp) =>
{
    const date = new Date(timestamp);
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
};
const route = () => location.hash.slice(1) || 'menu';
let toastTimer;
let renderVersion = 0;
let checkoutKey = null;

function toast(message)
{
    clearTimeout(toastTimer);
    // Place feedback inside the active modal so the browser's top layer cannot hide errors.
    const surface = document.querySelector('dialog[open]') || document.body;
    surface.appendChild($('#toast'));
    $('#toast').textContent = message;
    $('#toast').hidden = false;
    toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 5000);
}

async function api(action, data)
{
    const options = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (data !== undefined)
    {
        options.method = 'POST';
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-CSRF-Token'] = state.csrf;
        options.body = JSON.stringify(data);
    }
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
    let result;
    try
    {
        result = await response.json();
    }
    catch
    {
        throw new Error('The server is not ready. Check the PHP configuration and database setup.');
    }
    if (!response.ok)
    {
        throw new Error(result.error || 'The request failed. Please try again.');
    }
    return result;
}

async function loadSession()
{
    const data = await api('bootstrap');
    state.user = data.user;
    state.csrf = data.csrf;
    state.vendors = data.vendors;
    state.sso = data.sso_enabled;
    state.paymentOptions = data.payment_options || { eft: null, payfast: false, demo: false };
    document.documentElement.dataset.theme = state.user?.theme || 'light';
    $('#account-button').textContent = state.user ? state.user.name.split(' ')[0] : 'Sign in';
    $('#admin-nav').hidden = state.user?.role !== 'admin';
    await loadCart();
}

async function loadCart()
{
    state.cart = state.user ? (await api('cart')).items : [];
    $('#basket-count').textContent = state.cart.reduce((sum, item) => sum + Number(item.quantity), 0);
}

function signInView(register = false)
{
    $('#auth-content').innerHTML = `<span class="eyebrow">WELCOME TO CAMPUSEATS</span><h2 id="auth-title">${register ? 'Your next break starts here.' : 'Good to see you again.'}</h2><p class="hint">${register ? 'One account for the website and Android app.' : 'Sign in to order, save your basket and manage your account.'}</p>
    <form id="auth-form" data-register="${register}">
        ${register ? '<label>Full name<input name="name" autocomplete="name" maxlength="100" required></label>' : ''}
        <label>Email address<input name="email" type="email" autocomplete="email" maxlength="190" required></label>
        <label>Password<input name="password" type="password" autocomplete="${register ? 'new-password' : 'current-password'}" minlength="12" maxlength="72" required></label>
        ${register ? '<label>Account type<select name="role" id="register-role"><option value="student">Student</option><option value="lecturer">Lecturer</option><option value="admin">Administrator (approval required)</option></select></label><label id="number-label">Student number<input name="student_number" maxlength="40" required></label><p class="hint">Student discounts start after your student number is verified. Administrator requests require approval by an existing administrator.</p>' : ''}
        <button type="submit">${register ? 'Create account' : 'Sign in'}</button>
    </form>
    ${state.sso ? '<p class="hint">Or use your existing Google identity.</p><a class="button secondary full sso-link" href="sso.php">Continue with Google</a>' : '<p class="hint">Google single sign-on is available after the server owner configures it.</p>'}
    <p class="auth-switch">${register ? 'Already registered?' : 'New to campus?'} <button class="text-button" data-auth="${!register}">${register ? 'Sign in' : 'Create an account'}</button></p>`;
    if (!$('#auth-dialog').open)
    {
        $('#auth-dialog').showModal();
    }
}

function menuView()
{
    $('#content').innerHTML = `<section class="hero"><div class="hero-copy"><span class="eyebrow">FRESH FOOD. FAMILIAR FACES.</span><h1>Good food.<br><span>Between classes.</span></h1><p>Your favourite campus kitchens, all in one place. Order ahead. Collect. Enjoy.</p><a class="button" href="#menu" id="browse-menu">Find your next bite ↗</a></div><img src="assets/food/burger.jpg" alt="Beef burger with lettuce and tomato"></section>
    <div class="info-strip"><span><strong>4</strong> campus kitchens</span><span><strong>10% off</strong> for verified students</span><span><strong>Order online</strong> for campus collection</span></div>
    <div class="section-head" id="menu-start"><div><h2>What are you craving?</h2><p>Something for every break, appetite and budget.</p></div><input class="search" id="search" aria-label="Search food" placeholder="Search your next favourite…" value="${escapeHtml(state.search)}"></div>
    <div class="filters">${['All', ...new Set(state.products.map((p) => p.category))].map((c) => `<button class="${state.category === c ? 'selected' : ''}" data-category="${escapeHtml(c)}">${escapeHtml(c)}</button>`).join('')}<select id="vendor-filter" class="vendor-select" aria-label="Filter by campus vendor"><option value="">All campus kitchens</option>${state.vendors.map((v) => `<option value="${v.id}" ${String(v.id) === state.vendor ? 'selected' : ''}>${escapeHtml(v.name)}${Number(v.is_open) ? '' : ' (closed)'}</option>`).join('')}</select></div><div class="food-grid" id="food-grid"></div>`;
    renderProducts();
}

function renderProducts()
{
    const products = state.products.filter((p) => (state.category === 'All' || p.category === state.category) && (!state.vendor || String(p.vendor_id) === state.vendor) && `${p.name} ${p.description} ${p.vendor_name}`.toLowerCase().includes(state.search.toLowerCase()));
    $('#food-grid').innerHTML = products.length ? products.map((p) => `<article class="food-card"><div class="food-image"><img src="assets/food/${escapeHtml(p.image)}" alt="Illustrative ${escapeHtml(p.category.toLowerCase())} photograph" loading="lazy"><span class="pill">${Number(p.is_open) ? (Number(p.stock) ? `${p.stock} available` : 'Sold out') : 'Kitchen closed'}</span></div><div class="food-body"><div class="vendor-name">${escapeHtml(p.vendor_name)}</div><h3>${escapeHtml(p.name)}</h3><p class="description">${escapeHtml(p.description)}</p><div class="price-line"><div><strong>${money(p.price_cents)}</strong><small>${state.user?.role === 'student' && Number(state.user.verified) ? money(Number(p.price_cents) - Math.round(Number(p.price_cents) / 10)) + ' student price' : 'Per item'}</small></div><button class="add-button" aria-label="Add ${escapeHtml(p.name)} to basket" data-add="${p.id}" ${!Number(p.stock) || !Number(p.is_open) ? 'disabled' : ''}>+</button></div></div></article>`).join('') : '<div class="panel empty">No food matches your search. Try a different category.</div>';
}

function basketView()
{
    if (!state.user)
    {
        signInView();
        return;
    }
    const subtotal = state.cart.reduce((sum, p) => sum + Number(p.price_cents) * Number(p.quantity), 0);
    const discount = state.user.role === 'student' && Number(state.user.verified) ? Math.round(subtotal / 10) : 0;
    const currentTime = Date.now();
    const defaultPickup = localDateTime(currentTime + 20 * 60000);
    const earliestPickup = localDateTime(currentTime + 5 * 60000);
    const latestPickup = localDateTime(currentTime + 24 * 60 * 60000);
    const methods = [
        state.paymentOptions.payfast ? '<option value="payfast">PayFast secure online payment</option>' : '',
        state.paymentOptions.demo ? '<option value="demo">Card or Instant EFT · no charge</option>' : ''
    ].filter(Boolean).join('');
    $('#basket-content').innerHTML = `<span class="eyebrow">YOUR NEXT CAMPUS BREAK</span><h2 id="basket-title">Your basket</h2>${state.cart.length ? state.cart.map((p) => `<div class="cart-item"><img src="assets/food/${escapeHtml(p.image)}" alt=""><div><h3>${escapeHtml(p.name)}</h3><p>${money(p.price_cents)} · ${escapeHtml(p.vendor_name)}</p></div><div class="quantity"><button data-quantity="${p.id}" data-value="${Number(p.quantity) - 1}" aria-label="Remove one ${escapeHtml(p.name)}">−</button><span>${p.quantity}</span><button data-quantity="${p.id}" data-value="${Number(p.quantity) + 1}" aria-label="Add one ${escapeHtml(p.name)}">+</button></div></div>`).join('') + `<div class="totals"><div><span>Subtotal</span><span>${money(subtotal)}</span></div><div><span>Student discount (10%)</span><span>−${money(discount)}</span></div><div class="total"><span>Total</span><span>${money(subtotal - discount)}</span></div></div>${state.user.role === 'student' && !Number(state.user.verified) ? '<p class="notice">You can place an order now. The 10% student discount starts after student-number verification.</p>' : ''}<form id="checkout-form"><label>Collection time (your device time)<input type="datetime-local" name="pickup_at" value="${defaultPickup}" min="${earliestPickup}" max="${latestPickup}" required></label><label>Order notes<textarea name="notes" maxlength="500" placeholder="Anything the kitchen should know?"></textarea></label><p class="hint">Complete checkout before collection. Your collection number appears after account confirmation. ${state.paymentOptions.demo && !state.paymentOptions.payfast ? 'No money is charged for this coursework checkout.' : 'Payment is handled by the selected provider.'} For allergies, confirm ingredients with the vendor before ordering.</p>${methods ? `<label>Payment method<select name="payment_method" required>${methods}</select></label><button type="submit">${state.paymentOptions.demo && !state.paymentOptions.payfast ? 'Continue to checkout' : 'Continue to payment'} · ${money(subtotal - discount)}</button>` : '<p class="notice">Checkout is not enabled for this account. Ask the project administrator for access.</p>'}</form>` : '<div class="empty"><p>Your basket is waiting for something good.</p><button data-close="basket-dialog">Explore the menu</button></div>'}`;
    if (!$('#basket-dialog').open)
    {
        $('#basket-dialog').showModal();
    }
}

/**
 * Display a simulated payment screen for an existing unpaid demo order.
 * This reuses the normal My Orders API and deliberately collects no real
 * bank account or card details. Server-side payment confirmation remains
 * restricted to local, authenticated test requests.
 */
async function demoPaymentView(orderId)
{
    if (!state.paymentOptions.demo)
    {
        throw new Error('Checkout is not enabled for this account.');
    }
    const data = await api('orders');
    const order = data.orders.find((entry) => Number(entry.id) === Number(orderId));
    if (!order || order.payment_method !== 'demo' || order.payment_status !== 'unpaid' || order.status === 'cancelled')
    {
        throw new Error('This order is not awaiting payment. Refresh My orders.');
    }

    const lines = order.items.map((item) =>
        `<div class="demo-payment-line"><span>${Number(item.quantity)} × ${escapeHtml(item.name)}</span><strong>${money(Number(item.quantity) * Number(item.price_cents))}</strong></div>`
    ).join('');

    $('#payment-demo-content').innerHTML = `
        <span class="eyebrow">CAMPUSEATS SECURE CHECKOUT</span>
        <h2 id="payment-demo-title">Review your checkout</h2>
        <p class="demo-payment-warning">No money is charged. Card and EFT are checkout choices only. Do not enter banking or card details.</p>
        <div class="demo-payment-summary">
            <div class="demo-payment-line"><span>Order</span><strong>#${Number(order.id)}</strong></div>
            ${lines}
            <div class="demo-payment-line"><span>Subtotal</span><span>${money(order.subtotal_cents)}</span></div>
            <div class="demo-payment-line"><span>Student discount</span><span>−${money(order.discount_cents)}</span></div>
            <div class="demo-payment-line demo-payment-total"><span>Order total</span><strong>${money(order.total_cents)}</strong></div>
        </div>
        <form id="demo-payment-form" data-order-id="${Number(order.id)}">
            <fieldset class="demo-payment-methods">
                <legend>Choose payment method</legend>
                <label class="demo-payment-option"><input type="radio" name="demo_method" value="card" checked> Card <small>No card details needed.</small></label>
                <label class="demo-payment-option"><input type="radio" name="demo_method" value="eft"> Instant EFT <small>No banking login needed.</small></label>
            </fieldset>
            <fieldset class="payment-identity">
                <legend>Confirm your CampusEats account</legend>
                <label>Email address<input type="email" name="email" autocomplete="username" value="${escapeHtml(state.user.email)}" maxlength="190" required></label>
                <label>CampusEats password<input type="password" name="password" autocomplete="current-password" required></label>
            </fieldset>
            <button type="submit" class="full">Confirm order · ${money(order.total_cents)}</button>
            <p class="hint">No money is transferred. Your collection number appears after you confirm your account. No administrator approval is needed.</p>
        </form>
        <button class="secondary full" type="button" data-close="payment-demo-dialog">Return to orders</button>`;
    if (!$('#payment-demo-dialog').open)
    {
        $('#payment-demo-dialog').showModal();
    }
}

/** Confirm the CampusEats login before generating a real PayFast hand-off. */
async function onlinePaymentLoginView(orderId)
{
    const orders = (await api('orders')).orders;
    const order = orders.find((entry) => Number(entry.id) === Number(orderId));
    if (!order || order.payment_method !== 'payfast' || order.payment_status !== 'unpaid'
        || order.status === 'cancelled')
    {
        throw new Error('This order cannot proceed to online payment.');
    }

    $('#payment-demo-content').innerHTML = `
        <span class="eyebrow">CAMPUSEATS SECURE CHECKOUT</span>
        <h2 id="payment-demo-title">Verify account to continue</h2>
        <p>Order #${Number(order.id)} · Total ${money(order.total_cents)}</p>
        <p class="hint">Re-enter your CampusEats login details before continuing to the external payment provider. Never enter your bank password here.</p>
        <form id="online-payment-login-form" data-order-id="${Number(order.id)}">
            <fieldset class="payment-identity">
                <legend>Account verification</legend>
                <label>Email address<input name="email" type="email" autocomplete="username" maxlength="190" value="${escapeHtml(state.user.email)}" required></label>
                <label>CampusEats password<input name="password" type="password" autocomplete="current-password" required></label>
            </fieldset>
            <button type="submit" class="full">Continue to secure payment · ${money(order.total_cents)}</button>
        </form>`;
    if (!$('#payment-demo-dialog').open)
    {
        $('#payment-demo-dialog').showModal();
    }
}

/** Show a receipt after the server marks an authenticated demo order as paid. */
function demoReceiptView(orderId, collectionNumber, amount)
{
    $('#payment-demo-content').innerHTML = `
        <span class="eyebrow">CAMPUSEATS ORDER CONFIRMATION</span>
        <h2 id="payment-demo-title">Order confirmed</h2>
        <p class="demo-payment-warning">No money was charged. This order confirmation is for your coursework project, not proof of a bank payment.</p>
        <div class="demo-payment-receipt" role="status">
            <span class="eyebrow">YOUR COLLECTION NUMBER</span>
            <strong class="demo-collection-code">${escapeHtml(collectionNumber)}</strong>
            <span>Order #${Number(orderId)} · Amount ${money(amount)}</span>
            <span>Checkout status: Confirmed · no charge</span>
        </div>
        <p class="hint">Your collection number is also available under My orders.</p>
        <button type="button" class="full" data-close="payment-demo-dialog">View my order</button>`;
}

function orderCard(order, admin = false)
{
    const stages = ['placed', 'preparing', 'ready', 'collected'];
    const next = { placed: 'preparing', preparing: 'ready', ready: 'collected' };
    const paid = order.payment_status === 'paid';
    const pending = !paid && order.status !== 'cancelled';
    const method = order.payment_method || 'legacy';
    const demo = method === 'demo';
    const collection = paid ? `COLLECTION #CE${String(order.id).padStart(6, '0')}` : (demo ? 'CHECKOUT PENDING' : 'PAYMENT PENDING');
    // Prior EFT orders are not silently marked paid. They may be cancelled and re-ordered.
    const bankDetails = pending && method === 'eft' && !admin
        ? '<p class="notice">This old EFT order requires manual handling. Cancel it and place a new order using automatic online payment. No collection number has been issued.</p>' : '';
    const demoPayment = pending && demo && !admin
        ? `<p class="notice"><strong>No money is charged.</strong> Confirm your CampusEats account to receive a collection number.</p><button data-demo-pay="${order.id}">Continue to checkout</button>` : '';
    const online = pending && method === 'payfast' && !admin ? `<p class="notice">Online payment not yet confirmed. Use Pay online or refresh after returning from PayFast.</p><button data-pay="${order.id}">Pay online</button>` : '';
    return `<article class="panel"><div class="order-top"><div><span class="eyebrow">${collection}</span><h3>${dateText(order.pickup_at)}</h3><span class="hint">Collection time · South Africa</span>${admin ? `<p class="hint">Internal order ${order.id} · ${escapeHtml(order.customer)} · ${escapeHtml(order.email)}</p>` : ''}</div><div><span class="pill">${escapeHtml(order.status)}</span><h3>${money(order.total_cents)}</h3><span class="hint">${paid ? (demo ? 'Confirmed · No charge (coursework)' : 'Paid · ' + (method === 'payfast' ? 'PayFast verified' : 'Legacy payment')) : (demo ? 'Awaiting checkout' : 'Awaiting payment')}</span></div></div>${pending ? `<p class="notice">${demo ? 'Complete checkout before food preparation and collection.' : 'Payment is required before food preparation and collection.'}</p>` : ''}${paid && order.status !== 'cancelled' ? `<div class="progress">${stages.map((stage, index) => `<span class="${index <= stages.indexOf(order.status) ? 'done' : ''}">${stage}</span>`).join('')}</div>` : ''}${order.status === 'cancelled' ? '<p class="notice">Cancelled. Stock returned to the kitchens.</p>' : ''}<ul class="order-items">${order.items.map((item) => `<li>${item.quantity} × ${escapeHtml(item.name)} · ${money(Number(item.price_cents) * Number(item.quantity))}<small>${escapeHtml(item.vendor_name)} · ${escapeHtml(item.location)}</small></li>`).join('')}</ul>${order.notes ? `<p class="hint">Notes: ${escapeHtml(order.notes)}</p>` : ''}<p class="hint">Subtotal ${money(order.subtotal_cents)} · Student savings ${money(order.discount_cents)}</p>${bankDetails}${online}${demoPayment}<div class="row">${admin && paid && next[order.status] ? `<button data-status="${order.id}" data-value="${next[order.status]}">${order.status === 'ready' ? 'Confirm collection' : 'Mark ' + next[order.status]}</button>` : ''}${!paid && ((admin && ['placed', 'preparing'].includes(order.status)) || (!admin && order.status === 'placed')) ? `<button class="danger" data-cancel="${order.id}" data-admin="${admin}">Cancel pending order</button>` : ''}</div></article>`;
}

function settingsView()
{
    const u = state.user;
    $('#content').innerHTML = `<div class="section-head"><div><span class="eyebrow">MAKE YOURSELF AT HOME</span><h1>My account</h1><p>Your profile and preferences sync across the website and app.</p></div><button class="secondary" id="logout">Sign out</button></div><div class="two-col"><section class="panel"><h2>Personal details</h2><form id="settings-form"><label>Full name<input name="name" value="${escapeHtml(u.name)}" maxlength="100" required></label><label>Email<input value="${escapeHtml(u.email)}" disabled></label><label>Phone number<input name="phone" type="tel" maxlength="30" value="${escapeHtml(u.phone)}"></label><label>Student number<input name="student_number" maxlength="40" value="${escapeHtml(u.student_number)}" ${u.role === 'student' ? 'required' : ''}></label><label>Appearance<select name="theme"><option value="light" ${u.theme === 'light' ? 'selected' : ''}>Light</option><option value="dark" ${u.theme === 'dark' ? 'selected' : ''}>Dark</option></select></label><label class="check"><input type="checkbox" name="order_updates" ${Number(u.order_updates) ? 'checked' : ''}>Show order updates while I use CampusEats</label><p class="hint">Order updates appear in the app or browser while open. Changing your student number requires verification again.</p><button>Save changes</button></form></section><div><section class="panel"><h2>Your campus role</h2><p><span class="pill">${escapeHtml(u.role)}</span> <span class="pill">${Number(u.verified) ? 'Verified' : 'Awaiting verification'}</span></p><p class="hint">Requested role: ${escapeHtml(u.requested_role)}. Verified students receive 10% off food orders.</p>${u.role !== 'admin' ? `<form id="role-form"><label>Request account type<select name="role">${['student','lecturer','admin'].map((r) => `<option value="${r}" ${r === u.requested_role ? 'selected' : ''}>${r === 'admin' ? 'Administrator (approval required)' : r[0].toUpperCase() + r.slice(1)}</option>`).join('')}</select></label><label>Student number (required for students)<input name="student_number" value="${escapeHtml(u.student_number)}" maxlength="40"></label><button class="secondary">Submit role request</button></form>` : '<p class="hint">You can manage food, users, vendors and orders in Administration.</p>'}</section><section class="panel"><h2>Security</h2><form id="password-form"><label>Current password<input type="password" name="current_password" autocomplete="current-password"></label><label>New password<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label><p class="hint">Use 12 to 72 characters. For an SSO-only account, leave the current password blank to set a password.</p><button>Update password</button></form>${state.sso ? '<p class="hint">Link the Google account with the same email. For Android, complete initial linking in your system browser while signed into this account.</p><a class="button secondary full sso-link" href="sso.php">Link Google identity</a>' : ''}</section></div></div>`;
}

function adminView()
{
    const data = state.admin;
    const tabs = ['orders', 'products', 'users', 'vendors'];
    let body = '';
    if (state.adminTab === 'orders')
    {
        body = data.orders.length ? data.orders.map((o) => orderCard(o, true)).join('') : '<div class="panel empty">Orders will appear here.</div>';
    }
    if (state.adminTab === 'products')
    {
        body = `<div class="section-head"><h2>Food & inventory</h2><button id="new-product">Add food item</button></div><div class="panel table-wrap"><table><thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Stock</th><th>Visible</th><th>Manage</th></tr></thead><tbody>${data.products.map((p) => `<tr><td>${escapeHtml(p.name)}</td><td>${escapeHtml(p.category)}</td><td>${money(p.price_cents)}</td><td>${p.stock}</td><td>${Number(p.active) ? 'Yes' : 'No'}</td><td><button data-edit-product="${p.id}">Edit</button></td></tr>`).join('')}</tbody></table></div>`;
    }
    if (state.adminTab === 'users')
    {
        body = `<p class="notice">Verify student numbers against campus records. Only approve administrator requests from authorised staff.</p><div class="panel table-wrap"><table><thead><tr><th>Name / email</th><th>Role</th><th>Requested</th><th>Student number</th><th>Verification</th></tr></thead><tbody>${data.users.map((u) => `<tr><td>${escapeHtml(u.name)}<br><span class="hint">${escapeHtml(u.email)}</span></td><td>${escapeHtml(u.role)}</td><td>${escapeHtml(u.requested_role)}</td><td>${escapeHtml(u.student_number) || 'N/A'}</td><td>${Number(u.verified) ? 'Verified' : `<button data-approve="${u.id}">Approve ${escapeHtml(u.requested_role)}</button>`}</td></tr>`).join('')}</tbody></table></div>`;
    }
    if (state.adminTab === 'vendors')
    {
        body = `<div class="two-col">${state.vendors.map((v) => `<section class="panel"><h2>${escapeHtml(v.name)}</h2><form class="vendor-form"><input type="hidden" name="id" value="${v.id}"><label>Vendor name<input name="name" value="${escapeHtml(v.name)}" maxlength="100" required></label><label>Collection location<input name="location" value="${escapeHtml(v.location)}" maxlength="150" required></label><label class="check"><input type="checkbox" name="is_open" ${Number(v.is_open) ? 'checked' : ''}>Accepting orders</label><button>Save vendor</button></form></section>`).join('')}</div>`;
    }
    $('#content').innerHTML = `<div class="section-head"><div><span class="eyebrow">CAMPUS KITCHEN CONTROL</span><h1>Administration</h1></div><button class="secondary" id="refresh">Refresh</button></div><div class="stats"><div class="panel"><span class="hint">Total orders</span><strong>${data.stats.orders}</strong></div><div class="panel"><span class="hint">Verified payments</span><strong>${money(data.stats.revenue_cents)}</strong></div><div class="panel"><span class="hint">Food items</span><strong>${data.products.length}</strong></div></div><div class="filters">${tabs.map((t) => `<button data-admin-tab="${t}" class="${state.adminTab === t ? 'selected' : ''}">${t[0].toUpperCase() + t.slice(1)}</button>`).join('')}</div>${body}`;
}

function productEditor(id)
{
    const p = state.admin.products.find((p) => Number(p.id) === id) || { id: 0, name: '', description: '', vendor_id: 1, category: 'Meals', stock: 0, price_cents: 0, image: 'burger.jpg', active: 1 };
    $('#edit-content').innerHTML = `<h2 id="edit-title">${id ? 'Edit food item' : 'Add food item'}</h2><form id="product-form"><input type="hidden" name="id" value="${p.id}"><label>Name<input name="name" value="${escapeHtml(p.name)}" maxlength="120" required></label><label>Description & allergens<textarea name="description" maxlength="500" required>${escapeHtml(p.description)}</textarea></label><label>Vendor<select name="vendor_id">${state.vendors.map((v) => `<option value="${v.id}" ${Number(v.id) === Number(p.vendor_id) ? 'selected' : ''}>${escapeHtml(v.name)}</option>`).join('')}</select></label><label>Category<input name="category" value="${escapeHtml(p.category)}" maxlength="40" required></label><div class="two-col"><label>Selling price (ZAR)<input type="number" name="price" min="0.01" max="10000" step="0.01" value="${(p.price_cents / 100).toFixed(2)}" required></label><label>Available quantity<input type="number" name="stock" min="0" max="100000" value="${p.stock}" required></label></div><label>Food image<select name="image">${state.admin.images.map((im) => `<option value="${escapeHtml(im)}" ${im === p.image ? 'selected' : ''}>${escapeHtml(im)}</option>`).join('')}</select></label><p class="hint">The server owner can add vendor photographs to web/assets/food. Use a .jpg filename.</p><label class="check"><input type="checkbox" name="active" ${Number(p.active) ? 'checked' : ''}>Show on menu</label><button>Save food item</button></form>`;
    $('#edit-dialog').showModal();
}

async function render()
{
    const version = ++renderVersion;
    const page = route();
    document.querySelectorAll('[data-nav]').forEach((a) => a.classList.toggle('active', a.dataset.nav === page));
    if (page !== 'menu' && !state.user)
    {
        $('#content').innerHTML = '<section class="panel empty"><h1>Your campus, connected.</h1><p>Sign in to view orders and manage your account.</p><button id="open-login">Sign in</button></section>';
        return;
    }
    if (page === 'orders')
    {
        const data = await api('orders');
        if (version !== renderVersion) return;
        $('#content').innerHTML = `<div class="section-head"><div><span class="eyebrow">FROM KITCHEN TO COLLECTION</span><h1>My orders</h1><p>Track your food and see where to collect it.</p></div><button class="secondary" id="refresh">Refresh</button></div>${data.orders.length ? data.orders.map((o) => orderCard(o)).join('') : '<section class="panel empty"><h2>No orders yet</h2><p>Find something you love on the menu.</p><a class="button" href="#menu">Explore food</a></section>'}`;
    }
    else if (page === 'settings')
    {
        settingsView();
    }
    else if (page === 'admin')
    {
        if (state.user.role !== 'admin')
        {
            $('#content').innerHTML = '<div class="panel"><h2>Administrator access required</h2><p>Your request must be approved by an existing administrator.</p></div>';
            return;
        }
        const data = await api('admin');
        if (version !== renderVersion) return;
        state.admin = data;
        adminView();
    }
    else
    {
        const data = await api('catalog');
        if (version !== renderVersion) return;
        state.products = data.products;
        menuView();
    }
}

/** Central event delegation also handles views inserted after navigation. */
document.addEventListener('click', async (event) =>
{
    const element = event.target.closest('button,a');
    if (!element) return;
    try
    {
        if (element.dataset.close)
        {
            document.getElementById(element.dataset.close).close();
        }
        if (element.id === 'account-button')
        {
            if (state.user) location.hash = 'settings'; else signInView();
        }
        if (element.id === 'open-login') signInView();
        if (element.dataset.auth) signInView(element.dataset.auth === 'true');
        if (element.id === 'basket-button')
        {
            await loadCart();
            basketView();
        }
        if (element.id === 'browse-menu')
        {
            event.preventDefault();
            $('#menu-start').scrollIntoView({ behavior: 'smooth' });
        }
        if (element.dataset.category)
        {
            state.category = element.dataset.category;
            document.querySelectorAll('[data-category]').forEach((b) => b.classList.toggle('selected', b.dataset.category === state.category));
            renderProducts();
        }
        if (element.dataset.add)
        {
            if (!state.user) return signInView();
            element.disabled = true;
            await loadCart();
            const existing = state.cart.find((p) => String(p.id) === element.dataset.add);
            await api('cart_set', { product_id: Number(element.dataset.add), quantity: (Number(existing?.quantity) || 0) + 1 });
            await loadCart();
            toast('Added to your basket.');
        }
        if (element.dataset.quantity)
        {
            element.disabled = true;
            await api('cart_set', { product_id: Number(element.dataset.quantity), quantity: Number(element.dataset.value) });
            checkoutKey = null;
            await loadCart();
            basketView();
        }
        if (element.id === 'logout')
        {
            await api('logout', {});
            checkoutKey = null;
            await loadSession();
            location.hash = 'menu';
            await render();
        }
        if (element.id === 'refresh')
        {
            await loadSession();
            await render();
        }
        if (element.dataset.pay)
        {
            // Ask for CampusEats credentials before redirecting to the provider.
            await onlinePaymentLoginView(Number(element.dataset.pay));
            return;
        }
        if (element.dataset.demoPay)
        {
            // The order remains unpaid until the user confirms within the demo checkout.
            await demoPaymentView(Number(element.dataset.demoPay));
        }
        if (element.dataset.cancel)
        {
            if (!confirm('Cancel this order and return its items to stock?')) return;
            await api(element.dataset.admin === 'true' ? 'order_status' : 'cancel', { id: Number(element.dataset.cancel), status: 'cancelled' });
            await render();
            toast('Order cancelled.');
        }
        if (element.dataset.status)
        {
            if (element.dataset.value === 'collected' && !confirm('Confirm that the paid order was handed over using its order number?')) return;
            await api('order_status', { id: Number(element.dataset.status), status: element.dataset.value });
            await render();
        }
        if (element.dataset.adminTab)
        {
            state.adminTab = element.dataset.adminTab;
            adminView();
        }
        if (element.dataset.editProduct || element.id === 'new-product') productEditor(Number(element.dataset.editProduct) || 0);
        if (element.dataset.approve)
        {
            if (!confirm('Confirm that you have verified this person and their requested role?')) return;
            await api('user_approve', { id: Number(element.dataset.approve) });
            await render();
        }
    }
    catch (error)
    {
        toast(error.message);
    }
    finally
    {
        if (element.tagName === 'BUTTON') element.disabled = false;
    }
});

document.addEventListener('input', (event) =>
{
    if (event.target.id === 'search')
    {
        state.search = event.target.value;
        renderProducts();
    }
});
document.addEventListener('change', (event) =>
{
    if (event.target.id === 'vendor-filter')
    {
        state.vendor = event.target.value;
        renderProducts();
    }
    if (event.target.id === 'register-role')
    {
        $('#number-label').hidden = event.target.value !== 'student';
        $('#number-label input').required = event.target.value === 'student';
    }
});

document.addEventListener('submit', async (event) =>
{
    event.preventDefault();
    const form = event.target;
    const data = Object.fromEntries(new FormData(form));
    const button = form.querySelector('button[type="submit"],button:not([type])');
    if (button) button.disabled = true;
    try
    {
        if (form.id === 'auth-form')
        {
            await api(form.dataset.register === 'true' ? 'register' : 'login', data);
            $('#auth-dialog').close();
            await loadSession();
            await render();
            toast('Welcome to CampusEats.');
        }
        if (form.id === 'demo-payment-form')
        {
            // Both visible options simulate the SAME local-only API action.
            // No card or banking information is ever sent to the PHP server.
            const orderId = Number(form.dataset.orderId);
            const matchingOrder = (await api('orders')).orders.find((o) => Number(o.id) === orderId);
            if (!matchingOrder || matchingOrder.payment_method !== 'demo')
            {
                throw new Error('This order cannot use this checkout method.');
            }
            const result = await api('payment_demo_complete', {
                id: orderId,
                email: data.email,
                password: data.password
            });
            // Clear submitted credentials immediately after successful verification.
            form.reset();
            await render();
            demoReceiptView(orderId, result.collection_number, matchingOrder.total_cents);
        }
        if (form.id === 'online-payment-login-form')
        {
            const payment = await api('payment_start', {
                id: Number(form.dataset.orderId),
                email: data.email,
                password: data.password
            });
            form.reset();
            window.location.assign(payment.payment_url);
            return;
        }
        if (form.id === 'checkout-form')
        {
            // Preserve the idempotency key on failure so a retry cannot double-order.
            checkoutKey ||= Array.from(crypto.getRandomValues(new Uint8Array(16)), (v) => v.toString(16).padStart(2, '0')).join('');
            data.request_key = checkoutKey;
            data.pickup_at = new Date(data.pickup_at).toISOString();
            const result = await api('checkout', data);
            checkoutKey = null;
            $('#basket-dialog').close();
            await loadCart();
            location.hash = 'orders';
            await render();
            toast('Complete checkout to receive your collection number.');
            if (data.payment_method === 'demo')
            {
                // Open the test checkout automatically, after the ordinary order is created.
                // A new order is still unpaid until the customer confirms the simulation.
                await demoPaymentView(result.order_id);
            }
            if (data.payment_method === 'payfast')
            {
                await onlinePaymentLoginView(result.order_id);
            }
        }
        if (form.id === 'settings-form')
        {
            data.order_updates = form.elements.order_updates.checked;
            await api('settings', data);
            await loadSession();
            await render();
            toast('Your settings have been saved.');
        }
        if (form.id === 'password-form')
        {
            await api('password', data);
            form.reset();
            toast('Password updated.');
        }
        if (form.id === 'role-form')
        {
            await api('role_request', data);
            await loadSession();
            await render();
            toast('Role request submitted for verification.');
        }
        if (form.id === 'product-form')
        {
            data.price_cents = Math.round(Number(data.price) * 100);
            data.stock = Number(data.stock);
            data.id = Number(data.id);
            data.vendor_id = Number(data.vendor_id);
            data.active = form.elements.active.checked;
            await api('product_save', data);
            $('#edit-dialog').close();
            await render();
            toast('Food item saved.');
        }
        if (form.classList.contains('vendor-form'))
        {
            data.id = Number(data.id);
            data.is_open = form.elements.is_open.checked;
            await api('vendor_save', data);
            await loadSession();
            await render();
            toast('Vendor updated.');
        }
    }
    catch (error)
    {
        toast(error.message);
    }
    finally
    {
        if (button) button.disabled = false;
    }
});
window.addEventListener('hashchange', () => render().catch((e) => toast(e.message)));
// Poll only while visible. This is an in-app update, not a background push service.
const knownOrders = new Map();
setInterval(async () =>
{
    if (!state.user || !Number(state.user.order_updates) || document.hidden) return;
    try
    {
        const data = await api('orders');
        for (const order of data.orders)
        {
            if (knownOrders.has(order.id) && (knownOrders.get(order.id).status !== order.status || knownOrders.get(order.id).paid !== order.payment_status))
            {
                toast(order.payment_status === 'paid' ? `Payment confirmed. Collection number CE${String(order.id).padStart(6, '0')}.` : `Your order status is now ${order.status}.`);
                if (route() === 'orders') await render();
            }
            knownOrders.set(order.id, { status: order.status, paid: order.payment_status });
        }
    }
    catch
    {
        // A transient connection failure will be retried at the next interval.
    }
}, 15000);
(async () =>
{
    try
    {
        await loadSession();
        await render();
        const parameters = new URLSearchParams(location.search);
        if (parameters.has('payment'))
        {
            toast('Payment status is verified by the server, not by returning from the gateway. Refresh My orders to check.');
        }
    }
    catch (error)
    {
        $('#content').innerHTML = `<section class="panel"><h1>Let’s get connected.</h1><p>${escapeHtml(error.message)}</p><button id="refresh">Try again</button></section>`;
    }
})();
