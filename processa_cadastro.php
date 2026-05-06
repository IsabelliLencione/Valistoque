<?php
include('config.php');
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $perfil = $_POST['perfil']; // 'adm' ou 'func'

    // Proteção básica contra SQL Injection
    $email = $conn->real_escape_string($email);
    
    $tabela = ($perfil == 'adm') ? 'adm' : 'func';
    $sql = "SELECT * FROM $tabela WHERE Email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        // Verificação de senha (estou usando password_verify para segurança)
        if (password_verify($senha, $usuario['Senha']) || $senha == $usuario['Senha']) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_email'] = $usuario['Email'];
            $_SESSION['perfil'] = $perfil;
            
            header("Location: Principal.html");
        } else {
            echo "<script>alert('Senha incorreta!'); history.back();</script>";
        }
    } else {
        echo "<script>alert('Usuário não encontrado!'); history.back();</script>";
    }
}
?>