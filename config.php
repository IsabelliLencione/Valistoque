<?php
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

define('AMBIENTE', 'desenvolvimento'); 

if (AMBIENTE === 'desenvolvimento') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/erros.log');
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'projeto_valistoque');
define('DB_USER', 'root');
define('DB_PASS', '');          a
define('DB_CHARSET', 'utf8mb4');

define('APP_NOME', 'Valistoque');
define('APP_URL', 'http://localhost/valistoque');
define('SESSION_TIMEOUT', 3600);           
define('MAX_TENTATIVAS_LOGIN', 5);          
define('TEMPO_BLOQUEIO_MINUTOS', 15);

define('DIAS_VALIDADE_PADRAO', 15);
define('ESTOQUE_MIN_PADRAO', 20);
define('PRATELEIRA_MIN_PADRAO', 20);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes.php';