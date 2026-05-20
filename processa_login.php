<?php

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirecionar('../../frontend/1tela.html', 'Acesso inválido.', 'erro');
}

$email  = trim($_POST['email']  ?? '');
$senha  = $_POST['senha']        ?? '';
$perfil = $_POST['perfil']       ?? '';

if (!validarEmail($email)) {
    alertaJs('E-mail inválido!');
}
if (empty($senha)) {
    alertaJs('A senha é obrigatória!');
}
if (!in_array($perfil, ['adm', 'func'], true)) {
    alertaJs('Perfil inválido!');
}

$tabela     = ($perfil === 'adm') ? 'adm' : 'func';
$paginaLogin = ($perfil === 'adm') ? '2tela_admin.html' : '2tela_func.html';

try {
  
    $stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE Email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        alertaJs('Usuário não encontrado!');
    }

    if (!empty($usuario['bloqueado_ate']) && strtotime($usuario['bloqueado_ate']) > time()) {
        $minutos = ceil((strtotime($usuario['bloqueado_ate']) - time()) / 60);
        alertaJs("Conta bloqueada. Tente novamente em {$minutos} minuto(s).");
    }

    if (isset($usuario['ativo']) && (int)$usuario['ativo'] === 0) {
        alertaJs('Sua conta está desativada. Procure o administrador.');
    }

    // --- 4. Verifica a senha ---
    $senhaOk = password_verify($senha, $usuario['Senha']);

    if (!$senhaOk && $senha === $usuario['Senha']) {
        $senhaOk = true;
        
        $novoHash = password_hash($senha, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE {$tabela} SET Senha = ? WHERE id = ?");
        $upd->execute([$novoHash, $usuario['id']]);
    }

    if (!$senhaOk) {
       
        $tentativas = (int)($usuario['tentativas_login'] ?? 0) + 1;
        $bloqueado  = null;
        if ($tentativas >= MAX_TENTATIVAS_LOGIN) {
            $bloqueado = date('Y-m-d H:i:s', time() + TEMPO_BLOQUEIO_MINUTOS * 60);
            $tentativas = 0;
        }
        $upd = $pdo->prepare("UPDATE {$tabela}
                              SET tentativas_login = ?, bloqueado_ate = ?
                              WHERE id = ?");
        $upd->execute([$tentativas, $bloqueado, $usuario['id']]);

        if ($bloqueado) {
            alertaJs("Muitas tentativas! Conta bloqueada por " . TEMPO_BLOQUEIO_MINUTOS . " minutos.");
        }
        $rest = MAX_TENTATIVAS_LOGIN - $tentativas;
        alertaJs("Senha incorreta! Tentativas restantes: {$rest}");
    }

    $upd = $pdo->prepare("UPDATE {$tabela}
                          SET tentativas_login = 0, bloqueado_ate = NULL
                          WHERE id = ?");
    $upd->execute([$usuario['id']]);

    session_regenerate_id(true);
    $_SESSION['usuario_id']      = $usuario['id'];
    $_SESSION['usuario_email']   = $usuario['Email'];
    $_SESSION['usuario_nome']    = $usuario['nome'] ?? $usuario['Email'];
    $_SESSION['perfil']          = $perfil;
    $_SESSION['ultima_atividade'] = time();

    registrarLog('LOGIN', "Login bem-sucedido ({$perfil})");

   
    redirecionar('../../frontend/Principal.html', 'Bem-vindo(a), ' . $_SESSION['usuario_nome'] . '!', 'sucesso');

} catch (PDOException $e) {
    error_log("Erro login: " . $e->getMessage());
    alertaJs('Erro interno. Tente novamente.');
}