<?php
// ========================================
// ARQUIVO: protect.php
// Protege páginas para usuários logados (qualquer tipo)
// ========================================

if (!isset($_SESSION)) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}
?>