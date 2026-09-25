<?php
http_response_code(403);
$codigo = 403;
$titulo = 'Acesso não autorizado';
$mensagem = 'Seu perfil não possui permissão para acessar este recurso.';
require __DIR__ . '/layout.php';
