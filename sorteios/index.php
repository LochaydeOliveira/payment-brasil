<?php
// /sorteio/index.php
session_start();
require_once 'includes/db.php';

$erros = $_SESSION['erros'] ?? [];
unset($_SESSION['erros']);

define('VALOR_PONTO', 20.00);
define('QTD_NUMEROS', 1000); 


// --- NOVO: Contador de Números Comprados ---
try {
    // Busca o total de números PAGOS (status = 'pago')
    $stmt_comprados = $pdo->query("SELECT COUNT(id) AS count FROM numeros WHERE status = 'pago'");
    $comprados_count = $stmt_comprados->fetchColumn();
    
    // Busca o total de números RESERVADOS (status = 'reservado')
    $stmt_reservados = $pdo->query("SELECT COUNT(id) AS count FROM numeros WHERE status = 'reservado'");
    $reservados_count = $stmt_reservados->fetchColumn();
    
} catch (Exception $e) {
    $comprados_count = 0;
    $reservados_count = 0;
    $erros[] = "Erro ao carregar contador de vendas.";
}
// --- FIM DO CONTADOR ---



// Busca números ocupados no DB
try {
    $stmt = $pdo->query("SELECT numero, status FROM numeros WHERE status != 'disponível'");
    $numeros_ocupados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [numero => status]
} catch (Exception $e) {
    $numeros_ocupados = [];
    $erros[] = "Erro ao carregar status dos números: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorteios Payment Brasil</title>
    <link href="assets/css/style.css" rel="stylesheet"> 
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">
</head>
<body>

    <div class="container">        
        <div class="banner">
            <a href="https://www.instagram.com/monaliza_lima/" target="_blank"></a>   
        </div>     
        <div class="">
            <p class="price">Número: R$<?= number_format(VALOR_PONTO, 2, ',', '.') ?> | Total de Números: <?= QTD_NUMEROS ?></p>

            <p style="margin: -18px 0 10px 0" class="counter">
                Números Comprados: <strong><?= $comprados_count ?></strong>/<?= QTD_NUMEROS ?>
            </p>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="error-message">
                <strong>Ocorreu um erro:</strong>
                <ul><?php foreach ($erros as $erro): ?><li><?= htmlspecialchars($erro) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form id="reserva-form" action="reservar.php" method="POST">
            
            <div class="grid-container">
                <h2>1. Escolha Seus Números</h2>
                <p>Selecione quantos números desejar (sem limite). Os números verdes estão disponíveis.</p>
                
                <div id="numero-grid" class="numero-grid">
                    <?php for ($i = 1; $i <= QTD_NUMEROS; $i++):
                        $status_db = $numeros_ocupados[$i] ?? 'disponível';
                        $status_class = 'status-' . $status_db;
                        $is_ocupado = $status_db !== 'disponível';
                    ?>
                        <div class="numero-item <?= $status_class ?>" 
                            data-numero="<?= $i ?>" 
                            data-status="<?= $is_ocupado ? 'ocupado' : 'disponivel' ?>">
                            
                            <?= $i ?> 
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-section">
                <h2 style="line-height: 25px;">2. Confirme o Valor e Seus Dados</h2>

                <div class="total-box">
                    Valor a Pagar: R$ <span id="valor-total">0,00</span>
                    <small>Quant. Selecionada: <span id="qtd-selecionada">0</span> números</small>
                </div>
                
                <input type="text" name="nome" placeholder="Seu Nome Completo" required>
                <input type="text" name="cpf" id="cpf-input" placeholder="CPF (Somente números)" required maxlength="14">
                <input
                type="tel"
                name="telefone"
                id="whatsapp-input"
                placeholder="Seu WhatsApp (apenas números)"
                maxlength="11"
                pattern="[0-9]{11}"
                inputmode="numeric"
                required>

                
                <input type="hidden" name="numeros_selecionados" id="numeros_selecionados_input" value="">

                <button type="submit" id="btn-reservar" disabled>
                    RESERVAR NÚMEROS
                </button>
            </div>

            <p style="text-align:center; margin-top: 20px;">
                <a href="verificar-reservas.php" style="color: var(--primary-color);">Consultar meus números comprados (CPF)</a>
            </p>
        </form>
    </div>

<script>
    const valorPonto = <?= VALOR_PONTO ?>;
    const grid = document.getElementById('numero-grid');
    const totalDisplay = document.getElementById('valor-total');
    const qtdDisplay = document.getElementById('qtd-selecionada');
    const reservarBtn = document.getElementById('btn-reservar');
    const numerosInput = document.getElementById('numeros_selecionados_input');
    const cpfInput = document.getElementById('cpf-input');
    
    let numerosSelecionados = [];

    // 1. Lógica de Seleção
    function updateDisplay() {
        const total = numerosSelecionados.length * valorPonto;
        totalDisplay.textContent = total.toFixed(2).replace('.', ',');
        qtdDisplay.textContent = numerosSelecionados.length;
        
        reservarBtn.disabled = numerosSelecionados.length === 0;

        // Popula o campo hidden para enviar os dados
        numerosInput.value = numerosSelecionados.join(',');
    }

    // 1. Lógica de Seleção
    function updateDisplay() {
        const total = numerosSelecionados.length * valorPonto;
        totalDisplay.textContent = total.toFixed(2).replace('.', ',');
        qtdDisplay.textContent = numerosSelecionados.length;
        
        reservarBtn.disabled = numerosSelecionados.length === 0;

        // Popula o campo hidden para enviar os dados
        numerosInput.value = numerosSelecionados.join(',');
    }

    grid.addEventListener('click', function(event) {
        // Tenta encontrar o item clicado, subindo na hierarquia (closest)
        let item = event.target.closest('.numero-item');
        
        // Verificação de segurança: Se não encontrou o item ou se ele é nulo, sai.
        if (!item) {
            console.log("Clique fora de um item de número.");
            return; 
        }

        // Ponto Crítico: Tenta ler o atributo data-numero
        const numero = parseInt(item.dataset.numero);
        const status = item.dataset.status;

        // Verifique se a leitura do número falhou (deve ser um número inteiro)
        if (isNaN(numero)) {
            console.error("ERRO DE DADOS: data-numero inválido ou não encontrado.");
            return;
        }

        if (status !== 'disponivel') {
            return; 
        }

        const isSelecionado = item.classList.contains('status-selecionado');
        
        if (isSelecionado) {
            // Desselecionar
            item.classList.remove('status-selecionado');
            numerosSelecionados = numerosSelecionados.filter(n => n !== numero);
        } else {
            // Selecionar
            item.classList.add('status-selecionado');
            numerosSelecionados.push(numero);
        }
        
        numerosSelecionados.sort((a, b) => a - b);
        updateDisplay();
    });

    // 2. Formatação de CPF (UX)
    cpfInput.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, ''); 
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

    updateDisplay(); 
</script>

<script>
    const whatsappInput = document.getElementById('whatsapp-input');

    whatsappInput.addEventListener('input', function (e) {
        // Remove tudo que não for número
        this.value = this.value.replace(/\D/g, '');

        // Limita a 11 dígitos
        if (this.value.length > 11) {
            this.value = this.value.slice(0, 11);
        }
    });

    // Validação extra ao enviar
    document.querySelector('form').addEventListener('submit', function (e) {
        const tel = whatsappInput.value;
        if (tel.length !== 11) {
            e.preventDefault();
            alert('Por favor, insira um número de WhatsApp válido com 11 dígitos (ex: DDD + número).');
            whatsappInput.focus();
        }
    });
</script>

</body>
</html>