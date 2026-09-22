<?php
require_once __DIR__ . '/../dashboard/config/conexion.php';

function escapar($valor): string
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function fechaReportaje(?string $valor): string
{
    if (!$valor) {
        return '';
    }

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
        return '';
    }

    $meses = [
        1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
        'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'
    ];

    return $meses[(int)$fecha->format('n')]
        . ' ' . $fecha->format('d, Y');
}

function imagenReportaje(?string $ruta): string
{
    $ruta = str_replace('\\', '/', trim((string)$ruta));

    if (
        !str_starts_with($ruta, 'uploads/')
        || str_contains($ruta, '..')
        || preg_match('/[\x00-\x1F\x7F]/', $ruta)
    ) {
        return '';
    }

    return '../' . implode(
        '/',
        array_map('rawurlencode', explode('/', $ruta))
    );
}

$archivo = $_GET['archivo'] ?? '';
$archivoValido = is_string($archivo)
    && preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $archivo);
if ($archivo !== '' && !$archivoValido) {
    http_response_code(400);
    exit('Fecha inválida. Usa el formato año-mes, por ejemplo 2026-08.');
}
$mesesArchivo = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo',
    'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$condicion = " WHERE estado = 'publicado'";
$parametros = [];
$nombreArchivo = '';
if ($archivo !== '') {
    $desde = DateTimeImmutable::createFromFormat('!Y-m-d', $archivo . '-01');
    $hasta = $desde->modify('+1 month');
    $condicion .= ' AND fecha_publicacion >= :desde AND fecha_publicacion < :hasta';
    $parametros = ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')];
    $nombreArchivo = $mesesArchivo[(int)$desde->format('n')] . ' ' . $desde->format('Y');
}
function enlacePagina(int $numero, string $archivo): string
{
    $datos = ['pagina' => $numero];
    if ($archivo !== '') {
        $datos['archivo'] = $archivo;
    }
    return 'reportajes.php?' . http_build_query($datos);
}

$porPagina = 6;

$paginaSolicitada = filter_input(
    INPUT_GET,
    'pagina',
    FILTER_VALIDATE_INT
);

$pagina = max(1, (int)($paginaSolicitada ?: 1));

$conteo = $conexion->prepare('SELECT COUNT(*) FROM reportajes' . $condicion);
$conteo->execute($parametros);
$total = (int)$conteo->fetchColumn();

$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$desplazamiento = ($pagina - 1) * $porPagina;

$consulta = $conexion->prepare(
    'SELECT id, titulo, foto_principal, fecha_publicacion
     FROM reportajes' . $condicion . '
     ORDER BY fecha_publicacion DESC, id DESC
     LIMIT :cantidad OFFSET :desplazamiento'
);

foreach ($parametros as $clave => $valor) {
    $consulta->bindValue(':' . $clave, $valor, PDO::PARAM_STR);
}

