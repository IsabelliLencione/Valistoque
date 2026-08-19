<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/funcoes.php';

validarMetodo('POST');
$dados = obterDadosRequisicao();
$email = strtolower(limpar($dados['email'] ?? ''));
$codigo = limpar($dados['codigo'] ?? '');
$senha = (string) ($dados['senha'] ?? '');
$confirmacao = (string) ($dados['confirmar_senha'] ?? $dados['confirmsenha'] ?? '');

if (!validarEmail($email) || !preg_match('/^\d{6}$/', $codigo) || strlen($senha) < 6) {
    responderJson(false, 'Dados inválidos para redefinição.', null, 422);
}
if ($senha !== $confirmacao) {
    responderJson(false, 'As senhas não conferem.', null, 422);
}

$pdo = pdo();
$stmt = $pdo->prepare('SELECT * FROM recuperacao_senha WHERE email = :email AND codigo = :codigo AND usado_em IS NULL AND expira_em >= NOW() ORDER BY id DESC LIMIT 1');
$stmt->execute([':email' => $email, ':codigo' => $codigo]);
$recuperacao = $stmt->fetch();
if (!$recuperacao) {
    responderJson(false, 'Código inválido ou expirado.', null, 422);
}

$tabela = tabelaPorPerfil((string) $recuperacao['perfil']);
if ($tabela === '') responderJson(false, 'Perfil de recuperação inválido.', null, 422);

$upd = $pdo->prepare("UPDATE {$tabela} SET senha = :senha, password_updated_at = NOW() WHERE id = :id AND email = :email");
$upd->execute([
    ':senha' => password_hash($senha, PASSWORD_BCRYPT),
    ':id' => (int) $recuperacao['usuario_id'],
    ':email' => $email,
]);

$mark = $pdo->prepare('UPDATE recuperacao_senha SET usado_em = NOW() WHERE id = :id');
$mark->execute([':id' => (int) $recuperacao['id']]);

registrarLog(['id' => (int) $recuperacao['usuario_id'], 'perfil' => (string) $recuperacao['perfil']], 'redefinir_senha', 'recuperacao_senha', (int) $recuperacao['id']);
responderJson(true, 'Senha redefinida com sucesso.');
