<?php
// Entry point / Router untuk Vercel Serverless Function

$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$file = ltrim($path, '/');

// Jika mengakses root (/), arahkan ke index.php di root
if (empty($file) || $file === '/') {
    $file = 'index.php';
}

$targetFile = __DIR__ . '/../' . $file;

// Cek apakah file ada dan berupa file nyata
if (file_exists($targetFile) && is_file($targetFile)) {
    // Abaikan jika mencoba mengakses vercel.json secara langsung
    if (basename($targetFile) === 'vercel.json') {
        http_response_code(403);
        echo "Forbidden";
        exit();
    }

    // Set Mime Type untuk static assets jika dipanggil via api router
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    if ($ext === 'css') {
        header('Content-Type: text/css');
    } elseif ($ext === 'js') {
        header('Content-Type: application/javascript');
    }

    require $targetFile;
} else {
    // Jika file tidak ditemukan, fallback ke index.php
    require __DIR__ . '/../index.php';
}
