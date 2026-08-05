<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

validarMetodo('POST');

$dados = obterDadosRequisicao();
$email = strtolower(limpar($dados['email'] ?? ''));
$cpf = formatarCpf((string) ($dados['cpf'] ?? ''));
$perfil = perfilNormalizado((string) ($dados['perfil'] ?? ''));

if (!validarEmail($email) || !validarCPF($cpf)) {
    responderJson(false, 'Informe email e CPF válidos.', null, 422);
}

$pdo = pdo();
$alvos = $perfil ? [tabelaPorPerfil($perfil)] : ['adm', 'func'];
$encontrado = null;
$tabelaEncontrada = null;

foreach ($alvos as $tabela) {
    $stmt = $pdo->prepare("SELECT id, nome, email, cpf FROM {$tabela} WHERE email = :email AND cpf = :cpf AND ativo = 1 LIMIT 1");
    $stmt->execute([':email' => $email, ':cpf' => $cpf]);
    $usuario = $stmt->fetch();
    if ($usuario) {
        $encontrado = $usuario;
        $tabelaEncontrada = $tabela;
        break;
    }
}

if (!$encontrado || !$tabelaEncontrada) {
    responderJson(false, 'Nenhum usuário encontrado com os dados informados.', null, 404);
}

$codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiraEm = date('Y-m-d H:i:s', strtotime('+' . RECOVERY_CODE_TTL_MINUTES . ' minutes'));
$perfilFinal = perfilPorTabela($tabelaEncontrada);

$stmt = $pdo->prepare('INSERT INTO recuperacao_senha (perfil, usuario_id, email, codigo, expira_em, ip) VALUES (:perfil, :usuario_id, :email, :codigo, :expira_em, :ip)');
$stmt->execute([
    ':perfil' => $perfilFinal,
    ':usuario_id' => (int) $encontrado['id'],
    ':email' => $email,
    ':codigo' => $codigo,
    ':expira_em' => $expiraEm,
    ':ip' => ipCliente(),
]);

registrarLog(['id' => (int) $encontrado['id'], 'nome' => $encontrado['nome'], 'perfil' => $perfilFinal], 'solicitar_recuperacao', 'recuperacao_senha', (int) $pdo->lastInsertId(), ['email' => $email]);

responderJson(true, 'Código de recuperação gerado com sucesso.', [
    'email' => $email,
    'perfil' => $perfilFinal,
    'codigo' => $codigo,
    'expira_em' => $expiraEm,
    'observacao' => 'Ambiente local/demo: o código é retornado em JSON porque não há serviço de e-mail configurado.',
]);
