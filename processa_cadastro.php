<?php
include('config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $cpf = $conn->real_escape_string($_POST['cpf']);
    // Criptografa a senha antes de salvar
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO adm (Email, Senha, cpf) VALUES ('$email', '$senha', '$cpf')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Cadastro realizado com sucesso!'); window.location.href='2tela_admin.html';</script>";
    } else {
        echo "Erro ao cadastrar: " . $conn->error;
    }
}
?>