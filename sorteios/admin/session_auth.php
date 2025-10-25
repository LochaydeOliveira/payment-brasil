<?php
// /sorteio/admin/session_auth.php

session_start();
// Caminho ajustado para o nível correto (../includes/)
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/functions.php';

// Redireciona para a tela de login se não estiver autenticado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// O admin_id pode ser usado para buscar dados de perfil se necessário
$admin_id = $_SESSION['admin_id'] ?? null;
?>