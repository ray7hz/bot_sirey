<?php
/**
 * Configuration file for Bot SiRey
 * Simplified structure for better maintainability
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rayhanrp_database_ta');

// Telegram Configuration
define('BOT_TOKEN', '8219558178:AAGONLX_MZxkGWHLwygUB-CaMM-_PjYJv3k');

return [
    'app' => [
        'name' => 'Bot SiRey',
        'timezone' => 'Asia/Jakarta',
        'state_file' => __DIR__ . '/data/state.json',
    ],
    'db' => [
        'host' => DB_HOST,
        'username' => DB_USER,
        'password' => DB_PASS,
        'database' => DB_NAME,
        'charset' => 'utf8mb4',
    ],
    'telegram' => [
        'bot_token' => BOT_TOKEN,
        'timeout' => 15,
    ],
];
