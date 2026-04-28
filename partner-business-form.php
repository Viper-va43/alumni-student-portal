<?php
// Load the partner business helpers and protect the form behind partner authentication.
require_once __DIR__ . '/includes/functions.php';

start_session();
require_partner_login();

$partnerId = (int) ($_SESSION['partner_id'] ?? 0);
$businessId = (int) ($_POST['business_id'] ?? ($_GET['business_id'] ?? 0));
$adminLoggedIn = is_admin_user();
$messages = [];

// Provide the default values for a brand-new business location row.
function partner_form_blank_location() {
    return [
        'location_id' => 0,
        'location_name' => '',
        'address' => '',
        'phone' => '',
        'promo_code' => '',
        'promo_details' => '',
        'capacity_per_hour' => 10,
        'has_reservations' => 1,
        'checkin_enabled' => 1,
        'hours' => get_default_hours_rows(),
    ];
}

// Rebuild the form state from submitted values after validation errors.
function build_partner_form_view($postData, $businessId = 0) {
    $postData = is_array($postData) ? $postData : [];
    $locations = normalize_partner_locations_input($postData['locations'] ?? [], $postData);

    if (!$locations) {
        $locations = [partner_form_blank_location()];
    }

    return [
        'business' => [
            'business_id' => $businessId,
            'name' => trim((string) ($postData['name'] ?? '')),
            'description' => trim((string) ($postData['description'] ?? '')),
            'rules' => trim((string) ($postData['rules'] ?? '')),
            'type' => trim((string) ($postData['type'] ?? 'restaurant')),
            'custom_type' => trim((string) ($postData['custom_type'] ?? '')),
            'logo_url' => trim((string) ($postData['logo_url'] ?? '')),
            'website' => trim((string) ($postData['website'] ?? '')),
            'approval_status' => 'pending',
            'review_note' => '',
            'reviewed_at' => null,
        ],
        'locations' => $locations,
        'photos' => array_map(function ($url) {
            return ['image_url' => trim((string) $url)];
        }, is_array($postData['photo_urls'] ?? null) ? $postData['photo_urls'] : []),
        'menus' => is_array($postData['menus'] ?? null) ? array_values($postData['menus']) : [],
        'offers' => is_array($postData['offers'] ?? null) ? array_values($postData['offers']) : [],
    ];
}

