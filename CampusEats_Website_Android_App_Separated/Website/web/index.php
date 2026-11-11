<?php
/** Public interface. All data and mutations pass through the shared PHP API. */
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#173d32">
    <title>CampusEats | Good food. For everyone.</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js?v=<?= (int)filemtime(__DIR__ . '/assets/app.js') ?>" defer></script>
</head>
<body>
    <aside class="sidebar">
        <a class="brand" href="#menu"><span class="brand-icon">C<span>e</span></span><span>Campus<span class="accent">Eats</span><small>GOOD FOOD FOR EVERYONE</small></span></a>
        <div class="kitchen-label"><span class="live-dot"></span> Your favourite kitchens</div>
        <nav aria-label="Main navigation">
            <a href="#menu" data-nav="menu"><span>▦</span> Explore menu</a>
            <a href="#orders" data-nav="orders"><span>▤</span> My orders</a>
            <a href="#settings" data-nav="settings"><span>⚙</span> My account</a>
            <a href="#admin" data-nav="admin" id="admin-nav" hidden><span>◇</span> Administration</a>
        </nav>
        <div class="order-card"><span class="eyebrow">YOUR NEXT GOOD MEAL</span><strong>Order ahead.<br>Collect and enjoy.</strong><p>Find something delicious for every appetite.</p><a href="#menu">Explore the menu ↗</a></div>
        <div class="sidebar-footer"><span>Good food brings us together.<small>Fresh choices. Easy ordering.</small></span></div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div><span class="eyebrow">ORDER. COLLECT. ENJOY.</span><p>CampusEats <span class="muted">/ Food for everyone</span></p></div><div class="top-actions"><button class="quiet" id="account-button">Sign in</button><button class="basket-button" id="basket-button">Basket <span id="basket-count">0</span></button></div></header>
        <main id="content" class="shell" tabindex="-1"><div class="panel">Loading your menu…</div></main>
        <footer class="page-footer">CampusEats · Order and collect · Prices in South African rand <span>Sample catalogue · Images are illustrative</span></footer>
    </div>
    <dialog id="auth-dialog" aria-labelledby="auth-title"><button class="close-dialog" data-close="auth-dialog" aria-label="Close">×</button><div id="auth-content"></div></dialog>
    <dialog id="basket-dialog" aria-labelledby="basket-title"><button class="close-dialog" data-close="basket-dialog" aria-label="Close">×</button><div id="basket-content"></div></dialog>
    <dialog id="edit-dialog" aria-labelledby="edit-title"><button class="close-dialog" data-close="edit-dialog" aria-label="Close">×</button><div id="edit-content"></div></dialog>
    <!-- Shared checkout confirmation dialog for simulated and provider payments. -->
    <dialog id="payment-demo-dialog" aria-labelledby="payment-demo-title"><button class="close-dialog" data-close="payment-demo-dialog" aria-label="Close payment">×</button><div id="payment-demo-content"></div></dialog>
    <div id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
