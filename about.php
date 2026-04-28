<?php
// Load the session so the about page can adapt its header actions for logged-in visitors.
require_once __DIR__ . '/includes/functions.php';

start_session();

$loggedIn = is_logged_in();
$customerName = trim($_SESSION['customer_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Where2Go</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* Default light theme tokens for the standalone about page. */
:root {
    color-scheme: light;
    --page-bg: radial-gradient(circle at top, rgba(242, 108, 28, 0.16), transparent 38%), linear-gradient(180deg, #fffaf5 0%, #ffffff 28%, #fff5ed 100%);
    --text: #23160c;
    --muted: #6f6156;
    --topbar-bg: rgba(255, 255, 255, 0.94);
    --surface: rgba(255, 255, 255, 0.92);
    --surface-strong: rgba(255, 255, 255, 0.94);
    --surface-soft: rgba(35, 22, 12, 0.05);
    --border: rgba(35, 22, 12, 0.08);
    --shadow: rgba(35, 22, 12, 0.1);
    --hero-start: #140c07;
    --hero-mid: #4f2207;
    --hero-end: #f26c1c;
    --accent: #f26c1c;
    --accent-soft: rgba(242, 108, 28, 0.1);
    --accent-strong: #c85108;
}

/* Dark theme overrides that activate when the visitor switches modes. */
body.dark-mode {
    color-scheme: dark;
    --page-bg: radial-gradient(circle at top, rgba(242, 108, 28, 0.22), transparent 32%), linear-gradient(180deg, #100b08 0%, #17100c 34%, #090705 100%);
    --text: #f6ede7;
    --muted: #c8b6ab;
    --topbar-bg: rgba(15, 10, 8, 0.9);
    --surface: rgba(30, 21, 17, 0.9);
    --surface-strong: rgba(27, 19, 15, 0.94);
    --surface-soft: rgba(255, 255, 255, 0.08);
    --border: rgba(255, 255, 255, 0.08);
    --shadow: rgba(0, 0, 0, 0.34);
    --hero-start: #090605;
    --hero-mid: #2c1407;
    --hero-end: #d95d12;
    --accent: #ff8a3d;
    --accent-soft: rgba(242, 108, 28, 0.16);
    --accent-strong: #ffb178;
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

a {
    color: inherit;
    text-decoration: none;
}

img {
    display: block;
    max-width: 100%;
}

.page-shell {
    min-height: 100vh;
}

/* Sticky header styles for the page title, navigation links, and theme toggle. */
.topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    background: var(--topbar-bg);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
}

.topbar-inner,
.main-inner,
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
}

body.dark-mode .logo {
    filter: invert(1) hue-rotate(180deg) saturate(1.08) brightness(1.06);
}

body.light-mode .logo {
    filter: brightness(0.9);
}

.theme-toggle,
.top-link,
.top-cta,
.feature-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 42px;
    padding: 0 14px;
    border-radius: 999px;
    font: inherit;
    font-size: 0.92rem;
}

.theme-toggle {
    border: 1px solid var(--border);
    background: var(--surface-strong);
    color: var(--text);
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 12px 24px rgba(35, 22, 12, 0.06);
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.top-link,
.feature-link {
    color: var(--muted);
    background: var(--surface-soft);
    transition: color 0.3s ease, outline-color 0.3s ease, transform 0.3s ease;
}

.top-link:hover,
.feature-link:hover {
    color: var(--accent);
    outline: 2px solid rgba(242, 108, 28, 0.34);
    outline-offset: 2px;
    transform: translateY(-1px);
}

.top-cta {
    background: linear-gradient(135deg, #f26c1c, #ff8c42);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 14px 28px rgba(242, 108, 28, 0.22);
}

.main-inner {
    padding: 50px 0 72px;
}

/* Hero panel styles for the page introduction and quick callout content. */
.hero-panel {
    overflow: hidden;
    border-radius: 32px;
    background: linear-gradient(135deg, var(--hero-start) 0%, var(--hero-mid) 36%, var(--hero-end) 100%);
    color: #fff;
    padding: 34px;
    box-shadow: 0 32px 70px rgba(55, 25, 8, 0.18);
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    margin-bottom: 18px;
    font-size: 0.9rem;
    font-weight: 600;
}

.hero-grid {
    display: grid;
    gap: 24px;
    grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
    align-items: end;
}

.hero-panel h1 {
    margin: 0 0 14px;
    font-size: clamp(2.1rem, 4.2vw, 3.5rem);
    line-height: 1.02;
    letter-spacing: -0.05em;
}

.hero-panel p {
    margin: 0;
    max-width: 720px;
    color: rgba(255, 255, 255, 0.84);
    line-height: 1.75;
}

.hero-side {
    display: grid;
    gap: 16px;
}

.hero-stat,
.section-card,
.story-card,
.contact-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 28px;
    box-shadow: 0 24px 54px var(--shadow);
}

.hero-stat {
    padding: 22px;
    color: var(--text);
}

.hero-stat strong {
    display: block;
    margin-bottom: 8px;
    font-size: 1.8rem;
    color: var(--accent);
}

.sections-grid {
    margin-top: 24px;
    display: grid;
    gap: 22px;
}

.section-card {
    padding: 28px;
}

.section-card h2,
.contact-card h2 {
    margin: 0 0 10px;
    font-size: 1.55rem;
    letter-spacing: -0.03em;
}

.section-card p,
.contact-card p,
.story-card p {
    margin: 0;
    color: var(--muted);
    line-height: 1.75;
}

.two-column {
    display: grid;
    gap: 22px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.check-grid {
    margin-top: 20px;
    display: grid;
    gap: 14px;
}

.check-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 18px;
    border-radius: 20px;
    background: var(--accent-soft);
}

.check-item i {
    color: var(--accent);
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.check-item strong {
    display: block;
    margin-bottom: 4px;
    color: var(--text);
}

.pill-grid {
    margin-top: 18px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.pill-grid.stacked {
    display: grid;
    gap: 12px;
    width: min(100%, 240px);
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    border-radius: 999px;
    background: var(--surface-soft);
    color: var(--text);
    outline: 1px solid var(--border);
}

.pill-grid.stacked .pill {
    width: 100%;
    justify-content: flex-start;
}

.pill i {
    color: var(--accent);
    width: 18px;
    height: 18px;
}

.compact-card {
    align-self: start;
    justify-self: start;
    width: min(100%, 360px);
}

.story-card,
.contact-card {
    padding: 28px;
}

.story-quote {
    margin: 0 0 14px;
    color: var(--accent);
    font-weight: 700;
    font-size: 1.12rem;
}

.contact-list {
    margin-top: 20px;
    display: grid;
    gap: 12px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 18px;
    background: var(--surface-soft);
    color: var(--muted);
}

.contact-item i {
    width: 20px;
    height: 20px;
    color: var(--accent);
}

.footer {
    padding: 0 0 48px;
}

.footer-card {
    background: linear-gradient(135deg, #17100a, #26160d);
    color: rgba(255, 255, 255, 0.86);
    border-radius: 28px;
    padding: 28px;
    box-shadow: 0 26px 48px rgba(14, 10, 7, 0.25);
}

body.dark-mode .footer-card {
    background: linear-gradient(135deg, #050403, #170d08);
}

.footer-grid {
    display: grid;
    gap: 22px;
    grid-template-columns: minmax(0, 1.2fr) repeat(2, minmax(180px, 0.9fr));
}

.footer-card h3,
.footer-card h4 {
    margin: 0 0 12px;
    color: #fff;
}

.footer-card p,
.footer-card a,
.footer-card span {
    color: rgba(255, 255, 255, 0.72);
    line-height: 1.7;
}

.footer-list {
    display: grid;
    gap: 10px;
}

.footer-list a:hover {
    color: #ffb178;
}

.footer-meta {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.58);
}

@media (max-width: 920px) {
    .hero-grid,
    .two-column,
    .footer-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .topbar-inner {
        flex-direction: column;
        align-items: flex-start;
    }

    .top-actions {
        justify-content: flex-start;
    }
}

@media (max-width: 640px) {
    .main-inner {
        padding-top: 28px;
    }

    .hero-panel,
    .hero-stat,
    .section-card,
    .story-card,
    .contact-card,
    .footer-card {
        padding: 22px;
    }
}
</style>
</head>
<body class="light-mode">
<div class="page-shell">
    <!-- Page header with navigation back into the main discovery experience. -->
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand-wrap">
                <a class="brand" href="Home.php" aria-label="Where2Go home">
                    <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="logo">
                </a>

                <button class="theme-toggle" id="theme-toggle" type="button">
                    <i data-lucide="sun-medium" id="theme-icon"></i>
                    <span id="theme-label">Light mode</span>
                </button>
            </div>

            <div class="top-actions">
                <a class="top-link" href="Home.php">Back to homepage</a>
                <?php if ($loggedIn): ?>
                <span class="top-link">Welcome, <?php echo htmlspecialchars($customerName !== '' ? $customerName : 'Traveler', ENT_QUOTES, 'UTF-8'); ?></span>
                <a class="top-cta" href="logout.php">Logout</a>
                <?php else: ?>
                <a class="top-link" href="login.php">Login</a>
                <a class="top-cta" href="register.php">Create account</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-inner">
        <!-- Intro section that explains the purpose and promise of Where2Go. -->
        <section class="hero-panel">
            <div class="hero-grid">
                <div>
                    <div class="eyebrow">
                        <i data-lucide="sparkles"></i>
                        <span>About Where2Go</span>
                    </div>
                    <h1>Your guide to where to go, what to do, and how to make the most of your time.</h1>
                    <p>Where2Go is more than just a booking platform. It is your go-to guide for discovering where to go, what to do, and how to make the most of your time. Whether you are planning a night out, a weekend activity, or just looking for something new, Where2Go helps you find the right place instantly.</p>
                </div>

                <div class="hero-side">
                    <div class="hero-stat">
                        <strong>Save your hangouts</strong>
                        <span>Spend less time deciding where to go and more time actually enjoying the plan.</span>
                    </div>

                    <a class="feature-link" href="#contact-us">
                        <i data-lucide="message-circle"></i>
                        <span>Questions or feedback?</span>
                    </a>
                </div>
            </div>
        </section>

        <div class="sections-grid">
            <!-- Main content grid covering the mission, vision, story, and contact details. -->
            <section class="section-card">
                <h2>Our Mission</h2>
                <p>We are here to save your hangouts. No more wasting time deciding where to go or scrolling endlessly through options. Where2Go helps you make quick decisions so you can spend less time planning and more time enjoying.</p>
            </section>

            <section class="two-column">
                <div class="section-card">
                    <h2>What We Do</h2>
                    <p>Where2Go helps people discover better options for going out and makes planning feel lighter, faster, and more organized.</p>

                    <div class="check-grid">
                        <div class="check-item">
                            <i data-lucide="search"></i>
                            <div>
                                <strong>Discover entertainment and experiences</strong>
                                <span>Find entertainment spots, activities, and experiences in one place.</span>
                            </div>
                        </div>

                        <div class="check-item">
                            <i data-lucide="sparkles"></i>
                            <div>
                                <strong>Match the mood</strong>
                                <span>Find places that fit whether you want something relaxed, fun, or adventurous.</span>
                            </div>
                        </div>

                        <div class="check-item">
                            <i data-lucide="calendar-check-2"></i>
                            <div>
                                <strong>Book and reserve easily</strong>
                                <span>Move from choosing to booking without extra stress or unnecessary steps.</span>
                            </div>
                        </div>

                        <div class="check-item">
                            <i data-lucide="map"></i>
                            <div>
                                <strong>Plan outings without stress</strong>
                                <span>Get clarity faster so the focus stays on enjoying the day, not organizing it.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card compact-card">
                    <h2>Why Where2Go</h2>
                    <p>Because going out should be simple. We focus on the parts that make decisions easier and outings better.</p>

                    <div class="pill-grid stacked">
                        <div class="pill">
                            <i data-lucide="compass"></i>
                            <span>Discovery</span>
                        </div>

                        <div class="pill">
                            <i data-lucide="wand-sparkles"></i>
                            <span>Simplicity</span>
                        </div>

                        <div class="pill">
                            <i data-lucide="zap"></i>
                            <span>Speed</span>
                        </div>

                        <div class="pill">
                            <i data-lucide="smile"></i>
                            <span>Experience</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="two-column">
                <div class="story-card">
                    <h2>Our Vision</h2>
                    <p>To become the first platform people open when they ask: "Where should we go today?"</p>
                </div>

                <div class="story-card">
                    <h2>Our Story</h2>
                    <p class="story-quote">"People want to go out, but choosing where takes too long."</p>
                    <p>Where2Go started with a simple problem: people want to go out, but choosing where takes too long. We built a platform that removes the confusion and replaces it with clarity and inspiration, so instead of overthinking, you get straight to enjoying your time.</p>
                </div>
            </section>

            <section class="section-card">
                <h2>In Simple Terms</h2>
                <p>We help people go out more, think less, and enjoy better.</p>

                <div class="pill-grid">
                    <div class="pill">
                        <i data-lucide="party-popper"></i>
                        <span>Go out more</span>
                    </div>

                    <div class="pill">
                        <i data-lucide="brain-circuit"></i>
                        <span>Think less</span>
                    </div>

                    <div class="pill">
                        <i data-lucide="sparkles"></i>
                        <span>Enjoy better</span>
                    </div>
                </div>
            </section>

            <section class="contact-card" id="contact-us">
                <h2>Contact Us</h2>
                <p>Have questions, suggestions, or feedback? We would love to hear from you. We are always working to improve your experience and make Where2Go even better.</p>

                <div class="contact-list">
                    <div class="contact-item">
                        <i data-lucide="mail"></i>
                        <span>support@where2go.com</span>
                    </div>

                    <div class="contact-item">
                        <i data-lucide="phone"></i>
                        <span>+20 XXX XXX XXXX</span>
                    </div>

                    <div class="contact-item">
                        <i data-lucide="map-pin"></i>
                        <span>Cairo, Egypt</span>
                    </div>

                    <div class="contact-item">
                        <i data-lucide="message-square-heart"></i>
                        <span>Questions, suggestions, and feedback are always welcome.</span>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-card">
                <div class="footer-grid">
                    <div>
                        <h3>Where2Go</h3>
                        <p>Find where to go faster, plan with less stress, and spend more time enjoying the moment.</p>
                    </div>

                    <div class="footer-list">
                        <h4>Quick Links</h4>
                        <a href="Home.php">Home</a>
                        <a href="about.php">About</a>
                        <?php if ($loggedIn): ?>
                        <a href="logout.php">Logout</a>
                        <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="register.php">Register</a>
                        <?php endif; ?>
                    </div>

                    <div class="footer-list">
                        <h4>Focus</h4>
                        <span>Discovery made easy</span>
                        <span>Faster decisions</span>
                        <span>Better outings</span>
                    </div>
                </div>

                <div class="footer-meta">Where2Go continues to grow from one simple idea: help people spend less time deciding and more time enjoying.</div>
            </div>
        </div>
    </footer>
</div>

<script>
// Shared theme controls for the standalone about page.
const themeKey = 'where2go-theme';
const body = document.body;
const themeToggle = document.getElementById('theme-toggle');
const themeIcon = document.getElementById('theme-icon');
const themeLabel = document.getElementById('theme-label');

// Apply the saved light or dark mode and refresh the page icons.
function applyTheme(theme) {
    const isDark = theme === 'dark';
    body.classList.toggle('dark-mode', isDark);
    body.classList.toggle('light-mode', !isDark);
    themeIcon.setAttribute('data-lucide', isDark ? 'moon-star' : 'sun-medium');
    themeLabel.textContent = isDark ? 'Dark mode' : 'Light mode';
    localStorage.setItem(themeKey, theme);
    lucide.createIcons();
}

const savedTheme = localStorage.getItem(themeKey) || 'light';
applyTheme(savedTheme);

themeToggle.addEventListener('click', () => {
    const currentTheme = localStorage.getItem(themeKey) || 'light';
    applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
});
</script>
</body>
</html>
