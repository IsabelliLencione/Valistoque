<?php
$host = 'localhost';
$db   = 'projeto_valistoque';
$user = 'root';
$pass = ''; // Senha padrão do XAMPP/WAMP costuma ser vazia

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
?>