<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/funcoes.php';

validarMetodo(['GET', 'POST']);
$usuario = usuarioAtual();
if ($usuario) {
    registrarLog($usuario, 'logout', 'autenticacao', $usuario['id'] ?? null);
}
encerrarSessaoUsuario();
responderJson(true, 'Logout realizado com sucesso.');
