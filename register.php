<?php
// Load the shared auth helpers and open the customer database connection for registration.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

start_session();
$redirectTarget = get_safe_internal_redirect_target($_POST['redirect'] ?? ($_GET['redirect'] ?? ''), 'Home.php');

$database = new Database();
$conn = $database->getConnection();
$loggedIn = is_logged_in();
$customerName = trim($_SESSION['customer_name'] ?? '');

$errors = [];
$first_name = trim($_POST['First_N'] ?? '');
$middle_name = trim($_POST['Middle_N'] ?? '');
$last_name = trim($_POST['Last_N'] ?? '');
$email = trim($_POST['Email'] ?? '');
$password = $_POST['Password'] ?? '';
$phone = trim($_POST['Customer_NUM'] ?? '');
$address = trim($_POST['Physical_Address'] ?? '');
$dob = $_POST['Date_Of_Birth'] ?? '';
$gender = trim($_POST['Gender'] ?? '');
$nationality = trim($_POST['Nationality'] ?? '');

// Validate the submitted profile details before creating a new customer account.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
        $errors[] = 'First name, last name, email, and password are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password !== '' && !preg_match('/^(?=.*[A-Z])(?=.*[\\W_]).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include 1 uppercase letter and 1 special character.';
    }

    if (!$conn) {
        $errors[] = $database->lastError ?: 'Unable to connect to the Where2Go database.';
    }

    if (!$errors) {
        try {
            $checkQuery = 'SELECT Customer_ID FROM customers WHERE Email = ?';
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute([$email]);

            if ($checkStmt->fetch()) {
                $errors[] = 'This email address is already registered.';
            }
        } catch (PDOException $e) {
            $errors[] = 'The customer table is not ready yet. Please repair or re-import the Where2Go database tables in phpMyAdmin before testing registration.';
            error_log('Where2Go register lookup failed: ' . $e->getMessage());
        }
    }

    if (!$errors) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $middle_name_value = $middle_name !== '' ? $middle_name : null;
            $phone_value = $phone !== '' ? $phone : null;
            $address_value = $address !== '' ? $address : null;
            $dob_value = $dob !== '' ? $dob : null;
            $gender_value = $gender !== '' ? $gender : null;
            $nationality_value = $nationality !== '' ? $nationality : null;

            $insertQuery = "INSERT INTO customers
                (First_N, Middle_N, Last_N, Email, Password, Date_Of_Birth, Gender, Physical_Address, Customer_NUM, Verification_Status, Nationality, Created_At)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())";

            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->execute([
                $first_name,
                $middle_name_value,
                $last_name,
                $email,
                $hashed_password,
                $dob_value,
                $gender_value,
                $address_value,
                $phone_value,
                $nationality_value,
            ]);

            $loginRedirect = 'login.php?registered=1';

            if ($redirectTarget !== 'Home.php') {
                $loginRedirect .= '&redirect=' . rawurlencode($redirectTarget);
            }

            header('Location: ' . $loginRedirect);
            exit;
        } catch (PDOException $e) {
            $errors[] = 'The form is working, but the Where2Go database could not save the account yet. Repair the tables in phpMyAdmin, then try again.';
            error_log('Where2Go register insert failed: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Your Where2Go Account</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
/* Default light theme tokens for the customer registration page. */
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
}

/* Dark theme overrides that activate when the visitor switches modes. */
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

/* Sticky header styles for the registration page navigation and theme toggle. */
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
.top-cta {
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

.top-link {
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

/* Hero panel styles for the account-creation introduction and notices. */
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
    grid-template-columns: minmax(0, 1.25fr) minmax(260px, 0.75fr);
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

.notice p {
    margin: 0;
}

.notice p + p {
    margin-top: 8px;
}

.form-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.field {
    display: grid;
    gap: 8px;
}

.field-full {
    grid-column: 1 / -1;
}

label {
    font-size: 0.94rem;
    font-weight: 600;
}

input,
select {
    width: 100%;
    min-height: 52px;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 0 14px;
    font: inherit;
    background: var(--input-bg);
    color: var(--input-text);
}

input:focus,
select:focus {
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

    .form-grid {
        grid-template-columns: 1fr;
    }

    .actions {
        flex-direction: column;
    }
}
</style>
</head>
<body class="light-mode">
<div class="page-shell">
    <!-- Header that keeps registration connected to the main site and theme control. -->
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
                <a class="top-link" href="login.php<?php echo $redirectTarget !== 'Home.php' ? '?redirect=' . rawurlencode($redirectTarget) : ''; ?>">Already have an account?</a>
                <span class="top-cta">Registration</span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-inner">
        <!-- Intro panel explaining the customer registration flow. -->
        <section class="hero-panel">
            <h1>Create your Where2Go account</h1>
            <p>The registration page follows the same light and dark theme system as the homepage, so the whole account flow feels connected while the `where2go` database keeps taking shape.</p>
            <?php if ($redirectTarget !== 'Home.php'): ?>
            <p style="margin-top:12px;">After registration, you will be sent to login and then returned to the QR reward page you opened.</p>
            <?php endif; ?>
        </section>

        <!-- Form area that captures the new customer's profile and login details. -->
        <section class="form-shell">
            <div class="form-card">
                <h2 class="section-title">Registration form</h2>
                <p class="section-copy">This version keeps your existing fields, matches the homepage styling, and now sends successful signups straight to the homepage.</p>

                <?php if ($errors): ?>
                <div class="notice">
                    <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-grid">
                        <div class="field">
                            <label for="first_name">First name</label>
                            <input id="first_name" type="text" name="First_N" value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="field">
                            <label for="middle_name">Middle name</label>
                            <input id="middle_name" type="text" name="Middle_N" value="<?php echo htmlspecialchars($middle_name, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label for="last_name">Last name</label>
                            <input id="last_name" type="text" name="Last_N" value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="Email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="field">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="Password" required>
                        </div>

                        <div class="field">
                            <label for="phone">Phone number</label>
                            <input id="phone" type="text" name="Customer_NUM" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field field-full">
                            <label for="address">Physical address</label>
                            <input id="address" type="text" name="Physical_Address" value="<?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label for="dob">Date of birth</label>
                            <input id="dob" type="date" name="Date_Of_Birth" value="<?php echo htmlspecialchars($dob, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label for="gender">Gender</label>
                            <select id="gender" name="Gender">
                                <option value="" <?php echo $gender === '' ? 'selected' : ''; ?>>Select gender</option>
                                <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="field field-full">
                            <label for="nationality">Nationality</label>
                            <input id="nationality" type="text" name="Nationality" value="<?php echo htmlspecialchars($nationality, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="actions">
                        <button class="button" type="submit">Create account</button>
                        <a class="button-secondary" href="<?php echo htmlspecialchars($redirectTarget !== 'Home.php' ? ('login.php?redirect=' . rawurlencode($redirectTarget)) : 'Home.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo $redirectTarget !== 'Home.php' ? 'Open login' : 'Back to homepage'; ?></a>
                    </div>
                </form>
            </div>

            <aside class="info-card">
                <h3>What changed here</h3>
                <p>The registration screen now uses the same brand logo, persistent theme preference, and softer branded surfaces as the homepage.</p>

                <div class="info-list">
                    <div class="info-item">
                        <strong>Shared themes</strong>
                        Your light or dark mode choice is saved in the browser and reused across both pages.
                    </div>

                    <div class="info-item">
                        <strong>Cleaner brand setup</strong>
                        The header now uses the same Where2Go logo asset so replacing it later will be a one-file swap.
                    </div>

                    <div class="info-item">
                        <strong>Database note</strong>
                        If phpMyAdmin still shows broken customer tables, this page will keep showing a clear message instead of failing silently.
                    </div>
                </div>
            </aside>
        </section>
    </main>
</div>

<script>
// Shared theme controls for the standalone registration page.
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


