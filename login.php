<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

start_session();

if (is_logged_in()) {
    header('Location: Home.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$email = trim($_POST['Email'] ?? '');
$password = $_POST['Password'] ?? '';
$registered = ($_GET['registered'] ?? '') === '1';
$loggedOut = ($_GET['logged_out'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$conn) {
        $errors[] = $database->lastError ?: 'Unable to connect to the Where2Go database.';
    }

    if (!$errors) {
        try {
            $loginQuery = 'SELECT Customer_ID, First_N, Email, Password FROM customers WHERE Email = ? LIMIT 1';
            $loginStmt = $conn->prepare($loginQuery);
            $loginStmt->execute([$email]);
            $customer = $loginStmt->fetch();

            if (!$customer || !verify_password($password, $customer['Password'])) {
                $errors[] = 'The email or password is incorrect.';
            } else {
                session_regenerate_id(true);
                login_user($customer);
                header('Location: Home.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'The login form is ready, but the customer table still needs attention in phpMyAdmin before sign-in can work.';
            error_log('Where2Go login failed: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In to Where2Go</title>
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
    --surface: rgba(255, 255, 255, 0.92);
    --surface-solid: #ffffff;
    --surface-strong: rgba(255, 255, 255, 0.94);
    --surface-soft: rgba(35, 22, 12, 0.05);
    --border: rgba(35, 22, 12, 0.08);
    --shadow: rgba(35, 22, 12, 0.1);
    --hero-start: #140c07;
    --hero-mid: #4f2207;
    --hero-end: #f26c1c;
    --input-bg: #ffffff;
    --input-text: #23160c;
    --accent: #f26c1c;
    --accent-strong: #c85108;
    --accent-soft: rgba(242, 108, 28, 0.1);
    --warning-bg: #fff4ea;
    --warning-border: rgba(242, 108, 28, 0.18);
    --success-bg: #f4fff8;
    --success-border: rgba(40, 134, 85, 0.18);
}

body.dark-mode {
    color-scheme: dark;
    --page-bg: radial-gradient(circle at top, rgba(242, 108, 28, 0.22), transparent 32%), linear-gradient(180deg, #100b08 0%, #17100c 34%, #090705 100%);
    --text: #f6ede7;
    --muted: #c8b6ab;
    --topbar-bg: rgba(15, 10, 8, 0.9);
    --surface: rgba(30, 21, 17, 0.9);
    --surface-solid: rgba(22, 16, 13, 0.98);
    --surface-strong: rgba(27, 19, 15, 0.94);
    --surface-soft: rgba(255, 255, 255, 0.08);
    --border: rgba(255, 255, 255, 0.08);
    --shadow: rgba(0, 0, 0, 0.34);
    --hero-start: #090605;
    --hero-mid: #2c1407;
    --hero-end: #d95d12;
    --input-bg: rgba(22, 16, 13, 0.98);
    --input-text: #f6ede7;
    --accent: #ff8a3d;
    --accent-strong: #ffb178;
    --accent-soft: rgba(242, 108, 28, 0.16);
    --warning-bg: rgba(104, 56, 21, 0.34);
    --warning-border: rgba(255, 164, 102, 0.2);
    --success-bg: rgba(32, 88, 52, 0.28);
    --success-border: rgba(121, 209, 155, 0.26);
}

* {
    box-sizing: border-box;
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

.topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: var(--topbar-bg);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
}

.topbar-inner,
.main-inner {
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

.theme-toggle,
.top-link,
.top-cta,
.inline-link {
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
.inline-link {
    color: var(--muted);
    background: var(--surface-soft);
}

.top-cta {
    background: linear-gradient(135deg, #f26c1c, #ff8c42);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 14px 28px rgba(242, 108, 28, 0.22);
}

.main-inner {
    padding: 52px 0 72px;
}

.hero-panel {
    overflow: hidden;
    border-radius: 32px;
    background: linear-gradient(135deg, var(--hero-start) 0%, var(--hero-mid) 36%, var(--hero-end) 100%);
    color: #fff;
    padding: 34px;
    box-shadow: 0 32px 70px rgba(55, 25, 8, 0.18);
}

.hero-panel h1 {
    margin: 0 0 12px;
    font-size: clamp(2rem, 4vw, 3.3rem);
    line-height: 1.02;
    letter-spacing: -0.05em;
}

.hero-panel p {
    margin: 0;
    max-width: 760px;
    color: rgba(255, 255, 255, 0.84);
    line-height: 1.7;
}

.form-shell {
    margin-top: 22px;
    display: grid;
    gap: 22px;
    grid-template-columns: minmax(0, 1.05fr) minmax(260px, 0.95fr);
}

.form-card,
.info-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 28px;
    box-shadow: 0 24px 54px var(--shadow);
}

.form-card {
    padding: 30px;
}

.info-card {
    padding: 24px;
    align-self: start;
}

.section-title {
    margin: 0 0 8px;
    font-size: 1.45rem;
    letter-spacing: -0.03em;
}

.section-copy {
    margin: 0 0 20px;
    color: var(--muted);
    line-height: 1.7;
}

.notice {
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid var(--warning-border);
    background: var(--warning-bg);
    color: var(--text);
}

.notice.notice-success {
    background: var(--success-bg);
    border-color: var(--success-border);
}

.notice p {
    margin: 0;
}

.notice p + p {
    margin-top: 8px;
}

.form-grid {
    display: grid;
    gap: 16px;
}

.field {
    display: grid;
    gap: 8px;
}

label {
    font-size: 0.94rem;
    font-weight: 600;
}

input {
    width: 100%;
    min-height: 52px;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 0 14px;
    font: inherit;
    background: var(--input-bg);
    color: var(--input-text);
}

input:focus {
    outline: 2px solid rgba(242, 108, 28, 0.2);
    border-color: var(--accent);
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 24px;
}

.button,
.button-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 52px;
    padding: 0 18px;
    border-radius: 16px;
    border: 0;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}

.button {
    background: linear-gradient(135deg, #f26c1c, #ff8c42);
    color: #fff;
    box-shadow: 0 18px 36px rgba(242, 108, 28, 0.22);
}

.button-secondary {
    background: var(--surface-soft);
    color: var(--text);
}

.form-meta {
    margin-top: 18px;
    color: var(--muted);
    line-height: 1.7;
}

.info-card h3 {
    margin: 0 0 10px;
    font-size: 1.08rem;
}

.info-card p {
    margin: 0 0 18px;
    color: var(--muted);
    line-height: 1.7;
}

.info-list {
    display: grid;
    gap: 12px;
}

.info-item {
    padding: 14px 16px;
    border-radius: 18px;
    background: var(--accent-soft);
    color: var(--muted);
    line-height: 1.6;
}

.info-item strong {
    display: block;
    margin-bottom: 4px;
    color: var(--text);
}

@media (max-width: 900px) {
    .form-shell {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .topbar-inner {
        align-items: flex-start;
        flex-direction: column;
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
    .form-card,
    .info-card {
        padding: 22px;
    }

    .actions {
        flex-direction: column;
    }
}
</style>
</head>
<body class="light-mode">
<div class="page-shell">
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
                <a class="top-link" href="register.php">Create account</a>
                <span class="top-cta">Login</span>
            </div>
        </div>
    </header>

    <main class="main-inner">
        <section class="hero-panel">
            <h1>Welcome back to Where2Go</h1>
            <p>Sign in with the same account you created on the registration page and keep the same light or dark theme while you explore what comes next for the platform.</p>
        </section>

        <section class="form-shell">
            <div class="form-card">
                <h2 class="section-title">Login form</h2>
                <p class="section-copy">Use the email and password saved in the `customers` table. The session is created after a successful sign-in and brought back to the homepage.</p>

                <?php if ($registered): ?>
                <div class="notice notice-success">
                    <p>Your account was created successfully. Sign in below to continue.</p>
                </div>
                <?php endif; ?>

                <?php if ($loggedOut): ?>
                <div class="notice notice-success">
                    <p>You have been logged out. You can sign in again whenever you are ready.</p>
                </div>
                <?php endif; ?>

                <?php if ($errors): ?>
                <div class="notice">
                    <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-grid">
                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="Email" autocomplete="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="Password" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="button" type="submit">Sign in</button>
                        <a class="button-secondary" href="register.php">Create account</a>
                    </div>
                </form>

                <p class="form-meta">Need a new account? <a class="inline-link" href="register.php">Open registration</a></p>
            </div>

            <aside class="info-card">
                <h3>What this login page does</h3>
                <p>It matches the homepage and registration styling, checks the `customers` table in `where2go`, and starts a session for the user after a successful password check.</p>

                <div class="info-list">
                    <div class="info-item">
                        <strong>Shared theme</strong>
                        The same light and dark mode preference is reused here so login does not feel like a separate app.
                    </div>

                    <div class="info-item">
                        <strong>Real authentication</strong>
                        The password is verified against the hashed `Password` column stored in your customer records.
                    </div>

                    <div class="info-item">
                        <strong>Database note</strong>
                        If phpMyAdmin still shows broken customer tables, this page will show a clear message instead of failing silently.
                    </div>
                </div>
            </aside>
        </section>
    </main>
</div>

<script>
const themeKey = 'where2go-theme';
const body = document.body;
const themeToggle = document.getElementById('theme-toggle');
const themeIcon = document.getElementById('theme-icon');
const themeLabel = document.getElementById('theme-label');

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
