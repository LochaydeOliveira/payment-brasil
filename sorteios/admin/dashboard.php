<?php
require_once 'session_auth.php';
require_once __DIR__ . '/../includes/db.php';


$msg_sucesso = '';
$msg_erro = '';

// --- Processamento de Ações (Marcar Pago / Cancelar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reserva_id'])) {
    $reserva_id = intval($_POST['reserva_id']);
    $nova_status = '';

    if ($_POST['action'] === 'marcar_pago') $nova_status = 'paga';
    elseif ($_POST['action'] === 'cancelar') $nova_status = 'cancelada';

    if ($nova_status) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE reservas SET status = ? WHERE id = ?");
            $stmt->execute([$nova_status, $reserva_id]);

            if ($nova_status === 'cancelada') {
                $sql_num = "UPDATE numeros SET status = 'disponível', reserva_id = NULL WHERE reserva_id = ?";
                $msg_sucesso = "Reserva #{$reserva_id} cancelada e números liberados.";
            } else {
                $sql_num = "UPDATE numeros SET status = 'pago' WHERE reserva_id = ?";
                $msg_sucesso = "Reserva #{$reserva_id} marcada como PAGA.";
            }

            $pdo->prepare($sql_num)->execute([$reserva_id]);
            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_erro = "Erro ao processar: " . $e->getMessage();
        }
    }
}

// --- Filtros de busca ---
$status_filtro = $_GET['status'] ?? '';
$busca = trim($_GET['busca'] ?? '');

try {
    $sql = "
        SELECT 
            r.id, r.total, r.status, r.expiracao, r.criado_em,
            c.nome, c.cpf, c.telefone
        FROM reservas r
        JOIN clientes c ON r.cliente_id = c.id
        WHERE 1=1
    ";

    $params = [];

    if ($status_filtro && in_array($status_filtro, ['reservada', 'paga', 'cancelada'])) {
        $sql .= " AND r.status = ?";
        $params[] = $status_filtro;
    }

    if ($busca !== '') {
        $sql .= " AND (c.nome LIKE ? OR c.cpf LIKE ? OR c.telefone LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }

    $sql .= " ORDER BY r.criado_em DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservas = $stmt->fetchAll();

} catch (Exception $e) {
    $msg_erro = "Erro ao carregar reservas: " . $e->getMessage();
    $reservas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link href="../assets/css/admin-style.css" rel="stylesheet"> 
    <style>
        .filtro-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        .filtro-form select,
        .filtro-form input[type="text"] {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .filtro-form button {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .filtro-form button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <h1>Painel Administrativo</h1>
            <div>
                <a href="cadastrar-manual.php" class="btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                    </svg> Cadastro Manual
                </a>
                <a href="logout.php" class="btn btn-danger">Sair</a>
            </div>
        </div>

        <?php if ($msg_sucesso): ?><div class="feedback-message success"><?= $msg_sucesso ?></div><?php endif; ?>
        <?php if ($msg_erro): ?><div class="feedback-message error"><?= $msg_erro ?></div><?php endif; ?>

        <h2>Lista de Reservas (<?= count($reservas) ?>)</h2>

        <!-- 🔎 Filtros -->
        <form method="GET" class="filtro-form">
            <select name="status">
                <option value="">-- Todos os Status --</option>
                <option value="reservada" <?= $status_filtro==='reservada'?'selected':'' ?>>Reservadas</option>
                <option value="paga" <?= $status_filtro==='paga'?'selected':'' ?>>Pagas</option>
                <option value="cancelada" <?= $status_filtro==='cancelada'?'selected':'' ?>>Canceladas</option>
            </select>
            <input type="text" name="busca" placeholder="Buscar por nome, CPF ou telefone" value="<?= htmlspecialchars($busca) ?>">
            <button type="submit">Filtrar</button>
        </form>

        <div class="table-responsive">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>CPF</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Expira em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservas)): ?>
                            <tr><td colspan="7" style="text-align:center;">Nenhuma reserva encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reservas as $res): ?>
                            <tr>
                                <td><?= $res['id'] ?></td>
                                <td><?= htmlspecialchars($res['nome']) ?></td>
                                <td><?= htmlspecialchars($res['cpf']) ?></td>
                                <td>R$ <?= number_format($res['total'], 2, ',', '.') ?></td>
                                <td class="status-<?= $res['status'] ?>"><?= ucfirst($res['status']) ?></td>
                                <td><?= date('d/m H:i', strtotime($res['expiracao'])) ?></td>
                                <td class="actions">
                                    <a href="editar.php?reserva=<?= $res['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                                    <?php if ($res['status'] === 'reservada'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="reserva_id" value="<?= $res['id'] ?>">
                                            <button type="submit" name="action" value="marcar_pago" class="btn btn-sm btn-success">Pago</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="reserva_id" value="<?= $res['id'] ?>">
                                            <button type="submit" name="action" value="cancelar" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja CANCELAR a Reserva #<?= $res['id'] ?>?');">Cancelar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php $telefone_limpo = preg_replace('/[^0-9]/', '', $res['telefone']); ?>
                                    <a href="https://wa.me/55<?= $telefone_limpo ?>" target="_blank" class="btn btn-sm btn-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16">
                                        <path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
