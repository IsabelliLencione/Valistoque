<?php

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    if (AMBIENTE === 'desenvolvimento') {
        die("Falha na conexão MySQLi: " . $conn->connect_error);
    } else {
        error_log("Falha conexão MySQLi: " . $conn->connect_error);
        die("Erro ao conectar ao banco de dados. Tente novamente mais tarde.");
    }
}
$conn->set_charset(DB_CHARSET);

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo_opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_opcoes);
} catch (PDOException $e) {
    if (AMBIENTE === 'desenvolvimento') {
        die("Falha na conexão PDO: " . $e->getMessage());
    } else {
        error_log("Falha conexão PDO: " . $e->getMessage());
        die("Erro ao conectar ao banco de dados.");
    }
}