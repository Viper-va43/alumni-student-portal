<?php
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/includes/functions.php';
start_session();

$database = new Database();
$conn = $database->getConnection();

$search = trim($_GET['search'] ?? '');
$places = [];
$dbNotice = null;
$registered = ($_GET['registered'] ?? '') === '1';
$loggedInNotice = ($_GET['logged_in'] ?? '') === '1';
$loggedOutNotice = ($_GET['logged_out'] ?? '') === '1';
$loggedIn = is_logged_in();
$customerName = trim($_SESSION['customer_name'] ?? '');

if (!$conn) {
    $dbNotice = $database->lastError ?: 'Unable to connect to the Where2Go database.';
} else {
    try {
        if ($search !== '') {
            $query = "SELECT Business_Name, Physical_Address
                      FROM partners
                      WHERE Business_Name LIKE :search
                        AND Physical_Address LIKE :city
                      ORDER BY Business_Name
                      LIMIT 8";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            $stmt->bindValue(':city', '%Cairo%', PDO::PARAM_STR);
        } else {
            $query = "SELECT Business_Name, Physical_Address
                      FROM partners
                      WHERE Physical_Address LIKE :city
                      ORDER BY Business_Name
                      LIMIT 8";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':city', '%Cairo%', PDO::PARAM_STR);
        }

        $stmt->execute();
        $places = $stmt->fetchAll();
    } catch (PDOException $e) {
        $dbNotice = 'The homepage is connected to MySQL, but the Where2Go tables still need repair or re-import in phpMyAdmin before live places can load.';
        error_log('Where2Go homepage query failed: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go Cairo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root {
    color-scheme: light;
    --page-bg: radial-gradient(circle at top, rgba(242, 108, 28, 0.16), transparent 38%), linear-gradient(180deg, #fffaf5 0%, #ffffff 28%, #fff5ed 100%);
    --text: #23160c;
    --muted: #6f6156;
    --topbar-bg: rgba(255, 255, 255, 0.94);
    --surface: rgba(255, 255, 255, 0.84);
    --surface-solid: #ffffff;
    --surface-strong: rgba(255, 255, 255, 0.94);
    --surface-soft: rgba(35, 22, 12, 0.05);
    --border: rgba(35, 22, 12, 0.08);
    --shadow: rgba(35, 22, 12, 0.08);
    --hero-start: #140c07;
    --hero-mid: #4f2207;
    --hero-end: #f26c1c;
    --footer-bg: #17100a;
    --footer-text: rgba(255, 255, 255, 0.86);
    --footer-meta: rgba(255, 255, 255, 0.62);
    --input-bg: #ffffff;
    --input-text: #23160c;
    --accent: #f26c1c;
    --accent-strong: #c85108;
    --accent-soft: rgba(242, 108, 28, 0.1);
    --success-bg: #f4fff8;
    --success-border: rgba(40, 134, 85, 0.18);
    --warning-bg: #fff7f0;
    --warning-border: rgba(179, 88, 16, 0.18);
    --intro-bg: radial-gradient(circle at center, rgba(242, 108, 28, 0.2), transparent 32%), #0a0705;
}

body.dark-mode {
    color-scheme: dark;
    --page-bg: radial-gradient(circle at top, rgba(242, 108, 28, 0.22), transparent 32%), linear-gradient(180deg, #100b08 0%, #17100c 34%, #090705 100%);
    --text: #f6ede7;
    --muted: #c8b6ab;
    --topbar-bg: rgba(15, 10, 8, 0.9);
    --surface: rgba(30, 21, 17, 0.88);
    --surface-solid: rgba(22, 16, 13, 0.98);
    --surface-strong: rgba(27, 19, 15, 0.94);
    --surface-soft: rgba(255, 255, 255, 0.08);
    --border: rgba(255, 255, 255, 0.08);
    --shadow: rgba(0, 0, 0, 0.32);
    --hero-start: #090605;
    --hero-mid: #2c1407;
    --hero-end: #d95d12;
    --footer-bg: #050403;
    --footer-text: rgba(255, 255, 255, 0.86);
    --footer-meta: rgba(255, 255, 255, 0.62);
    --input-bg: rgba(22, 16, 13, 0.98);
    --input-text: #f6ede7;
    --accent: #ff8a3d;
    --accent-strong: #ffb178;
    --accent-soft: rgba(242, 108, 28, 0.16);
    --success-bg: rgba(35, 81, 58, 0.24);
    --success-border: rgba(95, 214, 149, 0.2);
    --warning-bg: rgba(104, 56, 21, 0.34);
    --warning-border: rgba(255, 164, 102, 0.2);
    --intro-bg: radial-gradient(circle at center, rgba(242, 108, 28, 0.18), transparent 30%), #050403;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: 'Sora', sans-serif;
    background: var(--page-bg);
    color: var(--text);
    transition: background 0.35s ease, color 0.35s ease;
}

body.intro-active {
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

img {
    display: block;
    max-width: 100%;
}

.site-shell {
    min-height: 100vh;
}

.intro-screen {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: grid;
    place-items: center;
    padding: 24px;
    background: var(--intro-bg);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.55s ease, visibility 0.55s ease;
    visibility: hidden;
}

.intro-screen.is-visible {
    opacity: 1;
    visibility: visible;
}

.intro-screen.is-leaving {
    opacity: 0;
}

.intro-card {
    position: relative;
    display: grid;
    justify-items: center;
    gap: 18px;
    padding: 34px 36px;
    border-radius: 34px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 36px 80px rgba(0, 0, 0, 0.35);
    transform: translateY(20px) scale(0.96);
    transition: transform 0.75s ease;
    backdrop-filter: blur(18px);
}

.intro-screen.is-visible .intro-card {
    transform: translateY(0) scale(1);
}

.intro-logo-shell {
    padding: 0;
    border-radius: 28px;
    overflow: hidden;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 24px 54px rgba(242, 108, 28, 0.18);
}

.intro-logo {
    width: min(420px, 72vw);
}

.intro-line {
    color: rgba(255, 255, 255, 0.86);
    font-size: 1rem;
    text-align: center;
}

.topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: var(--topbar-bg);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
}

.topbar-inner,
.section-inner,
.footer-inner {
    width: min(1140px, calc(100% - 32px));
    margin: 0 auto;
}

.topbar-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    padding: 18px 0;
}

.brand-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.brand {
    display: inline-flex;
    align-items: center;
}

.logo {
    height: 60px;
    width: auto;
    max-width: min(240px, 100%);
    object-fit: contain;
    display: block;
}

body.dark-mode .logo {
    filter: invert(1) hue-rotate(180deg) saturate(1.08) brightness(1.06);
}

body.light-mode .logo {
    filter: brightness(0.9);
}

.theme-toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 42px;
    padding: 0 14px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface-strong);
    color: var(--text);
    font: inherit;
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 12px 24px rgba(35, 22, 12, 0.06);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.nav-link,
.nav-cta,
.nav-muted {
    display: inline-flex;
    align-items: center;
    min-height: 40px;
    padding: 0 14px;
    border-radius: 999px;
    font-size: 0.95rem;
}

.nav-link:hover {
    background: var(--accent-soft);
    color: var(--accent-strong);
}

.nav-cta {
    background: linear-gradient(135deg, #f26c1c, #ff8c42);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 14px 28px rgba(242, 108, 28, 0.22);
}

.nav-muted {
    color: var(--muted);
    background: var(--surface-soft);
}

.hero {
    padding: 72px 0 38px;
}

.hero-card {
    position: relative;
    overflow: hidden;
    border-radius: 36px;
    padding: 42px;
    background: linear-gradient(135deg, var(--hero-start) 0%, var(--hero-mid) 38%, var(--hero-end) 100%);
    color: #fff;
    box-shadow: 0 32px 70px rgba(55, 25, 8, 0.25);
}

.hero-card::before,
.hero-card::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.hero-card::before {
    width: 220px;
    height: 220px;
    top: -70px;
    right: 90px;
}

.hero-card::after {
    width: 280px;
    height: 280px;
    right: -90px;
    bottom: -100px;
}

.hero-grid {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 24px;
    grid-template-columns: minmax(0, 1.15fr) minmax(260px, 0.85fr);
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
    margin-bottom: 18px;
}

.hero h1 {
    margin: 0 0 14px;
    max-width: 660px;
    font-size: clamp(2.5rem, 4vw, 4.6rem);
    line-height: 0.98;
    letter-spacing: -0.05em;
}

.hero p {
    margin: 0 0 28px;
    max-width: 640px;
    color: rgba(255, 255, 255, 0.82);
    font-size: 1.02rem;
    line-height: 1.7;
}

.search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    max-width: 620px;
    padding: 14px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(10px);
}

.search-field {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1 1 280px;
    padding: 0 14px;
    min-height: 54px;
    border-radius: 16px;
    background: #fff;
    color: #23160c;
}

.search-field input {
    width: 100%;
    border: 0;
    outline: 0;
    font: inherit;
    background: transparent;
    color: #23160c;
}

.search-button {
    border: 0;
    min-height: 54px;
    padding: 0 22px;
    border-radius: 16px;
    font: inherit;
    font-weight: 700;
    background: #fff;
    color: #c85108;
    cursor: pointer;
}

.hero-side {
    display: grid;
    align-content: start;
    gap: 14px;
}

.hero-side-card,
.stat-card {
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.12);
    padding: 18px;
    backdrop-filter: blur(10px);
}

.hero-side-card {
    display: grid;
    gap: 16px;
}

.hero-side-card p,
.stat-card p {
    margin: 0;
    color: rgba(255, 255, 255, 0.8);
}

.hero-logo-shell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    overflow: hidden;
    border-radius: 24px;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 20px 40px rgba(23, 16, 10, 0.18);
}

