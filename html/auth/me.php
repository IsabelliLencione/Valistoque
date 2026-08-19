<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigirLogin();
responderJson(true, 'Sessão carregada com sucesso.', [
    'usuario' => $usuario,
    'csrf_token' => gerarCsrf(),
]);
