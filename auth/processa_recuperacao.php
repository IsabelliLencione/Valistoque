<?php
/**
 * ============================================================
 *  VALISTOQUE - Solicitar recuperação de senha
 *  Gera código de 6 dígitos e token válido por 30 minutos
 * ============================================================
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirecionar('../../frontend/recuperasenha.html');
}

$email  = trim($_POST['email'] ?? '');
$cpf    = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$perfil = $_POST['perfil'] ?? 'adm';

if (!validarEmail($email) || !validarCpf($cpf)) {
    alertaJs('E-mail ou CPF inválido!');
}
if (!in_array($perfil, ['adm', 'func'], true)) $perfil = 'adm';
$tabela = ($perfil === 'adm') ? 'adm' : 'func';

try {
    // Verifica se usuário existe
    $stmt = $pdo->prepare("SELECT id, Email FROM {$tabela} WHERE Email = ? AND cpf = ? LIMIT 1");
    $stmt->execute([$email, $cpf]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        alertaJs('Dados não encontrados. Verifique e-mail e CPF.');
    }

    // Gera código e token
    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token  = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', time() + 1800); // 30 min

    // Invalida códigos antigos
    $upd = $pdo->prepare("UPDATE recuperacao_senha SET usado = 1
                          WHERE perfil = ? AND usuario_id = ? AND usado = 0");
    $upd->execute([$perfil, $usuario['id']]);

    // Insere novo
    $ins = $pdo->prepare("INSERT INTO recuperacao_senha
        (perfil, usuario_id, email, codigo, token, expira_em)
        VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$perfil, $usuario['id'], $email, $codigo, $token, $expira]);

    registrarLog('RECUPERACAO_SENHA', "Solicitação para {$email}");

    $_SESSION['recup_token']  = $token;
    $_SESSION['recup_email']  = $email;
    $_SESSION['recup_perfil'] = $perfil;

    // Em produção, este código seria enviado por e-mail.
    echo "<!DOCTYPE html>
    <html lang='pt-br'><head><meta charset='UTF-8'>
    <title>Código de Recuperação</title>
    <style>
        body{font-family:sans-serif;background:linear-gradient(to bottom,#fff 70%,#708090);
             min-height:100vh;display:flex;align-items:center;justify-content:center;}
        .box{background:rgba(255,255,255,.9);border-radius:14px;padding:40px;
             box-shadow:0 4px 20px rgba(0,0,0,.15);text-align:center;max-width:420px;}
        .codigo{font-size:48px;letter-spacing:6px;color:#4682B4;font-weight:bold;
                margin:20px 0;background:#f1f5f8;padding:18px;border-radius:10px;}
        a{display:inline-block;margin-top:18px;background:#4682B4;color:#fff;
          text-decoration:none;padding:12px 30px;border-radius:30px;font-weight:600;}
    </style></head><body>
    <div class='box'>
        <h2>📧 Código de Recuperação</h2>
        <p>Em produção, enviaríamos este código para <b>{$email}</b>.</p>
        <div class='codigo'>{$codigo}</div>
        <p>Válido por 30 minutos.</p>
        <a href='../../frontend/codigo.html'>Inserir código →</a>
    </div></body></html>";
    exit;

} catch (PDOException $e) {
    error_log("Erro recuperação: " . $e->getMessage());
    alertaJs('Erro interno. Tente novamente.');
}
