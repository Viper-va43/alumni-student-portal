<?php
// Where2Go future work before or after launch.
// This file is intentionally non-visual. It is a working notes page for future product and code work.
// Keep anything that needs outside subscriptions, API keys, payments, or a bigger data model here until we are ready.
//
// Priority labels:
// High importance        = important for launch quality, trust, revenue, or operational safety.
// Moderate importance    = useful and valuable, but can wait until the core product is stable.
// Unknown / needs research = depends on cost, legal/payment setup, API keys, market demand, or technical risk.

// BEFORE LAUNCH - PARTNER PORTAL CORE
// 1. Advanced analytics dashboard.
//    Priority: High importance.
//    Show daily, weekly, and monthly views, reservations, clicks, conversion rate, top locations, offer performance, and peak days.
//    This makes the partner portal feel like a business tool instead of only a listing editor.
//
// 2. Reservation calendar upgrades.
//    Priority: High importance.
//    Add filters by business, location, reservation status, and date range. Add print/export once the calendar is stable.
//    The first version is already a two-week dashboard view; this is the next step.
//
// 3. Holiday and special-hours table.
//    Priority: High importance.
//    Weekly hours are not enough for Eid, New Year, private events, maintenance, or one-off closures.
//    Build a separate table such as business_special_hours with location_id, date, is_closed, open_time, and close_time.
//
// 4. Dynamic menu management.
//    Priority: High importance.
//    Replace the fixed three menu rows with unlimited menu items, categories, sorting, active/inactive state, and PDF/image uploads.
//    Restaurants and cafes need this before launch if menus become a major selling point.
//
// 5. Partner customer management.
//    Priority: Moderate importance.
//    Show reservation customer history, returning/new customer status, internal notes, VIP/no-show flags, and last reservation date.
//    Keep this privacy-safe and only show details allowed by the customer's booking/account data.
//
// 6. Review management replies.
//    Priority: Moderate importance.
//    Partners should view reviews, reply publicly, report inappropriate reviews, and filter by rating.
//    Partners must never be able to edit or delete customer reviews directly; admin keeps moderation control.
//
// 7. Profile completion improvements.
//    Priority: Moderate importance.
//    The first score is live on the dashboard. Later, add action links that jump directly to missing form sections.
//    Example: "Add 3 photos", "Add search tags", "Add working hours", "Add menu".
//
// 8. Advanced business page customization.
//    Priority: Moderate importance.
//    Version 1 gives partners controlled page styling: preset, accent color, cover image, logo, and tagline.
//    Later versions can add richer templates, menu-section layouts, custom gallery order, branded offer blocks, and seasonal campaigns.
//    Do not allow partners to upload arbitrary HTML/CSS/JavaScript. Keep customization controlled so pages stay fast, safe, accessible,
//    and consistent with Where2Go reservations, reviews, save buttons, and navigation.

// BEFORE LAUNCH - ADMIN AND OPERATIONS
// 9. Promotion request system.
//    Priority: High importance.
//    Partners can request Top Picks, homepage placement, category boosts, or campaign review.
//    Admin approves/rejects so platform placement stays controlled and trustworthy.
//
// 10. Business verification.
//     Priority: High importance.
//     Add statuses: not verified, pending verification, verified.
//     Partners can upload commercial record, tax card, business license, owner ID proof, or other local verification documents.
//     Admin reviews documents before the verified badge appears publicly.
//
// 11. Notification center.
//     Priority: High importance.
//     Notify partners about new reservations, cancellations, expired offers, approval changes, new reviews, and incomplete profiles.
//     Start with in-dashboard notifications; email/SMS/push can come later.
//
// 12. Admin quality controls.
//     Priority: High importance.
//     Add admin screens for flagged reviews, suspicious reservation activity, duplicate businesses, and incomplete partner profiles.

// AFTER LAUNCH - CUSTOMER ENGAGEMENT
// 13. Customer rank and level system.
//     Priority: Moderate importance.
//     Treat customers like players who level up by visiting new places, scanning QR codes, making reservations, and completing real visits.
//     Example ranks: Explorer, Local Guide, Cairo Scout, Nightlife Hunter, Food Adventurer, Culture Collector.
//     Add XP for first-time place visits, new categories, streaks, verified check-ins, reviews after visits, and trying partner offers.
//     Add badges for milestones such as 5 new places, 10 different areas, 3 museums, 5 restaurants, or first nightlife booking.
//     Add anti-cheat rules: one QR/check-in cooldown per place, no repeated farming from the same location, and admin fraud review.
//     This is strong for retention and fun, but the reservation/review/partner core should stay ahead of it.

// AFTER LAUNCH - MONETIZATION AND SCALE
// 14. Subscription plan system.
//     Priority: Unknown / needs research.
//     Plan examples: Free, Standard, Premium, Enterprise.
//     Do not add real payments until close to launch; payment gateways need accounts, testing, refunds, invoices, and legal review.
//
// 15. Premium branded page builder.
//     Priority: Unknown / needs research.
//     This can become a paid feature after launch.
//     Standard: default Where2Go style.
//     Premium: branded accent, cover image, theme preset, tagline, gallery ordering.
//     Enterprise: custom campaign sections approved by admin.
//     Keep admin approval for any public-facing branded template changes that affect trust or platform quality.
//
// 16. Staff and team access.
//     Priority: Moderate importance.
//     Add partner roles: owner, manager, reservation staff, marketing staff.
//     Each role needs permissions so a staff member can manage bookings without editing business ownership or billing.
//
// 17. Messaging/contact system.
//     Priority: Moderate importance.
//     Start with predefined messages for confirmation, missing details, cancellation reason, reminders, and post-visit offers.
//     Full chat can wait; predefined messages are safer and easier to moderate.
//
// 18. Export reports.
//     Priority: Moderate importance.
//     Add Excel/PDF exports for reservations, monthly analytics, customer reservation lists, and offer performance.
//     Useful for partners who report to managers or owners outside Where2Go.
//
// 19. Public SEO and visibility settings.
//     Priority: Moderate importance.
//     Add meta title, public short description, best-for tags, amenities, price range, and featured keywords.
//     This helps search and filtering, but public SEO should wait until final URLs and content rules are stable.
//
// 20. Maps and location APIs.
//     Priority: Unknown / needs research.
//     Add Google Maps or another provider only after choosing the paid API plan close to launch.
//     Needed for distance search, directions, nearby places, map pins, and geocoding partner addresses.
//
// 21. App store launch tasks.
//     Priority: High importance.
//     App Store and Google Play work should wait until branding, privacy policy, screenshots, support email, and production APIs are ready.
//     Once launch is close, this becomes a launch-blocking workstream.

// TECHNICAL NOTES
// 22. Keep partner placement controlled by admin.
//     Priority: High importance.
//     Partners can request promotion, but they should not directly control Top Picks, featured homepage slots, or ranking boosts.
//
// 23. Avoid adding paid outside services too early.
//     Priority: High importance.
//     Anything that requires Google, SMS, email provider, payment gateway, push notifications, or cloud subscriptions can be mocked first.
//
// 24. Prefer database tables over packed text for long-term features.
//     Priority: High importance.
//     The current blocked_dates field is fine for a simple MVP, but special hours and advanced calendars should use proper tables.
//
// 25. Keep mobile and website booking logic shared.
//     Priority: High importance.
//     Reservation rules should stay in includes/functions.php so the app and website do not drift apart.
