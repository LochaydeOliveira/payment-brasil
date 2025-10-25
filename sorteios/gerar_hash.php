<?php
$senha_desejada = 'BentoBBdaMame2024';
$hash_seguro = password_hash($senha_desejada, PASSWORD_DEFAULT);
echo "Hash para 'Monaliza': " . $hash_seguro;
// O hash para o usuário admin padrão (123456)
// echo "<br>Hash para 'admin' (123456): " . password_hash('123456', PASSWORD_DEFAULT);
?>