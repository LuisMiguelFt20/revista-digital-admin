<?php
declare(strict_types=1);

require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';

function contar(PDO $conexion, string $tabla): int
{
    return (int) $conexion->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
}

function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

$datos = [
    'usuarios' => contar($conexion, 'usuarios'),
    'autores' => contar($conexion, 'autores'),
    'reportajes' => contar($conexion, 'reportajes'),
    'fotos' => contar($conexion, 'reportajes_fotos'),
    'noticias' => contar($conexion, 'noticias'),
    'boletines' => contar($conexion, 'boletines'),
    'podcasts' => contar($conexion, 'podcasts'),
    'videos' => contar($conexion, 'videos'),
];

$nombreUsuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$rolUsuario = $_SESSION['usuario_rol'] ?? 'redactor';
?>
<!doctype html>
<html lang="es" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administrativo | Revista Digital</title>
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/core/libs.min.css">
    <link rel="stylesheet" href="../assets/css/hope-ui.min.css?v=4.0.0">
    <link rel="stylesheet" href="../assets/css/custom.min.css?v=4.0.0">
    <link rel="stylesheet" href="../assets/css/dark.min.css">
    <style>
        .hero-revista { background: linear-gradient(120deg, #173b8f, #3a7afe); }
        .stat-icon { width: 50px; height: 50px; display:grid; place-items:center; border-radius:14px; font-size:24px; }
    </style>
</head>
<body>
<div id="loading"><div class="loader simple-loader"><div class="loader-body"></div></div></div>
<aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all">
    <div class="sidebar-header d-flex align-items-center justify-content-start">
        <a href="index.php" class="navbar-brand"><h4 class="logo-title">Revista Digital</h4></a>
        <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">←</div>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar">
        <div class="sidebar-list"><ul class="navbar-nav iq-main-menu">
            <li class="nav-item static-item"><span class="nav-link static-item disabled">Administración</span></li>
            <li class="nav-item"><a class="nav-link active" href="index.php"><i class="icon">🏠</i><span class="item-name">Panel principal</span></a></li>
            <?php if (esAdmin()): ?><li class="nav-item"><a class="nav-link" href="usuarios.php"><i class="icon">👤</i><span class="item-name">Usuarios</span></a></li><?php endif; ?>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">✍️</i><span class="item-name">Autores</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">📰</i><span class="item-name">Reportajes</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">🖼️</i><span class="item-name">Fotos de reportajes</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">📢</i><span class="item-name">Noticias</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">📄</i><span class="item-name">Boletines</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">🎙️</i><span class="item-name">Podcasts</span></span></li>
            <li class="nav-item"><span class="nav-link disabled"><i class="icon">🎬</i><span class="item-name">Videos</span></span></li>
        </ul></div>
    </div>
</aside>
<main class="main-content">
    <nav class="navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner justify-content-end">
            <div class="dropdown">
                <a href="#" class="nav-link d-flex align-items-center" data-bs-toggle="dropdown">
                    <img src="../assets/images/avatars/01.png" class="avatar avatar-40 avatar-rounded me-2" alt="Usuario">
                    <div><h6 class="mb-0"><?= escapar($nombreUsuario) ?></h6><small class="text-muted"><?= escapar($rolUsuario) ?></small></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item text-danger" href="logout.php">Cerrar sesión</a></li></ul>
            </div>
        </div>
    </nav>
    <div class="iq-navbar-header hero-revista" style="height:215px">
        <div class="container-fluid iq-container"><h1 class="text-white">Panel de Revista Digital</h1><p class="text-white">Contenido administrado con la base de datos del profesor.</p></div>
    </div>
    <div class="content-inner mt-n5 py-0"><div class="container-fluid">
        <?php if (($_GET['permiso'] ?? '') === 'denegado'): ?>
            <div class="alert alert-warning">No tienes permiso para administrar usuarios.</div>
        <?php endif; ?>
        <div class="row g-3">
            <?php
            $tarjetas = [
                ['Usuarios', $datos['usuarios'], '👤'], ['Autores', $datos['autores'], '✍️'],
                ['Reportajes', $datos['reportajes'], '📰'], ['Fotos adicionales', $datos['fotos'], '🖼️'],
                ['Noticias', $datos['noticias'], '📢'], ['Boletines', $datos['boletines'], '📄'],
                ['Podcasts', $datos['podcasts'], '🎙️'], ['Videos', $datos['videos'], '🎬'],
            ];
            foreach ($tarjetas as $tarjeta): ?>
                <div class="col-xl-3 col-md-6"><div class="card"><div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-primary-subtle me-3"><?= $tarjeta[2] ?></div>
                    <div><p class="mb-1 text-muted"><?= escapar($tarjeta[0]) ?></p><h3 class="mb-0"><?= $tarjeta[1] ?></h3></div>
                </div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card mt-3"><div class="card-body text-center py-5">
            <h3>Módulo Usuarios disponible</h3>
            <p class="text-muted mb-0">Los demás módulos se activarán paso a paso.</p>
        </div></div>
    </div></div>
</main>
<script src="../assets/js/core/libs.min.js"></script>
<script src="../assets/js/core/external.min.js"></script>
<script src="../assets/js/hope-ui.js" defer></script>
</body>
</html>
