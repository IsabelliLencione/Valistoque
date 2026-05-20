<?php

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirecionar('../../frontend/1tela.html');
}


$nome       = trim($_POST['nome']       ?? '');
$email      = trim($_POST['email']      ?? '');
$cpf        = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$senha      = $_POST['senha']            ?? '';
$confirmar  = $_POST['confirmar_senha']  ?? '';
$perfil     = $_POST['perfil']           ?? 'adm';

// --- 2. Validações ---
if ($nome === '' || strlen($nome) < 3) {
    alertaJs('Informe um nome válido (mínimo 3 caracteres).');
}
if (!validarEmail($email)) {
    alertaJs('E-mail inválido!');
}
if (!validarCpf($cpf)) {
    alertaJs('CPF inválido!');
}
if (strlen($senha) < 6) {
    alertaJs('A senha deve ter no mínimo 6 caracteres.');
}
if ($senha !== $confirmar) {
    alertaJs('As senhas não conferem!');
}
if (!in_array($perfil, ['adm', 'func'], true)) {
    alertaJs('Perfil inválido!');
}

$tabela = ($perfil === 'adm') ? 'adm' : 'func';

try {
   
    $stmt = $pdo->prepare("SELECT id FROM {$tabela} WHERE Email = ? OR cpf = ? LIMIT 1");
    $stmt->execute([$email, $cpf]);
    if ($stmt->fetch()) {
        alertaJs('E-mail ou CPF já cadastrado!');
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $idCriador = ($perfil === 'func' && ehAdmin()) ? $_SESSION['usuario_id'] : null;

    if ($perfil === 'func') {
        $sql = "INSERT INTO func (nome, Email, Senha, cpf, id_adm_criador)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $email, $hash, $cpf, $idCriador]);
    } else {
        $sql = "INSERT INTO adm (nome, Email, Senha, cpf) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $email, $hash, $cpf]);
    }

    registrarLog('CADASTRO', "Novo usuário {$perfil}: {$email}");

    $tela = ($perfil === 'adm') ? '2tela_admin.html' : '2tela_func.html';
    echo "<script>
            alert('Cadastro realizado com sucesso! Faça seu login.');
            window.location.href = '../../frontend/{$tela}';
          </script>";
    exit;

} catch (PDOException $e) {
    error_log("Erro cadastro: " . $e->getMessage());
    alertaJs('Erro ao cadastrar: ' . (AMBIENTE === 'desenvolvimento' ? $e->getMessage() : 'erro interno'));
}