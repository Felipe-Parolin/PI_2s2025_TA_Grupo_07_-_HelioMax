<?php
// ========================================
// ARQUIVO: protectadmin.php  
// Protege páginas APENAS para administradores
// ========================================

if (!isset($_SESSION)) {
    session_start();
}

// Primeiro verifica se está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Verifica se é administrador (TIPO_USUARIO = 1)
if (!isset($_SESSION["usuario_tipo"]) || $_SESSION["usuario_tipo"] != 1) {
    // Usuário comum tentando acessar área de admin
    header("Location: dashUSER.php");
    exit;
}
?>