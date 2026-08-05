<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

$admin = exigirAdmin();
validarMetodo('POST');

$dados = obterDadosRequisicao();
$nome = limpar($dados['nome'] ?? '');
$email = strtolower(limpar($dados['email'] ?? ''));
$cpf = formatarCpf((string) ($dados['cpf'] ?? ''));
$senha = (string) ($dados['senha'] ?? '');
$confirmacao = (string) ($dados['confirmar_senha'] ?? $dados['confirmsenha'] ?? '');
$perfil = perfilNormalizado((string) ($dados['perfil'] ?? $dados['tipo_usuario'] ?? ''));

if ($nome === '' || !validarEmail($email) || !validarCPF($cpf) || strlen($senha) < 6 || $perfil === '') {
    responderJson(false, 'Dados inválidos para cadastro.', null, 422);
}

if ($confirmacao !== '' && $senha !== $confirmacao) {
    responderJson(false, 'As senhas não conferem.', null, 422);
}

$pdo = pdo();
if (emailOuCpfEmUso($pdo, $email, $cpf)) {
    responderJson(false, 'Email ou CPF já cadastrados.', null, 409);
}

$tabela = tabelaPorPerfil($perfil);
$stmt = $pdo->prepare("INSERT INTO {$tabela} (nome, email, cpf, senha, ativo, password_updated_at) VALUES (:nome, :email, :cpf, :senha, 1, NOW())");
$stmt->execute([
    ':nome' => $nome,
    ':email' => $email,
    ':cpf' => $cpf,
    ':senha' => password_hash($senha, PASSWORD_BCRYPT),
]);

$id = (int) $pdo->lastInsertId();
registrarLog($admin, 'cadastro_usuario', 'usuario', $id, ['perfil' => $perfil, 'email' => $email]);

responderJson(true, 'Usuário cadastrado com sucesso.', [
    'id' => $id,
    'nome' => $nome,
    'email' => $email,
    'cpf' => $cpf,
    'perfil' => $perfil,
]);
