<?php
// /sorteio/reservar.php

// 1. Incluir dependências
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redireciona em caso de acesso direto (não-POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

session_start();
$erros = [];


// 2. Coleta e Sanitização/Validação
$nome = sanitize_input($_POST['nome'] ?? '');
// O CPF é limpo no lado do servidor, mas a validação precisa do CPF limpo
$cpf_input = $_POST['cpf'] ?? ''; // Valor bruto do input
$cpf = sanitize_cpf($cpf_input); // Limpa pontuação
$telefone = sanitize_cpf($_POST['telefone'] ?? ''); // Limpa pontuação
$numeros_selecionados_str = $_POST['numeros_selecionados'] ?? '';

// Validação Básica
if (empty($nome) || strlen($nome) < 3) {
    $erros[] = "Nome inválido.";
}

// *** SEÇÃO DE VALIDAÇÃO DE CPF ***
if (strlen($cpf) != 11) { 
    $erros[] = "CPF inválido. Digite 11 dígitos.";
} elseif (!isValidCPF($cpf)) { 
    $erros[] = "CPF não existe. Por favor, verifique.";
}
// *** FIM DA SEÇÃO DE VALIDAÇÃO DE CPF ***

if (empty($telefone)) {
    $erros[] = "Telefone/WhatsApp obrigatório.";
}

// 3. Validação e Processamento dos Números (SEM LIMITE DE 10)
$numeros_selecionados = array_map('intval', array_filter(explode(',', $numeros_selecionados_str)));
$numeros_selecionados = array_filter($numeros_selecionados, function($n) {
    return $n >= 1 && $n <= 1000;
});

if (empty($numeros_selecionados)) {
    $erros[] = "Selecione pelo menos 1 número.";
}

// Cálculo do Total
$valor_por_numero = 20.00;
$total_reserva = count($numeros_selecionados) * $valor_por_numero;

// Em caso de erro de validação, redireciona de volta
if (!empty($erros)) {
    $_SESSION['erros'] = $erros; 
    header('Location: index.php');
    exit;
}

// 4. Início da Transação
$pdo->beginTransaction();

try {
    // 5.1. Cria/Busca Cliente
    $cliente_id = createOrUpdateCliente($pdo, $nome, $cpf, $telefone);

    // 5.2. Cria a Reserva (com expiração de 1h)
    $reserva_id = createReserva($pdo, $cliente_id, $total_reserva);

    // 5.3. Reserva os Números e Cria Vínculos
    $sucesso_reserva = reservarNumeros($pdo, $reserva_id, $numeros_selecionados);

    if (!$sucesso_reserva) {
        // Se a reserva dos números falhar (por indisponibilidade), cancela tudo
        $pdo->rollBack();
        $_SESSION['erros'] = ["Erro: Um ou mais números já foram reservados. Tente novamente."];
        header('Location: index.php');
        exit;
    }

    // 5.4. Sucesso / Commit
    $pdo->commit();

    // 5.5. Redirecionamento para a página de confirmação (o "POPUP" de PIX)
    header("Location: confirmar.php?reserva=" . $reserva_id);
    exit;

} catch (Exception $e) {
    // Em caso de qualquer erro no processo (exceção PDO ou lógica)
    $pdo->rollBack();
    error_log("Erro ao processar reserva: " . $e->getMessage()); 
    $_SESSION['erros'] = ["Ocorreu um erro interno. Por favor, tente novamente mais tarde."];
    header('Location: index.php');
    exit;
}