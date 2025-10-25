<?php
// /sorteio/includes/functions.php

/**
 * Funções de Segurança e Sanitização
 */
function sanitize_input($data) {
    return htmlspecialchars(trim(stripslashes($data)));
}

function sanitize_cpf($cpf) {
    return preg_replace('/[^0-9]/', '', $cpf);
}

/**
 * Adicionar função para validar matematicamente o CPF (Dígitos Verificadores)
 */
function isValidCPF($cpf) {
    // Remove caracteres não-numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se o número de dígitos é 11
    if (strlen($cpf) != 11) {
        return false;
    }

    // Verifica se todos os dígitos são iguais (ex: 111.111.111-11) - são inválidos por regra
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // Valida o primeiro dígito verificador
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

/**
 * Funções de Cliente e Reserva
 */
function createOrUpdateCliente(PDO $pdo, $nome, $cpf, $telefone) {
    // Tenta buscar cliente pelo CPF
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        // Atualiza os dados do cliente existente
        $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, telefone = ? WHERE id = ?");
        $stmt->execute([$nome, $telefone, $cliente['id']]);
        return $cliente['id'];
    } else {
        // Cria um novo cliente
        $stmt = $pdo->prepare("INSERT INTO clientes (nome, cpf, telefone) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $cpf, $telefone]);
        return $pdo->lastInsertId();
    }
}

function createReserva(PDO $pdo, $cliente_id, $total) {
    // Registra data/hora atual e expiração de 1h
    $agora = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', strtotime('+1 hours'));

    $sql = "INSERT INTO reservas (cliente_id, total, status, criado_em, expiracao)
            VALUES (?, ?, 'reservada', ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id, $total, $agora, $expira]);

    return $pdo->lastInsertId();
}


function reservarNumeros(PDO $pdo, $reserva_id, $numeros_escolhidos) {
    if (empty($numeros_escolhidos)) return false;

    $placeholders = implode(',', array_fill(0, count($numeros_escolhidos), '?'));
    
    // 1. VERIFICA DISPONIBILIDADE
    $sql_check = "SELECT COUNT(id) AS count FROM numeros WHERE numero IN ({$placeholders}) AND status = 'disponível'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute($numeros_escolhidos);
    $result_check = $stmt_check->fetch();

    if ($result_check['count'] != count($numeros_escolhidos)) {
        return false; // Falha na reserva (algum número já foi pego)
    }

    // 2. ATUALIZAÇÃO: Marca todos como 'reservado' e vincula à reserva_id
    $params = array_merge(['reservado', $reserva_id], $numeros_escolhidos);
    $sql_update = "UPDATE numeros SET status = ?, reserva_id = ? WHERE numero IN ({$placeholders})";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute($params);
    
    return true;
}

/**
 * Função CRON JOB (Limpeza de Reservas Expiradas)
 */
function cancelarReservasExpiradas(PDO $pdo) {
    $sql = "SELECT id FROM reservas WHERE status = 'reservada' AND expiracao < NOW()";
    $stmt = $pdo->query($sql);
    $reservas_expiradas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($reservas_expiradas)) return 0;

    $reservas_str = implode(',', $reservas_expiradas);

    try {
        $pdo->beginTransaction();

        // 1. Libera os números
        $sql_num = "UPDATE numeros SET status = 'disponível', reserva_id = NULL WHERE reserva_id IN ({$reservas_str})";
        $pdo->exec($sql_num);

        // 2. Atualiza o status da reserva
        $sql_reserva = "UPDATE reservas SET status = 'cancelada', admin_note = 'Cancelado automaticamente por expiração.' WHERE id IN ({$reservas_str})";
        $pdo->exec($sql_reserva);

        $pdo->commit();
        return count($reservas_expiradas);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro no Cron Job de Cancelamento: " . $e->getMessage());
        return -1;
    }
}