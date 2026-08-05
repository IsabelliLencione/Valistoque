<?php
declare(strict_types=1);

const APP_NAME = 'Valistoque';
const APP_ENV = 'development';
const APP_URL = 'http://localhost/valistoque_backend_corrigido';

const DB_HOST = 'localhost';
const DB_NAME = 'projeto_valistoque';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

const SESSION_NAME = 'VALISTOQUESESSID';
const SESSION_TIMEOUT = 3600;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_BLOCK_MINUTES = 15;
const RECOVERY_CODE_TTL_MINUTES = 30;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => false,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['ultimo_acesso'])) {
    $_SESSION['ultimo_acesso'] = time();
}

if ((time() - (int) $_SESSION['ultimo_acesso']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['ultimo_acesso'] = time();
