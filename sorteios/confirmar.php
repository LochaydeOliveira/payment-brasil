<?php
// /sorteio/confirmar.php
// Página de confirmação de reserva e instruções de pagamento PIX (FINAL)


date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/db.php';
require_once 'includes/functions.php';

// --- Configurações PIX Fixas ---
define('PIX_CHAVE', '85999671024'); // Sua Chave PIX
define('PIX_CONTA_NOME', 'Monaliza');
define('PIX_QR_CODE_IMG', './assets/images/qr-code-pix.jpeg'); 

// 1. Receber e validar o ID da Reserva
$reserva_id = $_GET['reserva'] ?? 0;
$reserva_id = intval($reserva_id);

if ($reserva_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // 2. Buscar Dados da Reserva e Cliente
    $sql = "
        SELECT 
            r.id AS reserva_id, r.total, r.status, r.expiracao, r.criado_em,
            c.nome AS cliente_nome, c.cpf AS cliente_cpf, c.telefone AS cliente_telefone
        FROM reservas r
        JOIN clientes c ON r.cliente_id = c.id
        WHERE r.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reserva_id]);
    $dados_reserva = $stmt->fetch();

    if (!$dados_reserva) {
        die("Reserva não encontrada."); 
    }
    
    // Se a reserva já foi paga ou cancelada, redireciona/informa
    if ($dados_reserva['status'] !== 'reservada') {
        $status_msg = ucfirst($dados_reserva['status']);
        echo "
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f8f9fa;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
    
            .popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.55);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                animation: fadeIn 0.3s ease-in-out;
            }
    
            .popup-box {
                background: #fff;
                border-radius: 12px;
                padding: 25px 35px;
                text-align: center;
                max-width: 400px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                animation: scaleIn 0.25s ease-in-out;
            }
    
            .popup-box h2 {
                color: #333;
                font-size: 22px;
                margin-bottom: 10px;
            }
    
            .popup-box p {
                color: #666;
                font-size: 15px;
                margin-bottom: 20px;
                line-height: 1.5;
            }
    
            .popup-box a {
                display: inline-block;
                background: #4db6ac;
                color: #fff;
                padding: 10px 18px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                transition: background 0.2s;
            }
    
            .popup-box a:hover {
                background: #3fa499;
            }
    
            @keyframes fadeIn {
                from { opacity: 0; } to { opacity: 1; }
            }
    
            @keyframes scaleIn {
                from { transform: scale(0.9); opacity: 0; } 
                to { transform: scale(1); opacity: 1; }
            }
        </style>
    
        <div class='popup-overlay'>
            <div class='popup-box'>
                <h2>Aviso <svg xmlns=http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-exclamation-triangle-fill' viewBox='0 0 16 16'>
                <path d='M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2'/>
                </svg>
                </h2>
                <p>Sua reserva está com o status <strong>{$status_msg}</strong>.<br>Consulte o andamento no link abaixo.</p>
                <a href='https://agencialed.com/sorteio-iphone-monaliza/verificar-reservas.php'>Verificar Reserva</a>
            </div>
        </div>
        <script>
            // Impede que a página continue executando o restante do código
            document.addEventListener('DOMContentLoaded', () => {
                document.body.style.overflow = 'hidden';
            });
        </script>
        ";
        exit;
    }
    
    

    // 3. Buscar os Números Reservados (CORRIGIDO: Removido JOIN reserva_numeros)
    $sql_numeros = "
        SELECT numero
        FROM numeros
        WHERE reserva_id = ?
        ORDER BY numero ASC
    ";
    $stmt_numeros = $pdo->prepare($sql_numeros);
    $stmt_numeros->execute([$reserva_id]);
    $numeros_reservados = $stmt_numeros->fetchAll(PDO::FETCH_COLUMN);

    // 4. Calcular o tempo restante (para o Countdown)
    $expiracao_dt = new DateTime($dados_reserva['expiracao'], new DateTimeZone('America/Sao_Paulo'));
    $agora_dt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));    
    
    $tempo_restante_segundos = max(0, $expiracao_dt->getTimestamp() - $agora_dt->getTimestamp());
    
    // Para exibição da hora de expiração no fuso horário do Brasil (UTC-3)
    $expiracao_dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));


} catch (Exception $e) {
    die("Erro ao carregar dados da reserva: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIX - Reserva #<?= $reserva_id ?></title>
    <link href="./assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✅</text></svg>">
</head>
<body>

    <div class="popup-card">
        <h1 style="display: flex;justify-content: center;align-items: center;gap: 3px;color: var(--secondary-color);">
            Reserva Confirmada
            <svg xmlns="http://www.w3.org/2000/svg" width="45" height="45" fill="currentColor" class="bi bi-check-all" viewBox="0 0 16 16">
                <path d="M8.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L2.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093L8.95 4.992zm-.92 5.14.92.92a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 1 0-1.091-1.028L9.477 9.417l-.485-.486z"/>
            </svg>
        </h1>
        <p>Sua reserva está válida! <br>Prossiga com o pagamento PIX nas próximas <strong>1 HORA</strong>.</p>

        <div class="countdown" id="countdown-timer"></div>
        <p style="font-size: 0.9em;display: none!important;">A reserva expira em: <strong><?= $expiracao_dt->format('d/m/Y H:i:s') ?></strong></p> 

        <div class="details-box">
            <p><strong>Reserva:</strong> #<?= $dados_reserva['reserva_id'] ?></p>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($dados_reserva['cliente_nome']) ?></p>
            <p><span class="value-label">Valor Total: R$ <?= number_format($dados_reserva['total'], 2, ',', '.') ?></span></p>
        </div>

        <h2 style="display: flex;align-items: center;gap: 5px;justify-content: center;color: #4db6ac!important;">
        <img src="./assets/images/icone-pix.png" alt="Icone do Pix" width="45" height="45"> Pagamento via PIX
        </h2>

        <div style="color: #00b050;font-size: 0.9em;background: #edfff4ad;border-radius: 10px;padding: 15px 35px 10px;line-height: 20px;border: solid 1px #00b050;"">
            <strong>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                </svg>
                INSTRUÇÕES DE PAGAMENTO
            </strong><br>O processo não é automático. Ao fazer a transferência, digite o valor da sua reserva.<br><br>
            <i><strong>Após finalizar a transferência, envie o comprovante e informe o número da reserva pelo WhatsApp.</Strong></i>
        </div>        

        <?php if (file_exists(PIX_QR_CODE_IMG)): ?>
            <p><strong>QR Code:</strong></p>
            <img src="<?= PIX_QR_CODE_IMG ?>" alt="QR Code PIX Fixo" style="max-width: 200px; display: block; margin: 10px auto;">
        <?php else: ?>
            <p style="color: red;">* Alerta: Imagem do QR Code não encontrada. Use a chave PIX. *</p>
        <?php endif; ?>
        
        <p><strong>Chave PIX:</strong></p>
        <div class="chave-pix-container" onclick="copyPixKey()">
            <code id="pix-chave"><?= PIX_CHAVE ?></code>
            <button type="button" class="btn-copy">COPIAR CHAVE 
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-copy" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
                </svg>
            </button>
        </div>

        <p><strong>Nome da Conta:</strong> <?= PIX_CONTA_NOME ?></p>
        <a style="font-size: 14px;" href="https://wa.me/5585999671024">ENVIAR COMRPROVANTE</a>
        
        <div class="details-box" style="margin-top: 25px;">
            <p><strong>Seus Números Reservados (<?= count($numeros_reservados) ?>):</strong></p>
            <div class="numeros-lista">
                <?php foreach ($numeros_reservados as $numero): ?>
                    <span class="numero-conf"><?= $numero ?></span>
                <?php endforeach; ?>
            </div>
            <p style="margin-top: 15px; font-size: 0.9em;">Seu pagamento será confirmado em instantes pelo administrador.</p>
        </div>
        <a href="index.php">Voltar ao início</a>
    </div>

<script>
    // Lógica do Countdown (JavaScript)
    let totalSeconds = <?= $tempo_restante_segundos ?>;
    const countdownEl = document.getElementById('countdown-timer');

    function updateCountdown() {
        if (totalSeconds <= 0) {
            countdownEl.innerHTML = "EXPIRADA! Verifique o status na consulta.";
            return;
        }

        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        const timeString = 
            String(hours).padStart(2, '0') + ":" +
            String(minutes).padStart(2, '0') + ":" +
            String(seconds).padStart(2, '0') + "";
        
        countdownEl.innerHTML = timeString;

        totalSeconds--;
        setTimeout(updateCountdown, 1000);
    }

    function copyPixKey() {
        const key = document.getElementById('pix-chave').innerText;
        navigator.clipboard.writeText(key)
            .then(() => {
                alert('Chave PIX copiada para a área de transferência!');
            })
            .catch(err => {
                console.error('Erro ao copiar: ', err);
                alert('Erro ao copiar. Tente selecionar e copiar manualmente.');
            });
    }

    updateCountdown();
</script>

<script>
    const reservaId = <?= $reserva_id ?>;
    let ultimoStatus = "<?= $dados_reserva['status'] ?>";
    let countdownAtivo = true;

    // 🔁 Verifica o status a cada 5 segundos
    async function verificarStatus() {
        try {
            const response = await fetch(`includes/get_status.php?reserva=${reservaId}&t=${Date.now()}`);
            const data = await response.json();

            if (data.status && data.status !== ultimoStatus) {
                ultimoStatus = data.status;
                countdownAtivo = false;

                // Recarrega a página para que o PHP mostre o popup original
                window.location.reload();
            }
        } catch (e) {
            console.error("Erro ao verificar status:", e);
        }
    }

    // Intervalo para verificação
    setInterval(verificarStatus, 5000);

    // Exemplo de controle de contagem regressiva
    let tempoRestante = 60;
    const elContador = document.getElementById("contador");

    const contador = setInterval(() => {
        if (!countdownAtivo) {
            clearInterval(contador);
            return;
        }

        tempoRestante--;
        if (elContador) elContador.textContent = tempoRestante;

        if (tempoRestante <= 0) {
            clearInterval(contador);
            window.location.href = "index.php";
        }
    }, 1000);
</script>



<style>
    .popup-status {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex; justify-content: center; align-items: center;
        z-index: 9999;
    }
    .popup-content {
        background: white;
        padding: 25px 40px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 4px 25px rgba(0,0,0,0.2);
        animation: fadeIn 0.3s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>


</body>
</html>