.hero-logo {
    width: min(320px, 100%);
}

.stat-row {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.stat-value {
    display: block;
    margin-bottom: 6px;
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
}

.section {
    padding: 34px 0 72px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 16px;
    margin-bottom: 24px;
}

.section h2 {
    margin: 0;
    font-size: clamp(1.7rem, 3vw, 2.5rem);
    letter-spacing: -0.04em;
}

.section-copy {
    max-width: 540px;
    color: var(--muted);
    line-height: 1.7;
}

.notice-stack {
    display: grid;
    gap: 14px;
    margin: 26px 0 0;
}

.notice {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 16px 18px;
    border-radius: 18px;
    border: 1px solid transparent;
    background: var(--surface-solid);
    box-shadow: 0 16px 34px var(--shadow);
    color: var(--text);
}

.notice strong {
    display: block;
    margin-bottom: 4px;
}

.notice-warning {
    border-color: var(--warning-border);
    background: var(--warning-bg);
}

.notice-success {
    border-color: var(--success-border);
    background: var(--success-bg);
}

.category-grid,
.place-grid,
.detail-grid {
    display: grid;
    gap: 18px;
}

.category-grid {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.category-card,
.detail-card {
    padding: 22px;
    border-radius: 22px;
    background: var(--surface);
    border: 1px solid var(--border);
    box-shadow: 0 20px 38px var(--shadow);
}

.category-card h3,
.detail-card h3 {
    margin: 14px 0 10px;
    font-size: 1.05rem;
}

.category-card p,
.detail-card p {
    margin: 0;
    color: var(--muted);
    line-height: 1.6;
}

.icon-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 16px;
    background: var(--accent-soft);
    color: var(--accent);
}

.place-grid {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.place-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 24px;
    background: var(--surface-solid);
    border: 1px solid var(--border);
    box-shadow: 0 20px 40px var(--shadow);
}

.place-media {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px;
    min-height: 120px;
    background: linear-gradient(135deg, rgba(242, 108, 28, 0.12), rgba(255, 140, 66, 0.3));
}

.place-media strong {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 78px;
    min-height: 78px;
    padding: 12px;
    border-radius: 20px;
    background: linear-gradient(135deg, #f26c1c, #ff8c42);
    color: #fff;
    font-size: 0.9rem;
    text-align: center;
    box-shadow: 0 18px 34px rgba(242, 108, 28, 0.22);
}

.place-card-body {
    padding: 22px;
}

.place-card h3 {
    margin: 0 0 10px;
    font-size: 1.1rem;
}

.place-meta {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: var(--muted);
    line-height: 1.55;
}

.inline-icon {
    flex-shrink: 0;
    margin-top: 2px;
    color: var(--accent);
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    padding: 8px 12px;
    border-radius: 999px;
    background: var(--accent-soft);
    color: var(--accent-strong);
    font-size: 0.86rem;
    font-weight: 600;
}

.empty-state {
    padding: 28px;
    border-radius: 24px;
    background: var(--surface-solid);
    border: 1px dashed var(--border);
    color: var(--muted);
    line-height: 1.7;
}

.detail-grid {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.footer {
    padding: 26px 0 44px;
}

.footer-card {
    display: grid;
    gap: 18px;
    padding: 28px;
    border-radius: 28px;
    background: var(--footer-bg);
    color: var(--footer-text);
}

.footer-card h3,
.footer-card h4 {
    margin: 0 0 10px;
    color: #fff;
}

.footer-grid {
    display: grid;
    gap: 18px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.footer-list {
    display: grid;
    gap: 10px;
}

.footer-list a:hover {
    color: #ffb98c;
}

.footer-meta {
    color: var(--footer-meta);
    font-size: 0.92rem;
}

.footer-logo-shell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    overflow: hidden;
    border-radius: 20px;
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.1);
    max-width: fit-content;
}

.footer-logo {
    width: 194px;
}

@media (max-width: 980px) {
    .hero-grid {
        grid-template-columns: 1fr;
    }

    .hero h1 {
        max-width: 100%;
    }
}

@media (max-width: 820px) {
    .topbar-inner {
        align-items: flex-start;
        flex-direction: column;
    }

    .nav-links {
        justify-content: flex-start;
    }
}

@media (max-width: 640px) {
    .hero {
        padding-top: 36px;
    }

    .hero-card,
    .footer-card,
    .category-card,
    .detail-card,
    .empty-state,
    .place-card-body,
    .place-media,
    .hero-side-card,
    .stat-card {
        padding: 22px;
    }

    .search-form {
        padding: 10px;
    }

    .search-button {
        width: 100%;
    }

    .stat-row {
        grid-template-columns: 1fr;
    }

    .section-header {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>
<body class="light-mode">
<div class="intro-screen" id="intro-screen" aria-hidden="true">
    <div class="intro-card">
        <div class="intro-logo-shell">
            <img class="intro-logo" src="assets/images/where2go-logo.svg" alt="Where2Go logo">
        </div>
        <div class="intro-line">Discover Cairo one spot at a time.</div>
    </div>
</div>

<div class="site-shell">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand-wrap">
                <a class="brand" href="#home" aria-label="Where2Go home">
                    <img src="/where2go_transparent.png" alt="Where2Go logo" class="logo">
                </a>

                <button class="theme-toggle" id="theme-toggle" type="button">
                    <i data-lucide="sun-medium" id="theme-icon"></i>
                    <span id="theme-label">Light mode</span>
                </button>
            </div>

            <nav class="nav-links" aria-label="Primary">
                <a class="nav-link" href="#home">Home</a>
                <a class="nav-link" href="#categories">Explore</a>
                <a class="nav-link" href="#places">Places</a>
                <a class="nav-link" href="#contact">Contact</a>
                <?php if ($loggedIn): ?>
                <span class="nav-muted">Welcome back, <?php echo htmlspecialchars($customerName !== '' ? $customerName : 'traveler', ENT_QUOTES, 'UTF-8'); ?></span>
                <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                <a class="nav-link" href="login.php">Login</a>
                <a class="nav-cta" href="register.php">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero" id="home">
            <div class="section-inner">
                <div class="hero-card">
                    <div class="hero-grid">
                        <div>
                            <div class="eyebrow">
                                <i data-lucide="sparkles"></i>
                                <span>Built for Cairo discovery</span>
                            </div>

                            <h1>Find the next place worth going to, faster.</h1>
                            <p>Where2Go helps people discover trusted restaurants, entertainment, and hangout spots around Cairo. The intro is now more complete, the branding is in place, and the page can keep working even while the database is still being repaired.</p>

                            <form class="search-form" method="GET" action="index.php">
                                <label class="search-field">
                                    <i data-lucide="search"></i>
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search for places in Cairo" aria-label="Search for places in Cairo">
                                </label>
                                <button class="search-button" type="submit">Search</button>
                            </form>

                            <div class="notice-stack">
                                <?php if ($loggedInNotice): ?>
                                <div class="notice notice-success">
                                    <i data-lucide="sparkles"></i>
                                    <div>
                                        <strong>You are signed in.</strong>
                                        <span>Your session is active now, so the next step is building out the pages that use your account.</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($loggedOutNotice): ?>
                                <div class="notice notice-success">
                                    <i data-lucide="door-open"></i>
                                    <div>
                                        <strong>You have been logged out.</strong>
                                        <span>Your session was cleared successfully. You can sign back in any time from the new login page.</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($registered): ?>
                                <div class="notice notice-success">
                                    <i data-lucide="badge-check"></i>
                                    <div>
                                        <strong>Registration is working.</strong>
                                        <span>Your account was created. You can now continue to the login page and sign in with the email and password you just used.</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($dbNotice): ?>
                                <div class="notice notice-warning">
                                    <i data-lucide="triangle-alert"></i>
                                    <div>
                                        <strong>Database attention needed</strong>
                                        <span><?php echo htmlspecialchars($dbNotice, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <aside class="hero-side">
                            <div class="hero-side-card">
                                <div class="hero-logo-shell">
                                    <img class="hero-logo" src="assets/images/where2go-logo.svg" alt="Where2Go logo">
                                </div>
                                <p>A clearer first impression for the project: stronger branding, a session-based intro reveal, and one shared light/dark theme that registration can also follow.</p>
                            </div>

                            <div class="stat-row">
                                <div class="stat-card">
                                    <span class="stat-value">1 intro</span>
                                    <p>Shown once per session so it feels intentional instead of repetitive.</p>
                                </div>

                                <div class="stat-card">
                                    <span class="stat-value">2 themes</span>
                                    <p>Light and dark mode stay in sync across pages using saved browser preference.</p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="categories">
            <div class="section-inner">
                <div class="section-header">
                    <div>
                        <h2>Explore by category</h2>
                    </div>
                    <p class="section-copy">The homepage now has a more finished introduction while keeping the rest of the flow lightweight. These cards stay ready for the moment you start connecting real partner data.</p>
                </div>

                <div class="category-grid">
                    <article class="category-card">
                        <span class="icon-chip"><i data-lucide="utensils-crossed"></i></span>
                        <h3>Restaurants</h3>
                        <p>Browse casual spots, signature dining, and local favorites around Cairo.</p>
                    </article>

                    <article class="category-card">
                        <span class="icon-chip"><i data-lucide="gamepad-2"></i></span>
                        <h3>Activities</h3>
                        <p>Surface places to go when people want something more interactive than a cafe.</p>
                    </article>

                    <article class="category-card">
                        <span class="icon-chip"><i data-lucide="tickets"></i></span>
                        <h3>Entertainment</h3>
                        <p>Highlight cinemas, shows, and indoor experiences that fit different budgets.</p>
                    </article>

                    <article class="category-card">
                        <span class="icon-chip"><i data-lucide="map"></i></span>
                        <h3>Discover Cairo</h3>
                        <p>Keep the homepage focused on the city you are currently targeting in the database query.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section" id="places">
            <div class="section-inner">
                <div class="section-header">
                    <div>
                        <h2>Popular places</h2>
                    </div>
                    <p class="section-copy">This section still handles three states cleanly: live data, no matching rows, and broken tables. That way the design stays polished even when the schema still needs work.</p>
                </div>

                <?php if ($places): ?>
                <div class="place-grid">
                    <?php foreach ($places as $place): ?>
                    <article class="place-card">
                        <div class="place-media">
                            <div>
                                <span class="pill">
                                    <i data-lucide="building-2"></i>
                                    <span>Cairo partner</span>
                                </span>
                            </div>
                            <strong><?php echo htmlspecialchars(substr($place['Business_Name'], 0, 3), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>

                        <div class="place-card-body">
                            <h3><?php echo htmlspecialchars($place['Business_Name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="place-meta">
                                <i class="inline-icon" data-lucide="map-pin"></i>
                                <span><?php echo htmlspecialchars($place['Physical_Address'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <span class="pill">
                                <i data-lucide="badge-info"></i>
                                <span>Details page still needs to be built</span>
                            </span>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <?php if ($dbNotice): ?>
                    The homepage layout is ready, but the live partners table is not readable yet. Once the table is repaired or re-imported in phpMyAdmin, this section will populate automatically from MySQL.
                    <?php else: ?>
                    No Cairo places matched your current search. Add rows to the partners table or try a broader search term.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section">
            <div class="section-inner">
                <div class="detail-grid">
                    <article class="detail-card">
                        <span class="icon-chip"><i data-lucide="database"></i></span>
                        <h3>Database status</h3>
                        <p>The PHP app points at the `where2go` schema instead of the old alumni database, so the project is at least wired to the correct XAMPP target.</p>
                    </article>

                    <article class="detail-card">
                        <span class="icon-chip"><i data-lucide="shield-check"></i></span>
                        <h3>Safer runtime behavior</h3>
                        <p>Connection and query failures show as friendly notices while the real error details go to the PHP error log instead of appearing in the UI.</p>
                    </article>

                    <article class="detail-card">
                        <span class="icon-chip"><i data-lucide="palette"></i></span>
                        <h3>Shared visual system</h3>
                        <p>The logo, intro mood, and theme toggle now belong to the real site instead of living only in the earlier AI prototype.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer" id="contact">
        <div class="footer-inner">
            <div class="footer-card">
                <div class="footer-grid">
                    <div>
                        <div class="footer-logo-shell">
                            <img class="footer-logo" src="assets/images/where2go-logo.svg" alt="Where2Go logo">
                        </div>
                        <p>Discover places worth trying in Cairo, then grow into partner pages, filters, reviews, and booking once the database is healthy again.</p>
                    </div>

                    <div class="footer-list">
                        <h4>Quick links</h4>
                        <a href="#home">Home</a>
                        <a href="#categories">Explore</a>
                        <a href="#places">Places</a>
                        <a href="login.php">Login</a>
                        <a href="register.php">Register</a>
                    </div>

                    <div class="footer-list">
                        <h4>Local setup notes</h4>
                        <span>Server: XAMPP Apache + MySQL</span>
                        <span>Schema: where2go</span>
                        <span>Auth pages: login and registration are now live</span>
                    </div>
                </div>

                <div class="footer-meta">Where2Go local prototype on XAMPP. Next milestone: repair or recreate the live MySQL tables so cards and registration can store real data.</div>
            </div>
        </div>
    </footer>
</div>

<script>
const themeKey = 'where2go-theme';
const body = document.body;
const themeToggle = document.getElementById('theme-toggle');
const themeIcon = document.getElementById('theme-icon');
const themeLabel = document.getElementById('theme-label');
const introScreen = document.getElementById('intro-screen');

function applyTheme(theme) {
    const isDark = theme === 'dark';
    body.classList.toggle('dark-mode', isDark);
    body.classList.toggle('light-mode', !isDark);
    themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
    themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
    localStorage.setItem(themeKey, theme);
    lucide.createIcons();
}

function startIntro() {
    const seenIntro = sessionStorage.getItem('where2go-intro-seen') === '1';

    if (seenIntro) {
        introScreen.remove();
        return;
    }

    body.classList.add('intro-active');
    requestAnimationFrame(() => introScreen.classList.add('is-visible'));

    window.setTimeout(() => {
        introScreen.classList.add('is-leaving');
    }, 1450);

    window.setTimeout(() => {
        introScreen.remove();
        body.classList.remove('intro-active');
        sessionStorage.setItem('where2go-intro-seen', '1');
    }, 2200);
}

const savedTheme = localStorage.getItem(themeKey) || 'light';
applyTheme(savedTheme);
themeToggle.addEventListener('click', () => {
    const currentTheme = localStorage.getItem(themeKey) || 'light';
    applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
});

window.addEventListener('load', startIntro);
</script>
</body>
</html>


