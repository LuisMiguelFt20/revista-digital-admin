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

function archivoBoletin(?string $valor): string
{
    $ruta = str_replace('\\', '/', trim((string)$valor));

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

function fechaBoletin(?string $valor): string
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        return '';
    }

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
        return '';
    }

    $meses = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    return $meses[(int)$fecha->format('n')]
        . ' ' . $fecha->format('d')
        . ', ' . $fecha->format('Y');
}

$paginaSolicitada = filter_input(
    INPUT_GET,
    'pagina',
    FILTER_VALIDATE_INT
);

$pagina = max(1, (int)($paginaSolicitada ?: 1));
$porPagina = 6;

$total = (int)$conexion
    ->query("SELECT COUNT(*) FROM boletines WHERE estado = 'publicado'")
    ->fetchColumn();

$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$inicio = ($pagina - 1) * $porPagina;

$consulta = $conexion->prepare(
    "SELECT
        id,
        numero_boletin,
        foto_portada,
        archivo_pdf,
        fecha_publicacion
     FROM boletines
     WHERE estado = 'publicado'
     ORDER BY fecha_publicacion DESC, id DESC
     LIMIT :cantidad OFFSET :inicio"
);

$consulta->bindValue(':cantidad', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);
$consulta->execute();

$boletines = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Mostrar como máximo cinco números en la paginación.
$primeraPagina = max(1, $pagina - 2);
$ultimaPagina = min($paginas, $primeraPagina + 4);
$primeraPagina = max(1, $ultimaPagina - 4);
$protocolo=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host=$_SERVER['HTTP_HOST']??'localhost';
$directorio=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
$urlCanonica=$protocolo.'://'.$host.$directorio.'/boletines.php'.($pagina>1?'?pagina='.$pagina:'');
$descripcionSeo='Ediciones publicadas del Boletín NTEP de Diálogo y Desarrollo Perú.';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Boletines NTEP | Revista Digital</title>
    <meta name="description" content="<?=escapar($descripcionSeo)?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?=escapar($urlCanonica)?>">
    <meta property="og:locale" content="es_PE">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Boletines NTEP | Diálogo y Desarrollo Perú">
    <meta property="og:description" content="<?=escapar($descripcionSeo)?>">
    <meta property="og:url" content="<?=escapar($urlCanonica)?>">

    <script>
        try {
            const tema = localStorage.getItem('theme');

            if (tema === 'dark' || tema === 'light') {
                document.documentElement.setAttribute('data-theme', tema);
            }
        } catch (error) {
            // Mantener el tema predeterminado.
        }
    </script>

    <link
        href="https://fonts.googleapis.com/css?family=Cabin:400,500,600,700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="assets/css/style-starter.css">

    <style>
        :root {
            --bol-fondo: #ffffff;
            --bol-superficie: #f6f7fa;
            --bol-texto: #292f36;
            --bol-menu: #696b72;
            --bol-gris: #888888;
            --bol-rojo: #ed0012;
            --bol-borde: #e5e7eb;
        }

        [data-theme="dark"] {
            --bol-fondo: #10141b;
            --bol-superficie: #1a202b;
            --bol-texto: #f1f3f6;
            --bol-menu: #c6cbd3;
            --bol-gris: #b1bac8;
            --bol-rojo: #ff5261;
            --bol-borde: #333b49;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bol-fondo);
            color: var(--bol-texto);
            font-family: "Cabin", Arial, sans-serif;
        }

        .bol-contenedor {
            width: calc(100% - 40px);
            max-width: 1140px;
            margin: 0 auto;
        }

        .bol-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--bol-fondo);
            box-shadow: 0 2px 8px rgb(0 0 0 / 5%);
        }

        .bol-menu {
            min-height: 94px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .bol-marca {
            display: inline-flex;
            flex-shrink: 0;
        }

        .bol-logo {
            display: block;
            width: 72px;
            height: 72px;
            object-fit: contain;
        }

        .bol-enlaces {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .bol-enlaces a,
        .bol-enlaces .menu-solo-texto {
            color: var(--bol-menu);
            font-size: 17px;
            line-height: 1.4;
            text-decoration: none;
            white-space: nowrap;
            transition: color .2s;
        }

        .bol-enlaces a:hover,
        .bol-enlaces a[aria-current="page"] {
            color: var(--bol-rojo);
        }

        .bol-enlaces .bol-contacto {
            padding: 13px 27px;
            border: 1px solid var(--bol-texto);
            border-radius: 9px;
            color: var(--bol-texto);
        }

        .bol-enlaces .bol-contacto:hover {
            border-color: var(--bol-rojo);
            color: var(--bol-rojo);
        }

        .bol-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            padding: 8px;
            border: 1px solid var(--bol-borde);
            border-radius: 6px;
            background: var(--bol-fondo);
            color: var(--bol-texto);
            cursor: pointer;
        }

        .bol-franja {
            padding: 45px 0;
            background: var(--bol-superficie);
        }

        .bol-franja-contenido {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .bol-franja h1 {
            margin: 0;
            color: var(--bol-texto);
            font-family: inherit;
            font-size: 32px;
            font-weight: 600;
            line-height: 1.3;
        }

        .bol-ruta {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--bol-gris);
            font-size: 17px;
        }

        .bol-ruta a {
            color: var(--bol-texto);
            text-decoration: none;
        }

        .bol-ruta a:hover {
            color: var(--bol-rojo);
        }

        .bol-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: start;
            column-gap: 28px;
            row-gap: 0;
            padding: 65px 0 35px;
        }

        .bol-card {
            min-width: 0;
            overflow: hidden;
            border-radius: 8px;
            background: var(--bol-superficie);
        }

        .bol-portada-enlace {
            display: block;
        }

        .bol-portada {
            display: block;
            width: 100%;
            height: auto;
        }

        .bol-sin-portada {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            padding: 25px;
            background: var(--bol-superficie);
            color: var(--bol-gris);
            text-align: center;
        }

        .bol-info {
            padding: 27px 22px 32px;
        }

        .bol-fecha {
            display: block;
            margin-bottom: 30px;
            color: var(--bol-gris);
            font-size: 15px;
            line-height: 1.5;
        }

        .bol-ver {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--bol-rojo);
            font-size: 18px;
            line-height: 1.5;
            text-decoration: none;
        }

        .bol-ver svg {
            flex-shrink: 0;
            transition: transform .2s;
        }

        .bol-ver:hover {
            color: var(--bol-rojo);
            text-decoration: underline;
        }

        .bol-ver:hover svg {
            transform: translateX(3px);
        }

        .bol-no-pdf {
            color: var(--bol-gris);
            font-size: 15px;
        }

        .bol-paginas {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 25px 0 65px;
        }

        .bol-paginas a,
        .bol-paginas span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            min-height: 40px;
            padding: 8px 12px;
            border-radius: 4px;
            color: var(--bol-rojo);
            font-size: 15px;
            text-decoration: none;
        }

        .bol-paginas a:hover {
            background: var(--bol-superficie);
        }

        .bol-paginas [aria-current="page"] {
            background: var(--bol-rojo);
            color: var(--bol-fondo);
        }

        .bol-paginas [aria-disabled="true"] {
            color: var(--bol-gris);
        }

        .bol-vacio {
            grid-column: 1 / -1;
            padding: 50px 20px;
            color: var(--bol-gris);
            text-align: center;
        }

        .bol-arriba {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            background: var(--bol-rojo);
            color: #ffffff;
        }

        .bol-arriba:hover {
            color: #ffffff;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--bol-rojo);
            outline-offset: 4px;
        }

        @media (max-width: 1050px) {
            .bol-menu {
                flex-wrap: wrap;
            }

            .bol-toggle {
                display: inline-flex;
            }

            .bol-enlaces {
                display: none;
                width: 100%;
                align-items: stretch;
                flex-direction: column;
                gap: 0;
                padding-bottom: 12px;
            }

            .bol-enlaces.abierto {
                display: flex;
            }

            .bol-enlaces a,
            .bol-enlaces .menu-solo-texto {
                padding: 12px 0;
            }

            .bol-enlaces .bol-contacto {
                align-self: flex-start;
                margin-top: 10px;
                padding: 12px 25px;
            }
        }

        @media (max-width: 800px) {
            .bol-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 24px;
                padding-top: 45px;
            }
        }

        @media (max-width: 540px) {
            .bol-menu {
                min-height: 80px;
            }

            .bol-logo {
                width: 62px;
                height: 62px;
            }

            .bol-franja {
                padding: 30px 0;
            }

            .bol-franja-contenido {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .bol-franja h1 {
                font-size: 28px;
            }

            .bol-ruta {
                font-size: 15px;
            }

            .bol-grid {
                grid-template-columns: 1fr;
                padding-top: 35px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .bol-ver svg,
            .bol-enlaces a {
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
        @media(max-width:767px) {
            .publico-pie-columnas {grid-template-columns:1fr;gap:35px;}
            .publico-pie {padding-top:40px;}
        }
    </style>

    <noscript>
        <style>
            @media (max-width: 1050px) {
                .bol-enlaces { display: flex; }
                .bol-toggle { display: none; }
            }
        </style>
    </noscript>
</head>

<body id="inicio-pagina">

<header class="bol-header">
    <div class="bol-contenedor bol-menu">
        <a class="bol-marca" href="index.php" aria-label="Ir al inicio">
            <img
                class="bol-logo"
                src="assets/images/logo.png"
                alt="Diálogo y Desarrollo"
                width="72"
                height="72"
            >
        </a>

        <button
            class="bol-toggle"
            type="button"
            aria-label="Abrir menú"
            aria-expanded="false"
            aria-controls="menu-boletines"
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

        <!-- Se mantienen los destinos del menú de tu index adjunto. -->
        <nav
            id="menu-boletines"
            class="bol-enlaces"
            aria-label="Menú principal"
        >
            <a href="index.php">Inicio</a>
            <a href="index.php#actualidad">Actualidad</a>
            <a href="reportajes.php">Reportajes</a>
            <a href="index.php#podcast">Podcast</a>
            <a href="boletines.php" aria-current="page">Boletín NTEP</a>
            <span class="menu-solo-texto" aria-disabled="true">Alianzas</span>
            <span class="menu-solo-texto" aria-disabled="true">Sobre D&amp;D</span>
            <a href="index.php#btn" class="bol-contacto">Contacto</a>
        </nav>
    </div>
</header>

<section class="bol-franja" aria-labelledby="titulo-boletines">
    <div class="bol-contenedor bol-franja-contenido">
        <h1 id="titulo-boletines">Boletines NTEP</h1>

        <nav class="bol-ruta" aria-label="Ruta de navegación">
            <a href="index.php">Inicio</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Boletines</span>
        </nav>
    </div>
</section>

<main class="bol-contenedor">
    <div class="bol-grid">
        <?php foreach ($boletines as $indice => $boletin): ?>
            <?php
            $numero = (string)$boletin['numero_boletin'];
            $portada = archivoBoletin($boletin['foto_portada'] ?? null);
            $pdf = archivoBoletin($boletin['archivo_pdf'] ?? null);
            $fecha = fechaBoletin($boletin['fecha_publicacion'] ?? null);
            $esPrimero = $pagina === 1 && $indice === 0;
            ?>

            <article
                class="bol-card"
                aria-label="Boletín <?= escapar($numero) ?>"
            >
                <?php if ($pdf !== ''): ?>
                    <a
                        class="bol-portada-enlace"
                        href="<?= escapar($pdf) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Abrir boletín <?= escapar($numero) ?> en PDF, en otra pestaña"
                    >
                <?php endif; ?>

                <?php if ($portada !== ''): ?>
                    <img
                        class="bol-portada"
                        src="<?= escapar($portada) ?>"
                        alt="Portada del boletín <?= escapar($numero) ?>"
                        loading="<?= $indice < 3 ? 'eager' : 'lazy' ?>"
                        decoding="async"
                    >
                <?php else: ?>
                    <div class="bol-sin-portada">
                        Boletín <?= escapar($numero) ?> — Sin portada
                    </div>
                <?php endif; ?>

                <?php if ($pdf !== ''): ?>
                    </a>
                <?php endif; ?>

                <div class="bol-info">
                    <?php if ($fecha !== ''): ?>
                        <time
                            class="bol-fecha"
                            datetime="<?= escapar($boletin['fecha_publicacion']) ?>"
                        >
                            <?= escapar($fecha) ?>
                        </time>
                    <?php endif; ?>

                    <?php if ($pdf !== ''): ?>
                        <a
                            class="bol-ver"
                            href="<?= escapar($pdf) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Ver boletín <?= escapar($numero) ?> en PDF, en otra pestaña"
                        >
                            <?= $esPrimero ? 'Ver Boletín' : 'Leer' ?>

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
                    <?php else: ?>
                        <span class="bol-no-pdf">PDF no disponible.</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$boletines): ?>
            <p class="bol-vacio">
                Todavía no hay boletines publicados.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
        <nav class="bol-paginas" aria-label="Páginas de boletines">
            <?php if ($pagina > 1): ?>
                <a
                    href="boletines.php?pagina=<?= $pagina - 1 ?>"
                    aria-label="Página anterior"
                >Ant</a>
            <?php else: ?>
                <span aria-disabled="true">Ant</span>
            <?php endif; ?>

            <?php for ($numeroPagina = $primeraPagina;
                       $numeroPagina <= $ultimaPagina;
                       $numeroPagina++): ?>
                <?php if ($numeroPagina === $pagina): ?>
                    <span
                        aria-current="page"
                        aria-label="Página <?= $numeroPagina ?>"
                    ><?= $numeroPagina ?></span>
                <?php else: ?>
                    <a
                        href="boletines.php?pagina=<?= $numeroPagina ?>"
                        aria-label="Ir a la página <?= $numeroPagina ?>"
                    ><?= $numeroPagina ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pagina < $paginas): ?>
                <a
                    href="boletines.php?pagina=<?= $pagina + 1 ?>"
                    aria-label="Página siguiente"
                >Sig</a>
            <?php else: ?>
                <span aria-disabled="true">Sig</span>
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

<a class="bol-arriba" href="#inicio-pagina" aria-label="Volver arriba">
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
    const botonMenu = document.querySelector('.bol-toggle');
    const menu = document.getElementById('menu-boletines');

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
