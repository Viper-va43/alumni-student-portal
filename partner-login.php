<?php
// Handle partner authentication separately from customer accounts.
require_once __DIR__ . '/includes/functions.php';

start_session();

if (is_partner_logged_in()) {
    header('Location: partner-dashboard.php');
    exit;
}

$errors = [];
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$registered = ($_GET['registered'] ?? '') === '1';
$loggedOut = ($_GET['logged_out'] ?? '') === '1';

// Validate the submitted credentials and start a partner session on success.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $partner = get_partner_by_email($email);

        if (!$partner || !verify_password($password, (string) ($partner['password'] ?? ''))) {
            $errors[] = 'The partner email or password is incorrect.';
        } else {
            session_regenerate_id(true);
            login_partner_user($partner);
            header('Location: partner-dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | Partner Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css">
<link rel="stylesheet" href="assets/css/partner-portal.css">
</head>
<body class="light-mode">
<!-- Partner login header linking back to discovery and the partner registration flow. -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <a class="brand-link" href="Home.php" aria-label="Where2Go home">
                <img src="assets/images/where2go_transparent.png" alt="Where2Go logo" class="logo">
            </a>
            <button class="theme-toggle" id="theme-toggle" type="button">
                <i data-lucide="sun-medium" id="theme-icon"></i>
                <span id="theme-label">Light mode</span>
            </button>
        </div>

        <nav class="topbar-right" aria-label="Partner login navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="search.php">Search</a>
            <a class="nav-link" href="login.php">Customer login</a>
            <a class="nav-link" href="partner-register.php">Partner register</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero panel explaining what the partner portal can manage. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="store"></i>Partner portal</span>
        <h1>Sign in to manage your listing</h1>
        <p>Use your dedicated business-owner account to add businesses, update offers, adjust reservation capacity, and watch your approval status from one place.</p>
    </section>

    <!-- Two-column area with a dashboard preview and the actual login form. -->
    <!-- Two-column area with a dashboard preview and the actual login form. -->
    <section class="auth-shell" style="margin-top:24px;">
        <div class="panel-card">
            <h2>Inside the dashboard</h2>
            <div class="stack-list">
                <div class="repeat-card">
                    <strong>Business submissions</strong>
                    <p>Create or edit your listing, then send it for admin approval automatically.</p>
                </div>
                <div class="repeat-card">
                    <strong>Reservation tracking</strong>
                    <p>See reservation totals, upcoming bookings, and how many customers clicked into your place.</p>
                </div>
                <div class="repeat-card">
                    <strong>Approval visibility</strong>
                    <p>Pending businesses stay private until the admin validates them for the public website.</p>
                </div>
            </div>
        </div>

        <div class="auth-card">
            <h2>Partner login</h2>
            <p class="helper-text">This login is separate from customer accounts.</p>

            <?php if ($registered): ?>
            <div class="messages"><div class="message success">Your partner account is ready. Sign in to add your business.</div></div>
            <?php endif; ?>
            <?php if ($loggedOut): ?>
            <div class="messages"><div class="message success">You were signed out of the partner portal.</div></div>
            <?php endif; ?>
            <?php if ($errors): ?>
            <div class="messages">
                <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form action="partner-login.php" method="POST" class="form-grid">
                <div class="field">
                    <label for="email">Business email</label>
                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="owner@business.com">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="Your partner password">
                </div>

                <div class="action-row">
                    <button class="primary-btn" type="submit"><i data-lucide="log-in"></i>Open dashboard</button>
                    <a class="secondary-btn" href="partner-register.php"><i data-lucide="user-plus"></i>Create partner account</a>
                </div>
            </form>
        </div>
    </section>
</main>

<script>
// Keep the shared account script initialized even though this page does not expose saved places.
window.where2goPageData = <?php echo json_encode(['visitedPlaceIds' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
<script src="assets/js/partner-portal.js"></script>
</body>
</html>
