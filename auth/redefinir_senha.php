<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

validarMetodo('POST');

$dados = obterDadosRequisicao();
$email = strtolower(limpar($dados['email'] ?? ''));
$codigo = limpar($dados['codigo'] ?? '');
$novaSenha = (string) ($dados['nova_senha'] ?? $dados['senha'] ?? '');
$perfil = perfilNormalizado((string) ($dados['perfil'] ?? ''));

if (!validarEmail($email) || $codigo === '' || strlen($novaSenha) < 6) {
    responderJson(false, 'Dados inválidos para redefinir senha.', null, 422);
}

$pdo = pdo();
$sql = 'SELECT * FROM recuperacao_senha WHERE email = :email AND codigo = :codigo AND usado_em IS NULL AND expira_em >= NOW()';
if ($perfil !== '') {
    $sql .= ' AND perfil = :perfil';
}
$sql .= ' ORDER BY id DESC LIMIT 1';
$stmt = $pdo->prepare($sql);
$params = [':email' => $email, ':codigo' => $codigo];
if ($perfil !== '') {
    $params[':perfil'] = $perfil;
}
$stmt->execute($params);
$registro = $stmt->fetch();

if (!$registro) {
    responderJson(false, 'Código inválido, expirado ou já utilizado.', null, 404);
}

$tabela = tabelaPorPerfil((string) $registro['perfil']);
$upd = $pdo->prepare("UPDATE {$tabela} SET senha = :senha, password_updated_at = NOW() WHERE id = :id");
$upd->execute([
    ':senha' => password_hash($novaSenha, PASSWORD_BCRYPT),
    ':id' => (int) $registro['usuario_id'],
]);

$mark = $pdo->prepare('UPDATE recuperacao_senha SET usado_em = NOW() WHERE id = :id');
$mark->execute([':id' => (int) $registro['id']]);

registrarLog(['id' => (int) $registro['usuario_id'], 'nome' => $email, 'perfil' => $registro['perfil']], 'redefinir_senha', 'recuperacao_senha', (int) $registro['id']);

responderJson(true, 'Senha redefinida com sucesso.', ['email' => $email, 'perfil' => $registro['perfil']]);
