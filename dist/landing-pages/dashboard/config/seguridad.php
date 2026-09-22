<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['usuario_id'])) {
    header('Location: auth/sign-in.php');
    exit;
}

function esAdmin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'admin';
}

function exigirAdmin(): void
{
    if (!esAdmin()) {
        header('Location: index.php?permiso=denegado');
        exit;
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
