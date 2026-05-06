<?php

$host = "127.0.0.1:3306";
$db = "projeto_valistoque";
$user = "root";
$pass = "12345678";

try {
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
echo "<h2 style='color:green;'>✅Conexão realizada com sucesso</h2>";
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
    echo "<h2 style='color:green;'>❌Conexão realizada com sucesso</h2>";
    echo $e->getMessage();
}
