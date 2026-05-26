<?php
/**
 * config.local.example.php — Local & secret settings TEMPLATE.
 * قالب الإعدادات المحلية والسرّية.
 *
 * SETUP / الإعداد:
 *   1) Copy this file to:   includes/config.local.php
 *      انسخ هذا الملف إلى:  includes/config.local.php
 *   2) Fill in only the values you need, then save.
 *
 * `config.local.php` is git-ignored and is NEVER committed. Keep your keys there.
 * Every key can also be supplied as an ENV variable of the same name (env wins).
 *
 * IMPORTANT: The whole site runs with NO keys at all — it uses the free, public
 * openfootball dataset. Each key below only switches ON an OPTIONAL feature.
 * الموقع يعمل بالكامل بدون أي مفتاح؛ كل مفتاح هنا يفعّل ميزة اختيارية فقط.
 */
return [
    // ---------- Site URL / رابط الموقع ----------
    // Empty if served from the domain root. For a subfolder/production set the full URL.
    'SITE_URL' => '',                    // e.g. https://example.com  |  http://localhost/worldcup2026

    // ---------- MySQL — accounts & predictions (OPTIONAL) ----------
    // Only needed for user accounts. Leave DB_ENABLED=false to run in file-only mode.
    // مطلوبة فقط لنظام الحسابات. اتركها false للعمل بنظام الملفات.
    'DB_ENABLED' => false,
    'DB_HOST'    => '127.0.0.1',
    'DB_NAME'    => 'worldcup2026',
    'DB_USER'    => 'root',
    'DB_PASS'    => '',

    // ---------- Live scores — API-Football (OPTIONAL) ----------
    // Free tier = 100 requests/day. Get a key: https://dashboard.api-football.com
    'APIFOOTBALL_KEY' => '',

    // ---------- AI content — Anthropic Claude (OPTIONAL) ----------
    // Powers match previews/summaries & the heartbreak roast (Arabic dialects).
    // Get a key: https://console.anthropic.com   (uses the cheap Claude Haiku model)
    'CLAUDE_API_KEY'   => '',             // sk-ant-...
    'AI_ACTIVATE_FROM' => '2026-06-08',   // AI stays OFF before this date (cost guard)

    // ---------- Email — SMTP newsletter (OPTIONAL) ----------
    // Any SMTP provider works (e.g. Hostinger, Gmail app password, Mailgun...).
    'SMTP_HOST'   => '',                  // e.g. smtp.hostinger.com
    'SMTP_PORT'   => 465,                 // 465 = SSL, 587 = TLS
    'SMTP_USER'   => '',                  // full email address
    'SMTP_PASS'   => '',                  // mailbox / app password
    'SMTP_SECURE' => 'ssl',               // 'ssl' or 'tls'
    'MAIL_SECRET' => '',                  // random string for signing unsubscribe links (optional)

    // ---------- Contact / التواصل ----------
    'CONTACT_EMAIL' => 'info@example.com',
    'CONTACT_PHONE' => '',                // intl format e.g. +10000000000 (shows Call + WhatsApp)

    // ---------- Admin panel / لوحة التحكم ----------
    'ADMIN_PASS' => '',                   // strong password; empty = admin panel disabled

    // ---------- Installer guard / حماية أداة التثبيت ----------
    // Protects install.php & db_selftest.php from public access. Use 32+ random chars.
    'INSTALL_TOKEN' => '',

    // ---------- Telegram bot (OPTIONAL) ----------
    // Create a bot via @BotFather: https://t.me/BotFather
    'TELEGRAM_BOT_TOKEN'      => '',
    'TELEGRAM_WEBHOOK_SECRET' => '',      // random string; verifies incoming webhook calls

    // ---------- Search engine verification (OPTIONAL) ----------
    'GOOGLE_SITE_VERIFICATION' => '',     // from Google Search Console
    'BING_SITE_VERIFICATION'   => '',     // from Bing Webmaster Tools
];
