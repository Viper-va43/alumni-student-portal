<?php
// Create dedicated partner accounts that are separate from normal customer logins.
require_once __DIR__ . '/includes/functions.php';

start_session();

if (is_partner_logged_in()) {
    header('Location: partner-dashboard.php');
    exit;
}

$errors = [];
$ownerName = trim($_POST['owner_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate the submitted owner details before creating the partner account.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($ownerName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $errors[] = 'Name, email, password, and confirmation are required.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password !== '' && !preg_match('/^(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include 1 uppercase letter and 1 special character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors && get_partner_by_email($email)) {
        $errors[] = 'This partner email is already registered.';
    }

    if (!$errors) {
        $conn = db_connect();
        $sql = "INSERT INTO partners (owner_name, email, password, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errors[] = 'The partner account could not be prepared right now.';
        } else {
            $hashedPassword = hash_password($password);
            $stmt->bind_param("sss", $ownerName, $email, $hashedPassword);

            if ($stmt->execute()) {
                header('Location: partner-login.php?registered=1');
                exit;
            }

            $errors[] = 'The partner account could not be created right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | Partner Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css">
<link rel="stylesheet" href="assets/css/partner-portal.css">
</head>
<body class="light-mode">
<!-- Partner registration header with shortcuts back to the customer-facing pages. -->
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

        <nav class="topbar-right" aria-label="Partner register navigation">
            <a class="nav-link" href="Home.php">Home</a>
            <a class="nav-link" href="search.php">Search</a>
            <a class="nav-link" href="login.php">Customer login</a>
            <a class="nav-link" href="partner-login.php">Partner login</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero panel that explains the partner onboarding flow. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="store"></i>Partner portal</span>
        <h1>Create your business-owner account</h1>
        <p>Register as a partner to submit your business, track views and reservations, and send your listing into the pending approval queue before it appears on Where2Go.</p>
    </section>

    <!-- Registration layout pairing onboarding guidance with the account form. -->
    <section class="auth-shell" style="margin-top:24px;">
        <div class="panel-card">
            <h2>What happens next</h2>
            <div class="stack-list">
                <div class="repeat-card">
                    <strong>1. Create your partner account</strong>
                    <p>Use a dedicated business-owner email and password. This login stays separate from customer accounts.</p>
                </div>
                <div class="repeat-card">
                    <strong>2. Submit your business details</strong>
                    <p>Add your type, rules, photos, menus, working hours, offers, and reservation settings from the dashboard.</p>
                </div>
                <div class="repeat-card">
                    <strong>3. Wait for admin approval</strong>
                    <p>Your listing stays private and pending until the admin approves it for customers to see.</p>
                </div>
            </div>
        </div>

        <div class="auth-card">
            <h2>Register partner account</h2>
            <p class="helper-text">This creates the owner login only. You will add the actual business listing after sign-in.</p>

            <?php if ($errors): ?>
            <div class="messages">
                <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form action="partner-register.php" method="POST" class="form-grid">
                <div class="field">
                    <label for="owner_name">Owner name</label>
                    <input id="owner_name" type="text" name="owner_name" value="<?php echo htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your full name">
                </div>

                <div class="field">
                    <label for="email">Business email</label>
                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="owner@business.com">
                </div>

                <div class="grid-two">
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" placeholder="At least 8 characters">
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm password</label>
                        <input id="confirm_password" type="password" name="confirm_password" placeholder="Repeat password">
                    </div>
                </div>

                <p class="mini-note">Use at least 8 characters with 1 uppercase letter and 1 special character.</p>

                <div class="action-row">
                    <button class="primary-btn" type="submit"><i data-lucide="user-plus"></i>Create partner account</button>
                    <a class="secondary-btn" href="partner-login.php"><i data-lucide="log-in"></i>I already have an account</a>
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
