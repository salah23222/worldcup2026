<?php
/**
 * manifest.php — بيان تطبيق الويب (PWA) ديناميكي حسب اللغة والنطاق.
 * يجعل الموقع قابلاً للتثبيت على الهاتف كتطبيق بشعار «26».
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$lang = current_lang();
$base = rtrim(SITE_URL, '/');   // فارغ محلياً → مسارات من الجذر

echo json_encode([
    'name'             => match($lang) { 'ar' => 'كأس العالم 2026', 'fr' => 'Coupe du Monde 2026', default => 'FIFA World Cup 2026' },
    'short_name'       => match($lang) { 'ar' => 'مونديال 2026', 'fr' => 'CDM 2026', default => 'WC 2026' },
    'description'      => match($lang) { 'ar' => 'التغطية الكاملة واللحظية لكأس العالم 2026', 'fr' => 'Couverture complète et en direct de la Coupe du Monde 2026', default => 'Full live FIFA World Cup 2026 coverage' },
    'lang'             => $lang,
    'dir'              => $lang === 'ar' ? 'rtl' : 'ltr',
    'start_url'        => $base . '/index.php?lang=' . $lang,
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#0a1626',
    'theme_color'      => '#0a1626',
    'icons'            => [
        ['src' => $base . '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => $base . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => $base . '/assets/img/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
