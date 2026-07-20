<?php
/**
 * Midtrans PHP Library - Minimal Loader untuk Snap API
 * Hanya load file yang diperlukan untuk Snap payment
 */

// Load file utama untuk Snap API
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/ApiRequestor.php';
require_once __DIR__ . '/Sanitizer.php';   // ← WAJIB untuk Snap.php
require_once __DIR__ . '/Snap.php';

// File tambahan (opsional)
if (file_exists(__DIR__ . '/Notification.php')) {
    require_once __DIR__ . '/Notification.php';
}

if (file_exists(__DIR__ . '/Transaction.php')) {
    require_once __DIR__ . '/Transaction.php';
}

if (file_exists(__DIR__ . '/CoreApi.php')) {
    require_once __DIR__ . '/CoreApi.php';
}
?>