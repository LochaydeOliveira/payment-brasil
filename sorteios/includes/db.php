<?php
// /sorteio/includes/db.php
// Conexão PDO com o MySQL

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'paymen58_sorteio_iphone'); // Seu nome do DB
define('DB_USER', 'paymen58');
define('DB_PASS', 'u4q7+B6ly)obP_gxN9sNe');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // CRÍTICO: Força o MySQL a usar UTC para salvar e retornar a hora consistentemente
    // $pdo->exec("SET time_zone = '+00:00'"); 

} catch (PDOException $e) {
    die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
}