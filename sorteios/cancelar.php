<?php
// Define o fuso horário para PHP (necessário para logs e tempo consistente)
date_default_timezone_set('UTC'); 

// /sorteio/cancelar.php
// Roteiro para ser executado via Cron Job (HostGator/cPanel, etc.)

// Define que o script deve rodar sem limite de tempo
set_time_limit(0); 

// --- Configurações de Segurança ---
// ATENÇÃO: SUBSTITUA 'SUA_CHAVE_SECRETA' POR UMA CHAVE FORTE
$AUTH_KEY = 'SUA_CHAVE_SECRETA'; 

// Evita que o script seja executado via navegador (exceto com chave de autenticação)
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['run_auth_key']) || $_GET['run_auth_key'] !== $AUTH_KEY)) {
    http_response_code(403);
    die("Acesso Proibido. Use o Cron Job ou a chave de autenticação correta.");
}
// --- Fim da Segurança ---

// 1. Incluir dependências
require_once 'includes/db.php';
require_once 'includes/functions.php';

// --- INÍCIO DO LOG DE DIAGNÓSTICO ---
$log_path = __DIR__ . '/cron_status.log';
$log_message = date('Y-m-d H:i:s') . " - CRON EXECUTADO. Status: ";

// 2. Executa a função
try {
    $cancelados = cancelarReservasExpiradas($pdo);

    if ($cancelados > 0) {
        $log_message .= "SUCESSO. {$cancelados} reservas canceladas.\n";
        $output_message = "Sucesso: {$cancelados} reservas expiradas foram canceladas e seus números liberados.";
    } elseif ($cancelados === 0) {
        $log_message .= "OK. Nenhuma reserva expirada (expiracao < NOW()).\n";
        $output_message = "Nenhuma reserva expirada encontrada.";
    } else {
        $log_message .= "ERRO FATAL! Falha na função cancelarReservasExpiradas.\n";
        $output_message = "Erro: Falha no cancelamento automático. Verifique o log do servidor.";
    }
} catch (Exception $e) {
    $log_message .= "EXCEPTION: " . $e->getMessage() . "\n";
    $output_message = "Erro de Execução: " . $e->getMessage();
}

// Escreve a mensagem final no log
file_put_contents($log_path, $log_message, FILE_APPEND);
// --- FIM DO LOG DE DIAGNÓSTICO ---


// O Cron Job precisa de saída no console ou email para ser considerado completo pelo servidor
echo $output_message;
?>