<?php
// /sorteio/admin/cadastrar-manual.php
require_once 'session_auth.php'; 

$msg_sucesso = '';
$msg_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitização e Coleta
    $nome = sanitize_input($_POST['nome'] ?? '');
    $cpf = sanitize_cpf($_POST['cpf'] ?? '');
    $telefone = sanitize_cpf($_POST['telefone'] ?? '');
    $numeros_input = sanitize_input($_POST['numeros'] ?? '');
    $numeros_selecionados = array_map('intval', array_filter(explode(',', $numeros_input)));
    $admin_note = sanitize_input($_POST['admin_note'] ?? 'Cadastro manual pelo Admin.');

    $valor_ponto = 20.00; // Valor fixo
    $total_reserva = count($numeros_selecionados) * $valor_ponto;

    // 2. Validação Básica
    if (strlen($cpf) != 11 || empty($nome) || empty($numeros_selecionados) || $total_reserva === 0) {
        $msg_erro = "Dados inválidos. Verifique Nome, CPF (11 dígitos) e se há números selecionados.";
    } else {
        try {
            $pdo->beginTransaction();

            // 3. Cria/Busca Cliente
            $cliente_id = createOrUpdateCliente($pdo, $nome, $cpf, $telefone);
            
            // 4. Cria a Reserva (Status: Paga, pois é um cadastro manual)
            $reserva_id = createReserva($pdo, $cliente_id, $total_reserva, null, 'paga'); // Novo parâmetro 'status'='paga'

            // 5. Reserva os Números e Cria Vínculos (Status: Pago)
            $sucesso_reserva = reservarNumeros($pdo, $reserva_id, $numeros_selecionados, 'pago'); 

            if (!$sucesso_reserva) {
                $pdo->rollBack();
                $msg_erro = "Erro: Um ou mais números já estavam ocupados.";
            } else {
                // 6. Adiciona Nota Administrativa
                $stmt = $pdo->prepare("UPDATE reservas SET admin_note = ? WHERE id = ?");
                $stmt->execute([$admin_note, $reserva_id]);

                $pdo->commit();
                $msg_sucesso = "Reserva #{$reserva_id} cadastrada como PAGA com sucesso para " . htmlspecialchars($nome) . ". Total: R$" . number_format($total_reserva, 2, ',', '.') . ".";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro no Cadastro Manual: " . $e->getMessage());
            $msg_erro = "Erro interno ao cadastrar: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Manual - Admin</title>
    <link href="../assets/css/admin-style.css" rel="stylesheet"> 
</head>
<body>
    <div class="container">
        <h1>➕ Cadastro Manual de Venda (PIX Pago)</h1>
        <p>Use esta função para registrar vendas que já foram pagas fora do sistema principal.</p>
        <p><a href="dashboard.php" class="btn btn-sm btn-info">← Voltar ao Dashboard</a></p>
        
        <?php if ($msg_sucesso): ?><div class="feedback-message success"><?= $msg_sucesso ?></div><?php endif; ?>
        <?php if ($msg_erro): ?><div class="feedback-message error"><?= $msg_erro ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-section">
                <h2>Dados do Cliente</h2>
                <input type="text" name="nome" placeholder="Nome Completo" required>
                <input type="text" name="cpf" placeholder="CPF (Apenas 11 dígitos)" required maxlength="11">
                <input type="text" name="telefone" placeholder="Telefone / WhatsApp" required>
            </div>

            <div class="form-section">
                <h2>Números Comprados</h2>
                <p style="margin-bottom: 10px; font-size: 0.9em; color: #6c757d;">Digite os números separados por VÍRGULA (Ex: 5, 10, 25)</p>
                <textarea name="numeros" rows="4" placeholder="Ex: 5, 10, 25, 100" required></textarea>
            </div>
            
            <div class="form-section">
                <h2>Observações</h2>
                <textarea name="admin_note" rows="3" placeholder="Nota de Administração (Opcional)"></textarea>
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; padding: 12px; font-size: 1.1em; margin-top: 10px;">
                ✅ Cadastrar Venda como PAGA
            </button>
        </form>
    </div>
</body>
</html>