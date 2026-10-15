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
    <title>CampusEats | Good food. Between classes.</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
    <aside class="sidebar">
        <a class="brand" href="#menu"><span class="brand-icon">C<span>e</span></span><span>Campus<span class="accent">Eats</span><small>ROSEBANK COLLEGE</small></span></a>
        <div class="campus-label"><span class="live-dot"></span> Your campus kitchen</div>
        <nav aria-label="Main navigation">
            <a href="#menu" data-nav="menu"><span>▦</span> Explore menu</a>
            <a href="#orders" data-nav="orders"><span>▤</span> My orders</a>
            <a href="#settings" data-nav="settings"><span>⚙</span> My account</a>
            <a href="#admin" data-nav="admin" id="admin-nav" hidden><span>◇</span> Administration</a>
        </nav>
        <div class="student-card"><span class="eyebrow">A LITTLE STUDENT PERK</span><strong>Study hard.<br>Spend 10% less.</strong><p>Verified students save on every food order.</p><a href="#settings">Check your account ↗</a></div>
        <div class="sidebar-footer"><img src="assets/college-logo.png" alt="Rosebank College"><span>Made for campus life.<small>Food. Friends. Fresh starts.</small></span></div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div><span class="eyebrow">COLLECT ON CAMPUS</span><p>Rosebank College <span class="muted">/ CampusEats</span></p></div><div class="top-actions"><button class="quiet" id="account-button">Sign in</button><button class="basket-button" id="basket-button">Basket <span id="basket-count">0</span></button></div></header>
        <main id="content" class="shell" tabindex="-1"><div class="panel">Loading your campus menu…</div></main>
        <footer class="page-footer">CampusEats · Campus collection · Prices in South African rand <span>Sample catalogue · Images are illustrative</span></footer>
    </div>
    <dialog id="auth-dialog" aria-labelledby="auth-title"><button class="close-dialog" data-close="auth-dialog" aria-label="Close">×</button><div id="auth-content"></div></dialog>
    <dialog id="basket-dialog" aria-labelledby="basket-title"><button class="close-dialog" data-close="basket-dialog" aria-label="Close">×</button><div id="basket-content"></div></dialog>
    <dialog id="edit-dialog" aria-labelledby="edit-title"><button class="close-dialog" data-close="edit-dialog" aria-label="Close">×</button><div id="edit-content"></div></dialog>
    <div id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
