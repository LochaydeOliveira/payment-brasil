<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

// Aceita tanto ?reserva= quanto ?id=
$reserva_id = isset($_GET['reserva']) ? intval($_GET['reserva']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($reserva_id <= 0) {
    echo json_encode([
        'status' => false,
        'erro' => 'ID inválido',
        'recebido' => $_GET
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT status FROM reservas WHERE id = ?");
    $stmt->execute([$reserva_id]);
    $status = $stmt->fetchColumn();

    if ($status !== false) {
        echo json_encode(['status' => $status]);
    } else {
        echo json_encode([
            'status' => false,
            'erro' => 'Reserva não encontrada',
            'id' => $reserva_id
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'erro' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
