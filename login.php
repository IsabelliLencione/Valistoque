<?php
require_once __DIR__ . "/../Config/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM biblioteca_login.users where username = ?");
    $stmt->execute([$username]);
    $user=$stmt->fetch();

    if ($user && password_verify($password, $user["password_hash"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];

        header("Location: /biblioteca_login/livros/index.php");
        exit;    
    } else {
        $erro = "Usuário ou senha inválida";
    }
}
?>