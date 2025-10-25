<?php
// /sorteio/admin/index.php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitize_input($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    // Consulta para buscar o usuário
    $stmt = $pdo->prepare("SELECT id, senha FROM admin WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($senha, $admin['senha'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = "Usuário ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login Admin</title>
    <link href="../assets/css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <h1>🔑 Login Administrativo</h1>
        <?php if ($erro): ?><div class="feedback-message error"><?= $erro ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuário" required>
            <input type="password" name="senha" placeholder="Senha" required autocomplete="current-password">
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
</body>
</html>