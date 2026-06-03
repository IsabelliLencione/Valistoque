<?php
/**
 * ============================================================
 *  VALISTOQUE - Redefinição efetiva da senha
 *  Recebe POST: codigo, nova_senha, confirmar_senha
 *  (precisa de sessão de recuperação ativa)
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirecionar('../../frontend/recuperasenha.html');
}

$codigo    = trim($_POST['codigo'] ?? '');
$senha     = $_POST['nova_senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';

if (!preg_match('/^\d{6}$/', $codigo)) alertaJs('Código inválido (6 dígitos).');
if (strlen($senha) < 6)                alertaJs('A nova senha deve ter no mínimo 6 caracteres.');
if ($senha !== $confirmar)             alertaJs('As senhas não conferem.');

$email  = $_SESSION['recup_email']  ?? null;
$perfil = $_SESSION['recup_perfil'] ?? null;

if (!$email || !$perfil) {
    alertaJs('Sessão expirada. Solicite um novo código.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM recuperacao_senha
                           WHERE codigo = ? AND email = ? AND perfil = ?
                             AND usado = 0 AND expira_em > NOW()
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$codigo, $email, $perfil]);
    $rec = $stmt->fetch();

    if (!$rec) alertaJs('Código inválido ou expirado!');

    $tabela = ($perfil === 'adm') ? 'adm' : 'func';
    $hash   = password_hash($senha, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    $upd1 = $pdo->prepare("UPDATE {$tabela}
                           SET Senha = ?, tentativas_login = 0, bloqueado_ate = NULL
                           WHERE id = ?");
    $upd1->execute([$hash, $rec['usuario_id']]);

    $upd2 = $pdo->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE id = ?");
    $upd2->execute([$rec['id']]);
    $pdo->commit();

    // Limpa dados de recuperação
    unset($_SESSION['recup_token'], $_SESSION['recup_email'], $_SESSION['recup_perfil']);

    echo "<script>
        alert('Senha redefinida com sucesso! Faça login com a nova senha.');
        window.location.href = '../../frontend/login.html';
    </script>";
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Erro redefinir senha: " . $e->getMessage());
    alertaJs('Erro ao redefinir senha.');
}
