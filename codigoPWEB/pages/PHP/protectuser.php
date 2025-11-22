<?php
// ========================================
// ARQUIVO: protectuser.php  
// Protege páginas APENAS para usuários comuns (tipo 0)
// Admins são redirecionados para dashADM.php
// ========================================

if (!isset($_SESSION)) {
    session_start();
}

// Primeiro verifica se está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Verifica se é usuário comum (TIPO_USUARIO = 0)
// Se for admin (tipo 1), redireciona para área de admin
if (isset($_SESSION["usuario_tipo"]) && $_SESSION["usuario_tipo"] == 1) {
    header("Location: dashADM.php");
    exit;
}
?>