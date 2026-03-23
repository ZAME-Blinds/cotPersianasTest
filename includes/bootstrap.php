<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$users = require __DIR__ . '/../config/users.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/view.php';
