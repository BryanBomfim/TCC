<?php
http_response_code(500);
$codigo = 500;
$titulo = 'Não foi possível concluir a solicitação';
$mensagem = 'Ocorreu uma falha interna. Tente novamente e, se o problema continuar, procure o administrador.';
require __DIR__ . '/layout.php';