$consulta->bindValue(':cantidad', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(
    ':desplazamiento',
    $desplazamiento,
    PDO::PARAM_INT
);
$consulta->execute();

$reportajes = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Mostrar hasta seis números de página.
$primeraPaginaVisible = max(1, $pagina - 2);
$ultimaPaginaVisible = min(
    $totalPaginas,
    $primeraPaginaVisible + 5
);
$primeraPaginaVisible = max(1, $ultimaPaginaVisible - 5);
$protocolo=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host=$_SERVER['HTTP_HOST']??'localhost';
$directorio=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
$urlCanonica=$protocolo.'://'.$host.$directorio.'/reportajes.php';
$consultaCanonica=[];if($archivo!=='')$consultaCanonica['archivo']=$archivo;if($pagina>1)$consultaCanonica['pagina']=$pagina;
if($consultaCanonica)$urlCanonica.='?'.http_build_query($consultaCanonica);
$descripcionSeo=$archivo!==''?'Reportajes publicados en '.$nombreArchivo.' por Diálogo y Desarrollo Perú.':'Reportajes, análisis y periodismo sobre diálogo y desarrollo en el Perú.';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Reportajes | Revista Digital</title>
    <meta name="description" content="<?=escapar($descripcionSeo)?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?=escapar($urlCanonica)?>">
    <meta property="og:locale" content="es_PE">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Reportajes | Diálogo y Desarrollo Perú">
    <meta property="og:description" content="<?=escapar($descripcionSeo)?>">
    <meta property="og:url" content="<?=escapar($urlCanonica)?>">

    <script>
        try {
            const tema = localStorage.getItem('theme');

            if (tema === 'dark' || tema === 'light') {
                document.documentElement.dataset.theme = tema;
            }
        } catch (error) {}
    </script>

    <link
        href="https://fonts.googleapis.com/css2?family=Cabin:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Estilos propios para evitar conflictos con otras secciones. -->
    <style>
        :root {
            --fondo: #ffffff;
            --superficie: #f6f7fa;
            --texto: #292f36;
            --menu: #696b72;
            --secundario: #888888;
            --rojo: #ed0012;
            --borde: #e5e7eb;
            --pagina: #e6f7ff;
        }

        html[data-theme="dark"] {
            --fondo: #10141b;
            --superficie: #1a202b;
            --texto: #f1f3f6;
            --menu: #c6cbd3;
            --secundario: #b1bac8;
            --rojo: #ff5261;
            --borde: #333b49;
            --pagina: #233243;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--fondo);
            color: var(--texto);
            font-family: "Cabin", Arial, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        .rp-contenedor {
            width: calc(100% - 40px);
            max-width: 1140px;
            margin: 0 auto;
        }

        .rp-cabecera {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--fondo);
            box-shadow: 0 2px 8px rgb(0 0 0 / 5%);
        }

        .rp-barra {
            min-height: 94px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 8px 0;
        }

        .rp-logo {
            flex-shrink: 0;
        }

        .rp-logo img {
            display: block;
            width: 72px;
            height: 72px;
            object-fit: contain;
        }

        .rp-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .rp-menu a,
        .rp-menu .menu-solo-texto {
            color: var(--menu);
            font-size: 17px;
            white-space: nowrap;
        }

        .rp-menu a:hover,
        .rp-menu a[aria-current="page"] {
            color: var(--rojo);
        }

        .rp-menu .rp-contacto {
            padding: 14px 28px;
            color: var(--texto);
            border: 1px solid var(--texto);
            border-radius: 9px;
        }

        .rp-menu .rp-contacto:hover {
            color: var(--rojo);
            border-color: var(--rojo);
        }

        .rp-menu-boton {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            padding: 8px;
            color: var(--texto);
            background: transparent;
            border: 1px solid var(--borde);
            border-radius: 6px;
            cursor: pointer;
        }

        .rp-franja {
            background: var(--superficie);
            padding: 48px 0;
        }

        .rp-franja-interior {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .rp-franja h1 {
            margin: 0;
            font-size: 34px;
            font-weight: 600;
            line-height: 1.3;
        }

        .rp-ruta {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--secundario);
            font-size: 17px;
        }

        .rp-ruta a {
            color: var(--texto);
        }

        .rp-ruta a:hover {
            color: var(--rojo);
        }

        .rp-principal {
            padding: 70px 0 55px;
        }

        .rp-cuadricula {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 46px 28px;
            align-items: start;
        }

        .rp-tarjeta {
            min-width: 0;
            overflow: hidden;
            border-radius: 9px;
            background: var(--superficie);
        }

        .rp-foto-enlace {
            display: block;
            overflow: hidden;
            aspect-ratio: 10 / 7;
        }

        .rp-foto {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }

        .rp-sin-foto {
            display: grid;
            width: 100%;
            height: 100%;
            padding: 20px;
            place-items: center;
            color: var(--secundario);
            border-bottom: 1px solid var(--borde);
        }

        .rp-informacion {
            padding: 28px 20px 32px;
        }

        .rp-fecha {
            display: block;
            margin-bottom: 24px;
            color: var(--secundario);
            font-size: 15px;
            line-height: 1.5;
        }

        .rp-titulo {
            margin: 0 0 28px;
            font-size: 23px;
            font-weight: 500;
            line-height: 1.3;
            overflow-wrap: break-word;
        }

        .rp-titulo a:hover {
            color: var(--rojo);
        }

        .rp-leer {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--rojo);
            font-size: 18px;
            line-height: 1.4;
        }

        .rp-leer:hover {
            text-decoration: underline;
        }

        .rp-leer svg {
            flex-shrink: 0;
            transition: transform .2s;
        }

        .rp-leer:hover svg {
            transform: translateX(3px);
        }

        .rp-paginacion {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 65px;
        }

        .rp-paginacion a,
        .rp-paginacion span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            min-height: 40px;
            padding: 8px 12px;
            border-radius: 4px;
            color: var(--rojo);
            background: var(--pagina);
            font-size: 15px;
        }

        .rp-paginacion a:hover {
            color: #ffffff;
            background: #ed0012;
        }

        .rp-paginacion [aria-current="page"] {
            color: #ffffff;
            background: #ed0012;
        }

        .rp-paginacion .rp-direccion,
        .rp-paginacion .rp-puntos {
            background: transparent;
        }

        .rp-paginacion .rp-direccion {
            margin: 0 8px;
        }

        .rp-paginacion .rp-direccion:hover {
            color: var(--rojo);
            text-decoration: underline;
        }

        .rp-paginacion [aria-disabled="true"] {
            color: var(--secundario);
        }

        .rp-vacio {
            padding: 45px 20px;
            border-radius: 8px;
            background: var(--superficie);
            text-align: center;
        }

        .rp-vacio h2 {
            font-size: 24px;
        }

        .rp-vacio p {
            color: var(--secundario);
        }

        .rp-arriba {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: #ffffff;
            background: #ed0012;
            border-radius: 5px;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--rojo);
            outline-offset: 4px;
        }

        @media (max-width: 1050px) {
            .rp-barra {
                flex-wrap: wrap;
            }

            .rp-menu-boton {
                display: inline-flex;
            }

            .rp-menu {
                display: none;
                width: 100%;
                align-items: stretch;
                flex-direction: column;
                gap: 0;
                padding-bottom: 15px;
            }

            .rp-menu.abierto {
                display: flex;
            }

            .rp-menu a,
            .rp-menu .menu-solo-texto {
                padding: 12px 0;
            }

            .rp-menu .rp-contacto {
                align-self: flex-start;
                margin-top: 8px;
                padding: 12px 25px;
            }
        }

        @media (max-width: 800px) {
            .rp-cuadricula {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 30px 24px;
            }

            .rp-principal {
                padding-top: 45px;
            }
        }

        @media (max-width: 540px) {
            .rp-barra {
                min-height: 80px;
            }

            .rp-logo img {
                width: 62px;
                height: 62px;
            }

            .rp-franja {
                padding: 30px 0;
            }

            .rp-franja-interior {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .rp-franja h1 {
                font-size: 29px;
            }

            .rp-ruta {
                font-size: 15px;
            }

            .rp-cuadricula {
                grid-template-columns: 1fr;
            }

            .rp-principal {
                padding-top: 35px;
            }

            .rp-paginacion {
                margin-top: 40px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .rp-leer svg {
                transition: none;
            }
        }

        .publico-pie {background:#202020;color:#aaa;padding:60px 0 35px;font-family:"Cabin",Arial,sans-serif;}
        .publico-pie-interior {width:calc(100% - 40px);max-width:1140px;margin:auto;}
        .publico-pie-columnas {display:grid;grid-template-columns:2fr .9fr 1fr;gap:45px;}
        .publico-pie h2 {margin:0 0 25px;color:white;font-size:27px;font-weight:500;}
        .publico-pie p {margin:0;font-size:17px;line-height:1.8;}
        .publico-pie a {color:#aaa;text-decoration:none;font-size:17px;overflow-wrap:anywhere;}
        .publico-pie a:hover {color:white;}
        .publico-pie nav:not(.publico-redes) a {display:block;margin:0 0 14px;}
        .publico-redes {display:flex;gap:20px;margin-top:30px;}
        .publico-redes a {display:inline-flex;padding:4px;}
        .publico-redes svg {width:20px;height:20px;}
        .publico-creditos {border-top:1px solid #3c443e;margin-top:48px;padding-top:35px;text-align:center;font-size:16px;}
        .rp-filtro {display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:35px;}
        .rp-filtro a {color:var(--rojo);text-decoration:underline;}
        @media(max-width:767px) {.publico-pie-columnas{grid-template-columns:1fr;gap:35px;}.publico-pie{padding-top:40px;}}

    </style>

    <noscript>
        <style>
            @media (max-width: 1050px) {
                .rp-menu { display: flex; }
                .rp-menu-boton { display: none; }
            }
        </style>
    </noscript>
</head>

<body id="inicio-pagina">

<header class="rp-cabecera">
    <div class="rp-contenedor rp-barra">
        <a class="rp-logo" href="index.php" aria-label="Ir al inicio">
            <img
                src="assets/images/logo.png"
                alt="Diálogo y Desarrollo"
                width="72"
                height="72"
            >
        </a>

        <button
            class="rp-menu-boton"
            type="button"
            aria-controls="menu-publico"
            aria-expanded="false"
            aria-label="Abrir menú"
        >
            <svg
                width="26"
                height="26"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>

        <nav class="rp-menu" id="menu-publico" aria-label="Menú principal">
            <a href="index.php">Inicio</a>
            <a href="index.php#actualidad">Actualidad</a>
            <a href="reportajes.php" aria-current="page">Reportajes</a>
            <a href="index.php#podcast">Podcast</a>
            <a href="boletines.php">Boletín NTEP</a>
            <span class="menu-solo-texto" aria-disabled="true">Alianzas</span>
            <span class="menu-solo-texto" aria-disabled="true">Sobre D&amp;D</span>
            <a class="rp-contacto" href="index.php#contacto">Contacto</a>
        </nav>
    </div>
</header>

<section class="rp-franja" aria-labelledby="titulo-pagina">
    <div class="rp-contenedor rp-franja-interior">
        <h1 id="titulo-pagina"><?= $archivo !== '' ? 'Reportajes · ' . escapar($nombreArchivo) : 'Reportajes' ?></h1>

        <nav class="rp-ruta" aria-label="Ruta de navegación">
            <a href="index.php">Inicio</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Reportajes</span>
        </nav>
    </div>
</section>

<main class="rp-contenedor rp-principal">
    <?php if ($archivo !== ''): ?>
        <div class="rp-filtro">
            <span><?= $total ?> reportaje(s) de <?= escapar($nombreArchivo) ?></span>
            <a href="reportajes.php">Ver todos los reportajes</a>
        </div>
    <?php endif; ?>

    <?php if (!$reportajes): ?>
        <section class="rp-vacio">
            <h2><?= $archivo !== '' ? 'No hay reportajes publicados en este mes.' : 'Todavía no hay reportajes registrados.' ?></h2>
            <p><a href="reportajes.php">Ver todos los reportajes</a></p>
        </section>
    <?php else: ?>

        <div class="rp-cuadricula">
            <?php foreach ($reportajes as $indice => $reportaje): ?>
                <?php
                $enlace = 'reportaje.php?id=' . (int)$reportaje['id'];
                $imagen = imagenReportaje($reportaje['foto_principal']);
                $fecha = fechaReportaje($reportaje['fecha_publicacion']);
                ?>

                <article class="rp-tarjeta">
                    <a
                        class="rp-foto-enlace"
                        href="<?= escapar($enlace) ?>"
                        aria-label="Leer: <?= escapar($reportaje['titulo']) ?>"
                    >
                        <?php if ($imagen !== ''): ?>
                            <img
                                class="rp-foto"
                                src="<?= escapar($imagen) ?>"
                                alt="<?= escapar($reportaje['titulo']) ?>"
                                loading="<?= $indice < 3 ? 'eager' : 'lazy' ?>"
                                decoding="async"
                            >
                        <?php else: ?>
                            <span class="rp-sin-foto">Sin imagen</span>
                        <?php endif; ?>
                    </a>

                    <div class="rp-informacion">
                        <?php if ($fecha !== ''): ?>
                            <time
                                class="rp-fecha"
                                datetime="<?= escapar($reportaje['fecha_publicacion']) ?>"
                            >
                                <?= escapar($fecha) ?>
                            </time>
                        <?php endif; ?>

                        <h2 class="rp-titulo">
                            <a href="<?= escapar($enlace) ?>">
                                <?= escapar($reportaje['titulo']) ?>
                            </a>
                        </h2>

                        <a
                            class="rp-leer"
                            href="<?= escapar($enlace) ?>"
                            aria-label="Leer: <?= escapar($reportaje['titulo']) ?>"
                        >
                            Leer
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M4 12h15M13 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <nav class="rp-paginacion" aria-label="Páginas de reportajes">
            <?php if ($pagina > 1): ?>
                <a
                    class="rp-direccion"
                    href="<?= escapar(enlacePagina($pagina - 1, $archivo)) ?>"
                    aria-label="Página anterior"
                >Ant</a>
            <?php else: ?>
                <span class="rp-direccion" aria-disabled="true">Ant</span>
            <?php endif; ?>

            <?php if ($primeraPaginaVisible > 1): ?>
                <a href="<?= escapar(enlacePagina(1, $archivo)) ?>" aria-label="Página 1">1</a>

                <?php if ($primeraPaginaVisible > 2): ?>
                    <span class="rp-puntos" aria-hidden="true">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($numero = $primeraPaginaVisible;
                       $numero <= $ultimaPaginaVisible;
                       $numero++): ?>

                <?php if ($numero === $pagina): ?>
                    <span
                        aria-current="page"
                        aria-label="Página <?= $numero ?>"
                    ><?= $numero ?></span>
                <?php else: ?>
                    <a
                        href="<?= escapar(enlacePagina($numero, $archivo)) ?>"
                        aria-label="Página <?= $numero ?>"
                    ><?= $numero ?></a>
                <?php endif; ?>

            <?php endfor; ?>

            <?php if ($ultimaPaginaVisible < $totalPaginas): ?>
                <?php if ($ultimaPaginaVisible < $totalPaginas - 1): ?>
                    <span class="rp-puntos" aria-hidden="true">…</span>
                <?php endif; ?>

                <a
                    href="<?= escapar(enlacePagina($totalPaginas, $archivo)) ?>"
                    aria-label="Página <?= $totalPaginas ?>"
                ><?= $totalPaginas ?></a>
            <?php endif; ?>

            <?php if ($pagina < $totalPaginas): ?>
                <a
                    class="rp-direccion"
                    href="<?= escapar(enlacePagina($pagina + 1, $archivo)) ?>"
                    aria-label="Página siguiente"
                >Sig</a>
            <?php else: ?>
                <span class="rp-direccion" aria-disabled="true">Sig</span>
            <?php endif; ?>
        </nav>

    <?php endif; ?>
</main>

<footer class="publico-pie">
    <div class="publico-pie-interior">
        <div class="publico-pie-columnas">
            <section>
                <h2>Quiénes Somos</h2>
                <p>Compartimos reportajes y noticias sobre la actualidad del Perú, el diálogo y el desarrollo de sus comunidades.</p>
                <nav class="publico-redes" aria-label="Redes sociales">
                    <a href="https://www.facebook.com/DialogoyDesarrolloPeru" target="_blank" rel="noopener noreferrer" aria-label="Facebook (otra pestaña)"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 22v-9h3l.5-4H14V7c0-1 .3-2 2-2h2V1.5C17 1.2 16 1 15 1c-4 0-6 2-6 6v2H6v4h3v9z"/></svg></a>
                    <a href="https://www.tiktok.com/@dialogo.y.desarrollo" target="_blank" rel="noopener noreferrer" aria-label="TikTok (otra pestaña)"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3" d="M14 3v13a5 5 0 1 1-5-5M14 3c0 4 3 6 7 6"/></svg></a>
                    <a href="https://www.instagram.com/dialogo.y.desarrollo/" target="_blank" rel="noopener noreferrer" aria-label="Instagram (otra pestaña)"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg></a>
                </nav>
            </section>
            <nav aria-label="Contenido del sitio">
                <h2>Contenido</h2>
                <a href="index.php#actualidad">Noticias</a>
                <a href="index.php#video">Videos</a>
                <a href="index.php#podcast">Podcast</a>
            </nav>
            <section>
                <h2>Contacto</h2>
                <a href="mailto:info@dialogoydesarrollo.com.pe">info@dialogoydesarrollo.com.pe</a>
            </section>
        </div>
        <div class="publico-creditos">© <?= date('Y') ?> Diálogo y Desarrollo Perú.</div>
    </div>
</footer>


<a class="rp-arriba" href="#inicio-pagina" aria-label="Volver arriba">
    <svg
        width="22"
        height="22"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="m6 14 6-6 6 6"/>
    </svg>
</a>

<script>
    const botonMenu = document.querySelector('.rp-menu-boton');
    const menu = document.getElementById('menu-publico');

    function cerrarMenu() {
        menu.classList.remove('abierto');
        botonMenu.setAttribute('aria-expanded', 'false');
        botonMenu.setAttribute('aria-label', 'Abrir menú');
    }

    botonMenu.addEventListener('click', function () {
        const abierto = menu.classList.toggle('abierto');

        botonMenu.setAttribute('aria-expanded', String(abierto));
        botonMenu.setAttribute(
            'aria-label',
            abierto ? 'Cerrar menú' : 'Abrir menú'
        );
    });

    menu.addEventListener('click', function (evento) {
        if (evento.target.closest('a')) {
            cerrarMenu();
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && menu.classList.contains('abierto')) {
            cerrarMenu();
            botonMenu.focus();
        }
    });
</script>

</body>
</html>
