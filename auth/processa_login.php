<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

validarMetodo('POST');

$dados = obterDadosRequisicao();
$email = strtolower(limpar($dados['email'] ?? ''));
$senha = (string) ($dados['senha'] ?? '');
$perfil = perfilNormalizado((string) ($dados['perfil'] ?? ''));

if (!validarEmail($email) || $senha === '' || $perfil === '') {
    responderJson(false, 'Informe email, senha e perfil válidos.', null, 422);
}

$pdo = pdo();
$ip = ipCliente();
$bloqueado = loginBloqueado($pdo, $email, $ip);
if ($bloqueado) {
    responderJson(false, 'Login temporariamente bloqueado por excesso de tentativas.', ['bloqueado_ate' => $bloqueado], 423);
}

$tabela = tabelaPorPerfil($perfil);
$usuario = buscarUsuarioPorEmail($pdo, $email, $tabela);

if (!$usuario || !(bool) $usuario['ativo'] || !password_verify($senha, (string) $usuario['senha'])) {
    registrarTentativaFalha($pdo, $email, $ip);
    responderJson(false, 'Credenciais inválidas para o perfil selecionado.', null, 401);
}

limparTentativasLogin($pdo, $email, $ip);
iniciarSessaoUsuario($usuario, $perfil, $tabela);
registrarLog($_SESSION['usuario'], 'login', 'autenticacao', (int) $usuario['id'], ['perfil' => $perfil]);

$destino = $perfil === 'administrador' ? 'interiorAdm.html#relatorio' : 'tela_alerta.html';

responderJson(true, 'Login realizado com sucesso.', [
    'usuario' => $_SESSION['usuario'],
    'csrf_token' => gerarCsrf(),
    'destino' => $destino,
]);
