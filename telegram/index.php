<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// ================= DB =================
$db = sirey_getDatabase();

// ================= INPUT TELEGRAM =================
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['message'])) exit;

$msg = $data['message'];

$chat = (int)$msg['chat']['id'];
$text = trim($msg['text'] ?? '');

// ================= STATE =================
$state = getState($chat) ?? ['step' => 'init'];
$step  = $state['step'] ?? 'init';

// ================= USER =================
$user = getRegisteredUser($db, $chat);

// ================= LOAD HANDLER =================
require_once __DIR__ . '/handlers/command.php';
require_once __DIR__ . '/handlers/state.php';
require_once __DIR__ . '/handlers/menu.php';

// ================= EXECUTION =================
if (handleCommand($text, $chat, $user, $db)) exit;

if (handleState($step, $text, $chat, $state, $db)) exit;

if (handleMenu($text, $chat, $user, $db)) exit;