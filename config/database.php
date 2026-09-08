<?php
/**
 * config/database.php
 * -----------------------------------------------------------------
 * Configuração e abertura da conexão com o banco de dados MySQL,
 * utilizando PDO. Ajuste as constantes abaixo conforme o seu
 * ambiente (XAMPP/WAMP/phpMyAdmin).
 * -----------------------------------------------------------------
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'smartcontrol');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