// Render one location editor card, including reservation settings and opening hours.
function render_partner_location_card($index, $location) {
    $location = is_array($location) ? $location : partner_form_blank_location();
    $hoursRows = is_array($location['hours'] ?? null) ? $location['hours'] : get_default_hours_rows();
    ?>
    <div class="repeat-card location-card" data-location-card data-dynamic-index="<?php echo (int) $index; ?>">
        <div class="dashboard-item-head">
            <div>
                <h3 style="margin:0 0 6px;">Location <?php echo (int) $index + 1; ?></h3>
                <p class="mini-note" style="margin:0;">Each location keeps its own address, phone, table count, reservation setting, and working hours.</p>
            </div>
            <button class="secondary-btn" type="button" data-remove-location><i data-lucide="trash-2"></i>Remove</button>
        </div>

        <input type="hidden" name="locations[<?php echo (int) $index; ?>][location_id]" value="<?php echo (int) ($location['location_id'] ?? 0); ?>">

        <div class="grid-two">
            <div class="field">
                <label for="location_name_<?php echo (int) $index; ?>">Location name</label>
                <input id="location_name_<?php echo (int) $index; ?>" type="text" name="locations[<?php echo (int) $index; ?>][location_name]" value="<?php echo htmlspecialchars((string) ($location['location_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Main branch, Downtown branch, etc.">
            </div>
            <div class="field">
                <label for="location_phone_<?php echo (int) $index; ?>">Phone</label>
                <input id="location_phone_<?php echo (int) $index; ?>" type="text" name="locations[<?php echo (int) $index; ?>][phone]" value="<?php echo htmlspecialchars((string) ($location['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="+20...">
            </div>
        </div>

        <div class="field">
            <label for="location_address_<?php echo (int) $index; ?>">Address</label>
            <input id="location_address_<?php echo (int) $index; ?>" type="text" name="locations[<?php echo (int) $index; ?>][address]" value="<?php echo htmlspecialchars((string) ($location['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Branch address">
        </div>

        <div class="grid-two">
            <div class="field">
                <label for="location_capacity_<?php echo (int) $index; ?>">Available tables per hour</label>
                <input id="location_capacity_<?php echo (int) $index; ?>" type="number" min="1" name="locations[<?php echo (int) $index; ?>][capacity_per_hour]" value="<?php echo (int) ($location['capacity_per_hour'] ?? 10); ?>">
                <p class="mini-note" style="margin:0;">Internal only. Customers will not see this. Each table is treated as up to 4 people.</p>
            </div>
            <div class="field">
                <span>Reservation setting</span>
                <label class="checkbox-row">
                    <input type="checkbox" name="locations[<?php echo (int) $index; ?>][has_reservations]" value="1"<?php echo !empty($location['has_reservations']) ? ' checked' : ''; ?>>
                    <span>Allow reservation requests at this location</span>
                </label>
            </div>
        </div>

        <div class="panel-card" style="padding:18px;margin-top:16px;">
            <div class="dashboard-item-head">
                <div>
                    <h3 style="margin:0 0 6px;">QR promo and rewards</h3>
                    <p class="mini-note" style="margin:0;">Where2Go will generate one QR code for this location after you save. Customers scan it in-store to unlock the promo code and collect the default 20 points plus 20 XP reward.</p>
                </div>
            </div>

            <div class="grid-two">
                <div class="field">
                    <label for="location_promo_code_<?php echo (int) $index; ?>">Promo code</label>
                    <input id="location_promo_code_<?php echo (int) $index; ?>" type="text" name="locations[<?php echo (int) $index; ?>][promo_code]" value="<?php echo htmlspecialchars((string) ($location['promo_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="WELCOME20">
                </div>
                <div class="field">
                    <span>QR check-ins</span>
                    <label class="checkbox-row">
                        <input type="checkbox" name="locations[<?php echo (int) $index; ?>][checkin_enabled]" value="1"<?php echo array_key_exists('checkin_enabled', $location) ? (!empty($location['checkin_enabled']) ? ' checked' : '') : ' checked'; ?>>
                        <span>Allow customers to scan this location QR and earn rewards</span>
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="location_promo_details_<?php echo (int) $index; ?>">Promo details</label>
                <textarea id="location_promo_details_<?php echo (int) $index; ?>" name="locations[<?php echo (int) $index; ?>][promo_details]" placeholder="Describe what the customer gets from this code or location scan"><?php echo htmlspecialchars((string) ($location['promo_details'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="mini-note" style="margin:0;">Level 1 currently starts at 100 XP. The exact level table can be updated later without changing the partner form again.</p>
            </div>
        </div>

        <div class="panel-card" style="padding:18px;">
            <div class="dashboard-item-head">
                <div>
                    <h3 style="margin:0 0 6px;">Working hours</h3>
                    <p class="mini-note" style="margin:0;">Use the quick apply row if most days share the same schedule.</p>
                </div>
            </div>
            <div class="inline-fields" style="margin-bottom:16px;">
                <div class="field">
                    <label>Apply opening time</label>
                    <input type="time" data-apply-open>
                </div>
                <div class="field">
                    <label>Apply closing time</label>
                    <input type="time" data-apply-close>
                </div>
                <div class="field" style="align-content:end;">
                    <button class="secondary-btn" type="button" data-apply-hours><i data-lucide="copy"></i>Apply to all days</button>
                </div>
            </div>

            <div class="hours-grid">
                <?php for ($day = 0; $day <= 6; $day++): ?>
                <?php
                $row = $hoursRows[$day] ?? ['is_closed' => 0, 'open_time' => '', 'close_time' => ''];
                $openTime = trim((string) ($row['open_time'] ?? ''));
                $closeTime = trim((string) ($row['close_time'] ?? ''));
                ?>
                <div class="hours-row" data-hours-row>
                    <div class="hours-header">
                        <strong><?php echo htmlspecialchars(get_day_name_from_index($day), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <label class="checkbox-row">
                            <input type="checkbox" data-hours-closed name="locations[<?php echo (int) $index; ?>][hours][<?php echo $day; ?>][is_closed]" value="1"<?php echo !empty($row['is_closed']) ? ' checked' : ''; ?>>
                            <span>Closed</span>
                        </label>
                    </div>
                    <div class="inline-fields">
                        <div class="field">
                            <label>Open</label>
                            <input data-hours-time type="time" name="locations[<?php echo (int) $index; ?>][hours][<?php echo $day; ?>][open_time]" value="<?php echo htmlspecialchars(substr($openTime, 0, 5), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field">
                            <label>Close</label>
                            <input data-hours-time type="time" name="locations[<?php echo (int) $index; ?>][hours][<?php echo $day; ?>][close_time]" value="<?php echo htmlspecialchars(substr($closeTime, 0, 5), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php
}

// Render one promotional offer card inside the partner business form.
function render_partner_offer_card($index, $offer) {
    $offer = is_array($offer) ? $offer : [];
    ?>
    <div class="repeat-card offer-card" data-offer-card data-dynamic-index="<?php echo (int) $index; ?>">
        <div class="dashboard-item-head">
            <div>
                <h3 style="margin:0 0 6px;">Offer <?php echo (int) $index + 1; ?></h3>
                <p class="mini-note" style="margin:0;">Keep offers clean and focused. You can add more only when needed.</p>
            </div>
            <button class="secondary-btn" type="button" data-remove-offer><i data-lucide="trash-2"></i>Remove</button>
        </div>
        <div class="grid-two">
            <div class="field">
                <label>Offer title</label>
                <input type="text" name="offers[<?php echo (int) $index; ?>][title]" value="<?php echo htmlspecialchars((string) ($offer['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Weekend offer">
            </div>
            <div class="field">
                <label>Discount %</label>
                <input type="number" step="0.01" min="0" name="offers[<?php echo (int) $index; ?>][discount]" value="<?php echo htmlspecialchars((string) ($offer['discount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="15">
            </div>
        </div>
        <div class="field">
            <label>Offer description</label>
            <textarea name="offers[<?php echo (int) $index; ?>][description]" placeholder="Describe the offer"><?php echo htmlspecialchars((string) ($offer['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="grid-two">
            <div class="field">
                <label>Start date</label>
                <input type="date" name="offers[<?php echo (int) $index; ?>][start_date]" value="<?php echo htmlspecialchars((string) ($offer['start_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="field">
                <label>End date</label>
                <input type="date" name="offers[<?php echo (int) $index; ?>][end_date]" value="<?php echo htmlspecialchars((string) ($offer['end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <label class="checkbox-row">
            <input type="checkbox" name="offers[<?php echo (int) $index; ?>][is_active]" value="1"<?php echo !empty($offer['is_active']) ? ' checked' : ''; ?>>
            <span>Mark this offer as active</span>
        </label>
    </div>
    <?php
}

$formData = get_partner_business_form_data($partnerId, $businessId);

if ($businessId > 0 && (int) ($formData['business']['business_id'] ?? 0) !== $businessId) {
    header('Location: partner-dashboard.php');
    exit;
}

if (($_GET['saved'] ?? '') === '1') {
    $messages[] = ['type' => 'success', 'text' => 'Your business details were saved successfully.'];
}

// Save the submitted business data or rebuild the form with an error message.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = save_partner_business_submission($partnerId, $_POST, $businessId);

    if (!empty($result['ok'])) {
        header('Location: partner-business-form.php?business_id=' . (int) ($result['business_id'] ?? 0) . '&saved=1');
        exit;
    }

    $messages[] = ['type' => 'error', 'text' => (string) ($result['message'] ?? 'The business could not be saved right now.')];
    $formData = build_partner_form_view($_POST, $businessId);
}

$business = $formData['business'] ?? [];
$locations = is_array($formData['locations'] ?? null) ? array_values($formData['locations']) : [partner_form_blank_location()];
$photoRows = array_map(function ($photo) {
    return trim((string) ($photo['image_url'] ?? ''));
}, is_array($formData['photos'] ?? null) ? $formData['photos'] : []);
$photoRows = array_slice($photoRows, 0, 6);
$menuRows = is_array($formData['menus'] ?? null) ? array_values($formData['menus']) : [];
$offerRows = is_array($formData['offers'] ?? null) ? array_values($formData['offers']) : [];

while (count($photoRows) < 6) {
    $photoRows[] = '';
}

while (count($menuRows) < 3) {
    $menuRows[] = ['title' => '', 'file_url' => ''];
}

if (!$offerRows) {
    $offerRows[] = [
        'title' => '',
        'description' => '',
        'discount' => '',
        'start_date' => '',
        'end_date' => '',
        'is_active' => 0,
    ];
}

$businessStatus = trim((string) ($business['approval_status'] ?? 'pending'));
$isEditing = (int) ($business['business_id'] ?? 0) > 0;
$typeOptions = [
    'restaurant' => ['label' => 'Restaurant', 'icon' => 'utensils-crossed'],
    'cafe' => ['label' => 'Cafe', 'icon' => 'coffee'],
    'activity' => ['label' => 'Activity', 'icon' => 'mountain-snow'],
    'entertainment' => ['label' => 'Entertainment', 'icon' => 'star'],
    'nightlife' => ['label' => 'Nightlife', 'icon' => 'music-4'],
    'other' => ['label' => 'Other', 'icon' => 'building-2'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Where2Go | <?php echo $isEditing ? 'Edit business' : 'Add business'; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="assets/css/account.css">
<link rel="stylesheet" href="assets/css/partner-portal.css">
</head>
<body class="light-mode">
<!-- Business form header with shortcuts back to the partner workspace. -->
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

        <nav class="topbar-right" aria-label="Partner business form navigation">
            <a class="nav-link" href="partner-dashboard.php">Dashboard</a>
            <a class="nav-link" href="Home.php">Home</a>
            <?php if ($adminLoggedIn): ?>
            <a class="nav-link" href="admin/business-approvals.php">Approvals</a>
            <?php endif; ?>
            <a class="primary-btn" href="partner-logout.php"><i data-lucide="log-out"></i>Logout</a>
        </nav>
    </div>
</header>

<main class="main-inner">
    <!-- Hero summary showing whether the business is new, editable, approved, or rejected. -->
    <section class="hero-panel">
        <span class="eyebrow"><i data-lucide="store"></i><?php echo $isEditing ? 'Edit business listing' : 'Create business listing'; ?></span>
        <h1><?php echo $isEditing ? 'Update your business details' : 'Add a business to Where2Go'; ?></h1>
        <p><?php echo $businessStatus === 'approved' ? 'Approved listings stay live while you update them from the dashboard.' : 'New and resubmitted listings stay private until the admin approves them.'; ?></p>
        <div class="profile-stats">
            <span class="status-pill <?php echo htmlspecialchars($businessStatus, ENT_QUOTES, 'UTF-8'); ?>">
                <i data-lucide="<?php echo $businessStatus === 'approved' ? 'badge-check' : ($businessStatus === 'rejected' ? 'x-circle' : 'clock-3'); ?>"></i>
                <?php echo htmlspecialchars(ucfirst($businessStatus), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($isEditing): ?>
            <a class="secondary-btn" href="place.php?business_id=<?php echo (int) ($business['business_id'] ?? 0); ?>"><i data-lucide="eye"></i>Preview page</a>
            <?php endif; ?>
        </div>
        <?php if ($businessStatus === 'rejected' && trim((string) ($business['review_note'] ?? '')) !== ''): ?>
        <div class="messages" style="margin-top:18px;">
            <div class="message error">Admin note: <?php echo htmlspecialchars((string) $business['review_note'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($messages): ?>
    <div class="messages" style="margin-top:24px;">
        <?php foreach ($messages as $message): ?>
        <div class="message <?php echo htmlspecialchars((string) $message['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $message['text'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main business form covering identity, locations, media, menus, and offers. -->
    <form action="partner-business-form.php<?php echo $businessId > 0 ? '?business_id=' . $businessId : ''; ?>" method="POST" class="form-grid" style="margin-top:24px;">
        <input type="hidden" name="business_id" value="<?php echo $businessId; ?>">

        <!-- Basic business identity fields such as the name, type, website, and description. -->
        <section class="panel-card">
            <h2>Business identity</h2>
            <div class="grid-two">
                <div class="field">
                    <label for="name">Business name</label>
                    <input id="name" type="text" name="name" value="<?php echo htmlspecialchars((string) ($business['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your business name">
                </div>
                <div class="field">
                    <label for="website">Website</label>
                    <input id="website" type="url" name="website" value="<?php echo htmlspecialchars((string) ($business['website'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://yourbusiness.com">
                </div>
            </div>

            <div class="field">
                <label for="logo_url">Logo URL</label>
                <input id="logo_url" type="url" name="logo_url" value="<?php echo htmlspecialchars((string) ($business['logo_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://...">
            </div>

            <div class="field">
                <span class="legend-title">Business type</span>
                <div class="icon-grid">
                    <?php foreach ($typeOptions as $value => $option): ?>
                    <label class="icon-option">
                        <input type="radio" name="type" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($business['type'] ?? 'restaurant') === $value) ? ' checked' : ''; ?>>
                        <span class="icon-option-card">
                            <i data-lucide="<?php echo htmlspecialchars((string) $option['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <strong><?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field" data-custom-type-wrap hidden>
                <label for="custom_type">Other business type</label>
                <input id="custom_type" type="text" name="custom_type" value="<?php echo htmlspecialchars((string) ($business['custom_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Specify the business type">
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Tell customers what makes your place worth visiting"><?php echo htmlspecialchars((string) ($business['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="field">
                <label for="rules">Rules / policies</label>
                <textarea id="rules" name="rules" placeholder="Age limits, dress code, cancellation rules, or anything customers should know"><?php echo htmlspecialchars((string) ($business['rules'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </section>

        <!-- Location editor for branch-specific addresses, hours, and booking capacity. -->
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Locations and reservations</h2>
                    <p class="section-copy">Each location can have its own schedule, phone number, and table count.</p>
                </div>
                <button class="secondary-btn" type="button" data-add-location><i data-lucide="plus"></i>Add location</button>
            </div>
            <div class="stack-list" data-location-list>
                <?php foreach ($locations as $index => $location): ?>
                <?php render_partner_location_card($index, $location); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Photo URL inputs that feed the public gallery on the business detail page. -->
        <section class="panel-card">
            <h2>Photos</h2>
            <p class="section-copy">Add up to 6 image URLs for the business page gallery. The first image becomes the main public photo.</p>
            <div class="stack-list">
                <?php foreach ($photoRows as $index => $photoUrl): ?>
                <div class="field">
                    <label for="photo_<?php echo $index; ?>">Photo URL <?php echo $index + 1; ?></label>
                    <input id="photo_<?php echo $index; ?>" type="url" name="photo_urls[]" value="<?php echo htmlspecialchars((string) $photoUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://...">
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Menu links that give customers direct access to downloadable or hosted menus. -->
        <section class="panel-card">
            <h2>Menus</h2>
            <div class="stack-list">
                <?php foreach ($menuRows as $index => $menu): ?>
                <div class="repeat-card">
                    <div class="grid-two">
                        <div class="field">
                            <label for="menu_title_<?php echo $index; ?>">Menu title</label>
                            <input id="menu_title_<?php echo $index; ?>" type="text" name="menus[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars((string) ($menu['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Main menu">
                        </div>
                        <div class="field">
                            <label for="menu_url_<?php echo $index; ?>">Menu file / URL</label>
                            <input id="menu_url_<?php echo $index; ?>" type="url" name="menus[<?php echo $index; ?>][file_url]" value="<?php echo htmlspecialchars((string) ($menu['file_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://...">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Offer editor for optional promotions that can surface across discovery pages. -->
        <section class="panel-card">
            <div class="section-row">
                <div>
                    <h2 style="margin-bottom:8px;">Offers</h2>
                    <p class="section-copy">Start with one offer and add more only if you need them.</p>
                </div>
                <button class="secondary-btn" type="button" data-add-offer><i data-lucide="plus"></i>Add offer</button>
            </div>
            <div class="stack-list" data-offer-list>
                <?php foreach ($offerRows as $index => $offer): ?>
                <?php render_partner_offer_card($index, $offer); ?>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="action-row">
            <button class="primary-btn" type="submit"><i data-lucide="save"></i><?php echo $isEditing ? 'Save changes' : 'Submit business'; ?></button>
            <a class="secondary-btn" href="partner-dashboard.php"><i data-lucide="arrow-left"></i>Back to dashboard</a>
        </div>
    </form>
</main>

<!-- Hidden template used by JavaScript when the partner adds another location card. -->
<template id="location-template">
    <?php render_partner_location_card('__INDEX__', partner_form_blank_location()); ?>
</template>

<!-- Hidden template used by JavaScript when the partner adds another offer card. -->
<template id="offer-template">
    <?php render_partner_offer_card('__INDEX__', [
        'title' => '',
        'description' => '',
        'discount' => '',
        'start_date' => '',
        'end_date' => '',
        'is_active' => 0,
    ]); ?>
</template>

<script>
// Keep the shared account script initialized even though this page does not expose saved places.
window.where2goPageData = <?php echo json_encode(['visitedPlaceIds' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/js/account.js"></script>
<script src="assets/js/partner-portal.js"></script>
</body>
</html>
