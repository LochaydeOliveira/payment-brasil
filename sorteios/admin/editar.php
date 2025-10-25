<?php
// /sorteio/admin/editar.php
require_once 'session_auth.php'; 

$reserva_id = intval($_GET['reserva'] ?? 0);
if ($reserva_id === 0) {
    header('Location: dashboard.php');
    exit;
}

$msg_sucesso = '';
$msg_erro = '';

// --- Processamento de Ações (Mudar Status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $nova_status = sanitize_input($_POST['status_novo']);
    $admin_note = sanitize_input($_POST['admin_note'] ?? '');
    
    if (in_array($nova_status, ['reservada', 'paga', 'cancelada'])) {
        try {
            $pdo->beginTransaction();

            // 1. Atualiza o status da reserva e a nota
            $stmt = $pdo->prepare("UPDATE reservas SET status = ?, admin_note = ? WHERE id = ?");
            $stmt->execute([$nova_status, $admin_note, $reserva_id]);

            // 2. Atualiza o status dos números
            $sql_num = "UPDATE numeros SET status = ?, reserva_id = ? WHERE reserva_id = ?";
            
            if ($nova_status === 'cancelada') {
                // Cancela: Libera o número e remove o vínculo
                $sql_num = "UPDATE numeros SET status = 'disponível', reserva_id = NULL WHERE reserva_id = ?";
                $stmt_num = $pdo->prepare($sql_num);
                $stmt_num->execute([$reserva_id]);
            } elseif ($nova_status === 'paga') {
                // Paga: Garante que os números estão marcados como pagos
                $sql_num = "UPDATE numeros SET status = 'pago' WHERE reserva_id = ?";
                $stmt_num = $pdo->prepare($sql_num);
                $stmt_num->execute([$reserva_id]);
            } else {
                // Reservada: Garante que os números estão marcados como reservados
                $sql_num = "UPDATE numeros SET status = 'reservado' WHERE reserva_id = ?";
                $stmt_num = $pdo->prepare($sql_num);
                $stmt_num->execute([$reserva_id]);
            }

            $pdo->commit();
            $msg_sucesso = "Status da Reserva #{$reserva_id} atualizado para **" . ucfirst($nova_status) . "** com sucesso.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_erro = "Erro ao atualizar status: " . $e->getMessage();
        }
    } else {
        $msg_erro = "Status inválido.";
    }
}

// --- Carregar Dados da Reserva ---
try {
    $sql_reserva = "
        SELECT 
            r.id, r.total, r.status, r.expiracao, r.criado_em, r.admin_note,
            c.nome, c.cpf, c.telefone
        FROM reservas r
        JOIN clientes c ON r.cliente_id = c.id
        WHERE r.id = ?
    ";
    $stmt_reserva = $pdo->prepare($sql_reserva);
    $stmt_reserva->execute([$reserva_id]);
    $reserva = $stmt_reserva->fetch();

    if (!$reserva) {
        $msg_erro = "Reserva não encontrada.";
        // Redireciona se não encontrar a reserva
        if (empty($msg_erro)) { 
            header('Location: dashboard.php');
            exit;
        }
    }

    $sql_numeros = "SELECT numero FROM numeros WHERE reserva_id = ? ORDER BY numero ASC";
    $stmt_numeros = $pdo->prepare($sql_numeros);
    $stmt_numeros->execute([$reserva_id]);
    $numeros = $stmt_numeros->fetchAll();

} catch (Exception $e) {
    $msg_erro = "Erro ao carregar dados da reserva: " . $e->getMessage();
    $reserva = null;
    $numeros = [];
}

// Se a reserva não for encontrada, exibe o erro e encerra
if (!$reserva) {
    // Código HTML para exibir o erro
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva #<?= $reserva_id ?> - Admin</title>
    <link href="../assets/css/admin-style.css" rel="stylesheet"> 
</head>
<body>
    <div class="container">
        <h1>📝 Editar Reserva #<?= $reserva_id ?></h1>
        <p><a href="dashboard.php" class="btn btn-sm btn-info">← Voltar ao Dashboard</a></p>

        <?php if ($msg_sucesso): ?><div class="feedback-message success"><?= $msg_sucesso ?></div><?php endif; ?>
        <?php if ($msg_erro): ?><div class="feedback-message error"><?= $msg_erro ?></div><?php endif; ?>

        <h2>Dados do Cliente</h2>
        <div class="details-box">
            <p><strong>Nome:</strong> <?= htmlspecialchars($reserva['nome']) ?></p>
            <p><strong>CPF:</strong> <?= htmlspecialchars($reserva['cpf']) ?></p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($reserva['telefone']) ?></p>
            <?php 
                $telefone_limpo = preg_replace('/[^0-9]/', '', $reserva['telefone']);
            ?>
            <p style="margin-top: 15px;"><a href="https://wa.me/55<?= $telefone_limpo ?>" target="_blank" class="btn btn-success">💬 Chamar Cliente no WhatsApp</a></p>
        </div>

        <h2>Detalhes da Reserva</h2>
        <div class="details-box">
            <p><strong>Status Atual:</strong> <span class="status-<?= $reserva['status'] ?>"><?= ucfirst($reserva['status']) ?></span></p>
            <p><strong>Valor Total:</strong> R$ <?= number_format($reserva['total'], 2, ',', '.') ?></p>
            <p><strong>Criada em:</strong> <?= date('d/m/Y H:i', strtotime($reserva['criado_em'])) ?></p>
            <p><strong>Expira em:</strong> <?= date('d/m/Y H:i', strtotime($reserva['expiracao'])) ?></p>
            <p><strong>Nota Admin:</strong> <?= nl2br(htmlspecialchars($reserva['admin_note'])) ?></p>
        </div>

        <div class="form-section">
            <h2>Números na Reserva (<?= count($numeros) ?>)</h2>
            <div class="numeros-lista">
                <?php foreach ($numeros as $num): ?>
                    <span><?= str_pad($num['numero'], 4, '0', STR_PAD_LEFT) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Atualizar Status e Nota</h2>
            <form method="POST">
                <label for="status_novo" style="display: block; font-weight: 600; margin-bottom: 5px;">Mudar Status para:</label>
                <select name="status_novo" id="status_novo" required>
                    <option value="reservada" <?= $reserva['status'] === 'reservada' ? 'selected' : '' ?>>Reservada</option>
                    <option value="paga" <?= $reserva['status'] === 'paga' ? 'selected' : '' ?>>Paga</option>
                    <option value="cancelada" <?= $reserva['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
                
                <label for="admin_note" style="display: block; font-weight: 600; margin-bottom: 5px; margin-top: 15px;">Nota do Administrador:</label>
                <textarea name="admin_note" id="admin_note" rows="4" placeholder="Adicione observações (Opcional)"><?= htmlspecialchars($reserva['admin_note']) ?></textarea>
                
                <button type="submit" name="action" value="update" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Atualizar Status</button>
            </form>
        </div>
    </div>
</body>
</html>