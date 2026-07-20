<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/Midtrans/Midtrans.php';

\Midtrans\Config::$serverKey = $_ENV['MIDTRANS_SERVER_KEY'];
\Midtrans\Config::$clientKey = $_ENV['MIDTRANS_CLIENT_KEY'];
\Midtrans\Config::$isProduction = filter_var($_ENV['MIDTRANS_IS_PRODUCTION'], FILTER_VALIDATE_BOOLEAN);
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;
