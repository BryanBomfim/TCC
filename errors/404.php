<?php
http_response_code(404);
$codigo = 404;
$titulo = 'Página não encontrada';
$mensagem = 'O endereço informado não existe ou foi movido.';
require __DIR__ . '/layout.php';
