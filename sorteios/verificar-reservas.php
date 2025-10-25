<?php
// /sorteio/verificar-reservas.php

// CRÍTICO: Define o fuso horário para UTC para sincronizar o PHP/MySQL na exibição (resolve erro de 3 horas)
date_default_timezone_set('UTC'); 

require_once 'includes/db.php';
require_once 'includes/functions.php';

$resultados = [];
$termo_busca = '';
$tipo_busca = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['busca'])) {
    $termo_busca = sanitize_input($_GET['busca']);
    $termo_limpo = sanitize_cpf($termo_busca);
    
    // Determina o tipo de busca
    if (is_numeric($termo_limpo) && strlen($termo_limpo) == 11) {
        $tipo_busca = 'cpf';
    } elseif (is_numeric($termo_limpo) && $termo_limpo >= 1 && $termo_limpo <= 1000) {
        $tipo_busca = 'numero';
    } else {
        $tipo_busca = 'invalida';
    }

    if ($tipo_busca === 'cpf') {
        // Busca por CPF: Retorna todas as reservas do cliente
        $sql = "
            SELECT 
                r.id AS reserva_id, r.total, r.status, r.criado_em, r.expiracao,
                c.nome, GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
            FROM reservas r
            JOIN clientes c ON r.cliente_id = c.id
            JOIN numeros n ON n.reserva_id = r.id
            WHERE c.cpf = ? AND r.status IN ('reservada', 'paga')
            GROUP BY r.id
            ORDER BY r.criado_em DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$termo_limpo]);
        $resultados = $stmt->fetchAll();

    } elseif ($tipo_busca === 'numero') {
        // Busca por Número: Retorna quem reservou/pagou o número
        $sql = "
            SELECT 
                c.nome, c.cpf, n.numero, n.status AS numero_status, 
                r.id AS reserva_id
            FROM numeros n
            JOIN reservas r ON n.reserva_id = r.id
            JOIN clientes c ON r.cliente_id = c.id
            WHERE n.numero = ? AND n.status IN ('reservado', 'pago')
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$termo_limpo]);
        $resultados = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Números - Sorteio</title>
    <link href="assets/css/style.css" rel="stylesheet"> 
    <style>
        /* Estilos específicos (Mantidos) */
        .container { max-width: 600px; margin: auto; padding: 20px; }
        .box { max-width: 320px;margin: 0 auto;background: #fff; padding: 15px; border-radius: var(--border-radius); box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 15px; }
        input[type="text"] { font-family: var(--poppins); }
        button { background-color: var(--primary-color); }
        .status-paga { color: var(--secondary-color); font-weight: bold; }
        .status-reservada { color: var(--warning-color); font-weight: bold; }
        .status-cancelada { color: gray; }
        .cta-comprar { margin-top: 30px; padding: 20px; background: #d4edda; border-radius: var(--border-radius); text-align: center; }
        /* ADIÇÃO: Estilo para acomodar badges */
        .numeros-lista { margin-top: 10px; display: flex; flex-wrap: wrap; justify-content: flex-start; }

        .bt-cta-rmkt {
            background: #00b050;
            color: #fff;
            padding: 5px 10px;
            font-weight: 600;
            text-decoration: none;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            width: 50%;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .bt-cta-rmkt {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="line-height: 30px;margin: 50px 0;font-size: 25px;">
            🔍 Consulta de Reservas
        </h1>
        <p style="text-align: center;">Busque por <strong>CPF (11 dígitos)</strong> para ver suas reservas ou por <strong>Número (1 a 1000)</strong>.</p>

        <div class="box">
            <form action="verificar-reservas.php" method="GET">
                <input type="text" name="busca" id="cpf-consulta-input" 
                       placeholder="Digite CPF ou Número" 
                       value="<?= htmlspecialchars($termo_busca) ?>" required maxlength="14">
                <button type="submit">Buscar</button>
            </form>
        </div>

        <?php if ($tipo_busca === 'invalida' && !empty($termo_busca)): ?>
            <div class="box" style="color:red;">O termo de busca digitado é inválido.</div>
        <?php elseif (!empty($termo_busca) && empty($resultados)): ?>
            <div class="box" style="color:red;">Nenhum resultado encontrado para "<?= htmlspecialchars($termo_busca) ?>".</div>
        <?php elseif (!empty($resultados)): ?>            
            <h2>Resultados da Busca</h2>

            <?php foreach ($resultados as $item): ?>
                <div class="box resultado-item" style="max-width: none;">
                    <?php if ($tipo_busca === 'cpf'): 
                        // CRÍTICO: Divide a string de números em um array para o loop
                        $numeros_reservados_array = explode(',', $item['numeros']);
                    ?>
                        <p><strong>Comprador:</strong> <?= htmlspecialchars($item['nome']) ?></p> 
                        <p><strong>Data da Compra:</strong> <?= date('d/m/Y H:i', strtotime($item['criado_em'])) ?></p>
                        
                        <hr style="border: 0; border-top: 1px solid #eee; margin: 10px 0;">

                        <p><strong>Reserva #<?= $item['reserva_id'] ?>:</strong> R$ <?= number_format($item['total'], 2, ',', '.') ?></p>
                        <p><strong>Status:</strong> <span class="status-<?= strtolower($item['status']) ?>"><?= ucfirst($item['status']) ?></span></p>
                        
                        <p style="margin-top: 15px;"><strong>Números Comprados (<?= count($numeros_reservados_array) ?>):</strong></p>
                        <div class="numeros-lista">
                            <?php foreach ($numeros_reservados_array as $numero): ?>
                                <span class="numero-conf"><?= $numero ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php elseif ($tipo_busca === 'numero'): ?>
                        <p><strong>Número Buscado:</strong> <?= $item['numero'] ?></p>
                        <p><strong>Comprador:</strong> <?= htmlspecialchars($item['nome']) ?></p>
                        <p><strong>CPF:</strong> (<?= substr($item['cpf'], 0, 3) ?>.***.***-**)</p>
                        <p><strong>Status do Número:</strong> <span class="status-<?= strtolower($item['numero_status']) ?>"><?= ucfirst($item['numero_status']) ?></span></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align:center; margin-top: 40px; margin-bottom: 30px;">
            <h3>Quer garantir mais números?</h3>
            <a href="index.php" class="cta-button bt-cta-rmkt">
                RESERVAR NOVOS NÚMEROS!
            </a>
        </div>
    </div>
    <script>
        const cpfInputConsulta = document.getElementById('cpf-consulta-input');

        // Formatação de CPF (UX)
        cpfInputConsulta.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            
            // Lógica de Formatação
            if (value.length > 3) {
                value = value.substring(0, 3) + '.' + value.substring(3);
            }
            if (value.length > 7) {
                value = value.substring(0, 7) + '.' + value.substring(7);
            }
            if (value.length > 11) {
                value = value.substring(0, 11) + '-' + value.substring(11);
            }
            
            this.value = value.substring(0, 14);
        });
    </script>
</body>
</html>