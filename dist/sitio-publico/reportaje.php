<?php
require_once __DIR__ . '/../dashboard/config/conexion.php';

function escapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fechaCorta(?string $valor): string
{
    if (!$valor) {
        return '';
    }

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    if (!$fecha) {
        return '';
    }

    $meses = [
        1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
        'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'
    ];

    return $meses[(int)$fecha->format('n')]
        . ' ' . $fecha->format('d, Y');
}

function archivoPublico(?string $valor): string
{
    $ruta = str_replace('\\', '/', trim((string)$valor));

    if (
        !str_starts_with($ruta, 'uploads/')
        || str_contains($ruta, '..')
        || preg_match('/[\x00-\x1F]/', $ruta)
    ) {
        return '';
    }

    return '../' . implode(
        '/',
        array_map('rawurlencode', explode('/', $ruta))
    );
}

function mostrarFotoDesarrollo(array $foto): string
{
    $ruta = archivoPublico($foto['url_foto']);
    if ($ruta === '') {
        return '';
    }
    $descripcion = trim((string)($foto['descripcion'] ?? ''));
    $alt = $descripcion !== '' ? $descripcion : 'Fotografía del reportaje';
    $html = '<figure class="rd-figura"><img class="rd-imagen" src="'
        . escapar($ruta) . '" alt="' . escapar($alt) . '" loading="lazy">';
    if ($descripcion !== '') {
        $html .= '<figcaption class="rd-pie-imagen">'
            . escapar($descripcion) . '</figcaption>';
    }
    return $html . '</figure>';
}

function mostrarDesarrolloConFotos(string $texto, array $fotos): string
{
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $porOrden = [];
    $mostradas = [];
    foreach ($fotos as $foto) {
        $orden = (int)($foto['orden'] ?? 0);
        if ($orden > 0) {
            $porOrden[$orden][] = $foto;
        }
    }
    $partes = preg_split(
        '/(\[foto:[1-9][0-9]*\])/i',
        $texto,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );
    $html = '';
    foreach ($partes as $parte) {
        if (preg_match('/^\[foto:([1-9][0-9]*)\]$/i', $parte, $marca)) {
            foreach ($porOrden[(int)$marca[1]] ?? [] as $foto) {
                $idFoto = (int)$foto['id'];
                if (!isset($mostradas[$idFoto])) {
                    $html .= mostrarFotoDesarrollo($foto);
                    $mostradas[$idFoto] = true;
                }
            }
            continue;
        }
        foreach (preg_split('/\n[ \t]*\n+/', trim($parte)) as $parrafo) {
            if (trim($parrafo) !== '') {
                $html .= '<p>' . nl2br(escapar($parrafo)) . '</p>';
            }
        }
    }
    // Las fotografías sin marca conservan su lugar al final.
    foreach ($fotos as $foto) {
        if (!isset($mostradas[(int)$foto['id']])) {
            $html .= mostrarFotoDesarrollo($foto);
        }
    }
    return $html;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$reportaje = false;
$fotos = [];
$recientes = [];
$imagen = '';
$pdf = '';
$autor = '';

if ($id && $id > 0) {
    $consulta = $conexion->prepare(
        "SELECT
            r.*,
            a.nombres AS autor_nombres,
            a.ap_paterno AS autor_paterno,
            a.ap_materno AS autor_materno,
            a.nickname AS autor_nickname,
            a.es_nickname AS autor_usa_nickname
         FROM reportajes r
         LEFT JOIN autores a ON a.id = r.autor_id
         WHERE r.id = :id AND r.estado = 'publicado'
         LIMIT 1"
    );

    $consulta->execute(['id' => $id]);
    $reportaje = $consulta->fetch(PDO::FETCH_ASSOC);
}

if (!$reportaje) {
    http_response_code(404);
} else {
    $imagen = archivoPublico($reportaje['foto_principal']);
    $pdf = archivoPublico($reportaje['pdf_adjunto']);

    if (
        (int)$reportaje['autor_usa_nickname'] === 1
        && trim((string)$reportaje['autor_nickname']) !== ''
    ) {
        $autor = trim($reportaje['autor_nickname']);
    } else {
        $autor = trim(implode(' ', array_filter(
            [
                $reportaje['autor_nombres'],
                $reportaje['autor_paterno'],
                $reportaje['autor_materno']
            ],
            static fn($parte) => trim((string)$parte) !== ''
        )));
    }

    if ($autor === '') {
        $autor = 'Redacción';
    }

    $consultaFotos = $conexion->prepare(
        'SELECT id, url_foto, descripcion, orden
         FROM reportajes_fotos
         WHERE reportaje_id = :id
         ORDER BY orden ASC, id ASC'
    );

    $consultaFotos->execute(['id' => $id]);
    $fotos = $consultaFotos->fetchAll(PDO::FETCH_ASSOC);

    $recientes = $conexion->query(
        'SELECT id, titulo, fecha_publicacion
         FROM reportajes
         WHERE estado = \'publicado\'
         ORDER BY fecha_publicacion DESC, id DESC
         LIMIT 3'
    )->fetchAll(PDO::FETCH_ASSOC);


}

$archivos = $conexion->query(
    "SELECT DATE_FORMAT(fecha_publicacion, '%Y-%m') AS periodo
     FROM reportajes
     WHERE estado = 'publicado' AND fecha_publicacion IS NOT NULL AND fecha_publicacion >= '1000-01-01'
     GROUP BY DATE_FORMAT(fecha_publicacion, '%Y-%m')
     ORDER BY periodo DESC"
)->fetchAll(PDO::FETCH_COLUMN);
$mesesArchivo = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo',
    'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$tituloPagina = $reportaje
    ? $reportaje['titulo']
    : 'Reportaje no encontrado';
$protocolo=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host=$_SERVER['HTTP_HOST']??'localhost';
$directorio=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
$urlBase=$protocolo.'://'.$host.$directorio;
$urlCanonica=$reportaje?$urlBase.'/reportaje.php?id='.(int)$reportaje['id']:$urlBase.'/reportaje.php';
$descripcionSeo=$reportaje?trim((string)$reportaje['resumen_corto']):'El reportaje solicitado no está disponible.';
if($descripcionSeo==='')$descripcionSeo='Reportaje publicado por Diálogo y Desarrollo Perú.';
$descripcionSeo=mb_strimwidth($descripcionSeo,0,160,'…','UTF-8');
$imagenSeo=$reportaje&&!empty($reportaje['foto_principal'])?dirname($urlBase).'/'.ltrim((string)$reportaje['foto_principal'],'/'):$urlBase.'/assets/images/logo.png';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= escapar($tituloPagina) ?> | Revista Digital</title>
    <meta name="description" content="<?=escapar($descripcionSeo)?>">
    <meta name="robots" content="<?=$reportaje?'index,follow,max-image-preview:large':'noindex,follow'?>">
    <?php if($reportaje):?><link rel="canonical" href="<?=escapar($urlCanonica)?>"><?php endif?>
    <meta property="og:locale" content="es_PE">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?=escapar($tituloPagina)?>">
    <meta property="og:description" content="<?=escapar($descripcionSeo)?>">
    <meta property="og:url" content="<?=escapar($urlCanonica)?>">
    <meta property="og:image" content="<?=escapar($imagenSeo)?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php if($reportaje):?><script type="application/ld+json"><?=json_encode(['@context'=>'https://schema.org','@type'=>'NewsArticle','headline'=>$reportaje['titulo'],'description'=>$descripcionSeo,'datePublished'=>$reportaje['fecha_publicacion'],'author'=>['@type'=>'Person','name'=>$autor],'image'=>[$imagenSeo],'mainEntityOfPage'=>$urlCanonica],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script><?php endif?>

    <script>
        try {
            const tema = localStorage.getItem('theme');

            if (tema === 'dark' || tema === 'light') {
                document.documentElement.setAttribute('data-theme', tema);
            }
        } catch (error) {
            // Funciona también si el almacenamiento está deshabilitado.
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Cabin:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --rd-fondo: #ffffff;
            --rd-superficie: #f5f7fa;
            --rd-texto: #172331;
            --rd-secundario: #788390;
            --rd-acento: #e60012;
            --rd-borde: #e1e5eb;
        }

        html[data-theme="dark"] {
            --rd-fondo: #10141b;
            --rd-superficie: #1a202b;
            --rd-texto: #f1f3f6;
            --rd-secundario: #b1bac8;
            --rd-acento: #ff5261;
            --rd-borde: #323a47;
        }

        body {
            margin: 0;
            color: var(--rd-texto);
            background: var(--rd-fondo);
        }

        .rd-contenedor {
            width: min(1140px, calc(100% - 40px));
            margin-inline: auto;
        }

        .rd-cabecera {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--rd-fondo);
            box-shadow: 0 2px 7px rgb(0 0 0 / 5%);
        }

        .rd-barra {
            min-height: 94px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .rd-logo {
            flex-shrink: 0;
        }

        .rd-logo img {
            display: block;
            width: 72px;
            height: 72px;
            object-fit: contain;
        }

        .rd-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .rd-menu a,
        .rd-menu .menu-solo-texto {
            color: var(--rd-secundario);
            font-size: 16px;
            text-decoration: none;
        }

        .rd-menu a:hover,
        .rd-menu .activo {
            color: var(--rd-acento);
        }

        .rd-menu .rd-contacto {
            padding: 11px 24px;
            color: var(--rd-texto);
            border: 1px solid var(--rd-texto);
            border-radius: 8px;
        }

        .rd-menu-boton {
            display: none;
            padding: 8px 12px;
            color: var(--rd-texto);
            background: transparent;
            border: 1px solid var(--rd-borde);
            border-radius: 5px;
            font-size: 23px;
            cursor: pointer;
        }

        .rd-franja {
            padding: 40px 0;
            background: var(--rd-superficie);
        }

        .rd-franja-interior {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .rd-franja-titulo {
            margin: 0;
            color: var(--rd-texto);
            font-size: 30px;
            font-weight: 600;
        }

        .rd-ruta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--rd-secundario);
            font-size: 14px;
        }

        .rd-ruta a {
            color: var(--rd-texto);
        }

        .rd-principal {
            padding-block: 75px;
        }

        .rd-columnas {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 40px;
            align-items: start;
        }

        .rd-titulo {
            margin: 0 0 18px;
            color: var(--rd-texto);
            font-size: 30px;
            font-weight: 500;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .rd-datos {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            margin-bottom: 22px;
            color: var(--rd-secundario);
            font-size: 14px;
        }

        .rd-figura {
            margin: 0 0 35px;
        }

        .rd-imagen {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .rd-pie-imagen {
            margin-top: 8px;
            color: var(--rd-secundario);
            text-align: center;
            font-size: 13px;
            line-height: 1.5;
        }

        .rd-pie-imagen a {
            color: var(--rd-acento);
        }

        .rd-resumen {
            margin: 35px auto;
            padding: 0 25px;
            max-width: 620px;
            color: var(--rd-texto);
            font-size: 21px;
            font-style: italic;
            line-height: 1.6;
        }

        .rd-desarrollo p {
            margin: 0 0 24px;
            color: var(--rd-secundario);
            font-size: 17px;
            line-height: 1.75;
            text-align: justify;
            overflow-wrap: anywhere;
        }

        .rd-fotos-adicionales {
            margin-top: 35px;
        }

        .rd-pdf {
            display: inline-block;
            margin-bottom: 30px;
            padding: 11px 18px;
            color: var(--rd-acento);
            border: 1px solid var(--rd-acento);
            border-radius: 4px;
        }

        .rd-pdf:hover {
            color: var(--rd-acento);
            text-decoration: underline;
        }

        .rd-volver {
            margin-top: 25px;
            padding: 25px 10px;
            border-top: 1px solid var(--rd-borde);
            border-bottom: 1px solid var(--rd-borde);
        }

        .rd-volver a {
            color: var(--rd-acento);
            font-size: 14px;
        }

        .rd-lateral {
            padding-top: 48px;
        }

        .rd-lateral h2 {
            margin: 0 0 24px;
            color: var(--rd-texto);
            font-size: 24px;
            font-weight: 500;
        }

        .rd-recientes {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .rd-recientes li {
            margin-bottom: 25px;
        }

        .rd-recientes a {
            display: block;
            color: var(--rd-texto);
            font-size: 18px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .rd-recientes a:hover {
            color: var(--rd-acento);
        }

        .rd-recientes time {
            display: block;
            margin-top: 7px;
            color: var(--rd-secundario);
            font-size: 13px;
        }

        .rd-error {
            padding: 50px 20px;
            text-align: center;
            background: var(--rd-superficie);
        }

        .rd-error h1 {
            color: var(--rd-texto);
            font-size: 28px;
        }

        .rd-error a {
            color: var(--rd-acento);
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--rd-acento);
            outline-offset: 4px;
        }

        @media (max-width: 1000px) {
            .rd-barra {
                flex-wrap: wrap;
                gap: 0;
                padding-block: 10px;
            }

            .rd-menu-boton {
                display: block;
            }

            .rd-menu {
                display: none;
                width: 100%;
                padding-block: 20px;
                flex-direction: column;
                align-items: flex-start;
            }

            .rd-menu.abierto {
                display: flex;
            }
        }

        @media (max-width: 767px) {
            .rd-columnas {
                grid-template-columns: 1fr;
            }

            .rd-franja-interior {
                align-items: flex-start;
                flex-direction: column;
            }

            .rd-principal {
                padding-block: 35px;
            }

            .rd-titulo {
                font-size: 26px;
            }

            .rd-resumen {
                padding-inline: 0;
                font-size: 19px;
            }

            .rd-desarrollo p {
                text-align: left;
            }

            .rd-lateral {
                padding-top: 10px;
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

        * {box-sizing:border-box;}
        body {margin:0;font-family:"Cabin",Arial,sans-serif;}
        a {text-decoration:none;}
        :root {--rd-texto:#292f36;--rd-secundario:#888;--rd-superficie:#f6f7fa;}
        .rd-menu a {font-size:17px;white-space:nowrap;}
        .rd-franja {padding:48px 0;}
        .rd-franja-titulo {font-size:34px;line-height:1.3;}
        .rd-ruta {font-size:17px;}
        .rd-principal {padding:100px 0;}
        .rd-columnas {grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:40px;}
        .rd-titulo {font-size:32px;line-height:1.2;font-weight:500;overflow-wrap:break-word;margin:0 0 20px;}
        .rd-resumen {max-width:620px;padding:0 45px;margin:48px auto;font-size:22px;line-height:1.6;}
        .rd-desarrollo p {font-size:18px;line-height:1.65;overflow-wrap:break-word;margin-bottom:24px;}
        .rd-datos {margin:0 0 18px;font-size:14px;}
        .rd-lateral {padding-top:58px;}
        .rd-recientes a {font-size:19px;overflow-wrap:break-word;}
        .rd-recientes time {font-size:14px;}
        .rd-archivos {margin-top:65px;}
        .rd-archivos ul {padding-left:20px;margin:0;color:var(--rd-secundario);}
        .rd-archivos li {padding-left:4px;margin-bottom:12px;}
        .rd-archivos a {color:var(--rd-secundario);font-size:19px;}
        .rd-archivos a:hover {color:var(--rd-acento);text-decoration:underline;}
        .rd-volver {margin-top:35px;padding:25px 12px;}
        .rd-arriba {position:fixed;right:18px;bottom:18px;z-index:900;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:5px;background:#ed0012;color:white;}
        @media(max-width:767px){.rd-columnas{grid-template-columns:1fr;}.rd-principal{padding:40px 0;}.rd-titulo{font-size:27px;}.rd-resumen{padding:0 15px;font-size:20px;margin:30px auto;}.rd-lateral{padding-top:10px;}.rd-desarrollo p{text-align:left;font-size:17px;}.rd-archivos{margin-top:35px;}.rd-franja{padding:30px 0;}}

    </style>
<noscript><style>@media(max-width:1000px){.rd-menu{display:flex}.rd-menu-boton{display:none}}</style></noscript>
</head>
<body id="inicio-pagina">

<header class="rd-cabecera">
    <div class="rd-contenedor rd-barra">
        <a class="rd-logo" href="index.php" aria-label="Ir al inicio">
            <img
                src="assets/images/logo.png"
                alt="Diálogo y Desarrollo"
            >
        </a>

        <button
            class="rd-menu-boton"
            type="button"
            aria-controls="menu-publico"
            aria-expanded="false"
            aria-label="Abrir menú"
        >☰</button>

        <nav class="rd-menu" id="menu-publico" aria-label="Menú principal">
            <a href="index.php">Inicio</a>
            <a href="index.php#actualidad">Actualidad</a>
            <a class="activo" href="reportajes.php">Reportajes</a>
            <a href="index.php#podcast">Podcast</a>
            <a href="boletines.php">Boletín NTEP</a>
            <span class="menu-solo-texto" aria-disabled="true">Alianzas</span>
            <span class="menu-solo-texto" aria-disabled="true">Sobre D&amp;D</span>
            <a class="rd-contacto" href="index.php#contacto">Contacto</a>
        </nav>
    </div>
</header>

<section class="rd-franja">
    <div class="rd-contenedor rd-franja-interior">
        <p class="rd-franja-titulo">Reportajes</p>

        <nav class="rd-ruta" aria-label="Ruta de navegación">
            <a href="index.php">Inicio</a>
            <span aria-hidden="true">/</span>
            <a href="reportajes.php">Reportajes</a>
        </nav>
    </div>
</section>

<main class="rd-contenedor rd-principal">

    <?php if (!$reportaje): ?>

        <section class="rd-error">
            <h1>Reportaje no encontrado</h1>
            <p>El enlace no es válido o el reportaje fue eliminado.</p>
            <a href="reportajes.php">Volver a reportajes</a>
        </section>

    <?php else: ?>

        <div class="rd-columnas">

            <article>
                <h1 class="rd-titulo">
                    <?= escapar($reportaje['titulo']) ?>
                </h1>

                <?php if ($imagen !== ''): ?>
                    <figure class="rd-figura">

                        <?php if ($pdf !== ''): ?>
                            <a
                                href="<?= escapar($pdf) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Abrir PDF adjunto en otra pestaña"
                            >
                                <img
                                    class="rd-imagen"
                                    src="<?= escapar($imagen) ?>"
                                    alt="<?= escapar($reportaje['titulo']) ?>"
                                >
                            </a>

                            <figcaption class="rd-pie-imagen">
                                <a
                                    href="<?= escapar($pdf) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Clic en la imagen para abrir el documento completo
                                </a>
                            </figcaption>

                        <?php else: ?>
                            <img
                                class="rd-imagen"
                                src="<?= escapar($imagen) ?>"
                                alt="<?= escapar($reportaje['titulo']) ?>"
                            >
                        <?php endif; ?>

                    </figure>
                <?php endif; ?>

                <?php if (trim((string)$reportaje['resumen_corto']) !== ''): ?>
                    <div class="rd-resumen">
                        <?= nl2br(escapar($reportaje['resumen_corto'])) ?>
                    </div>
                <?php endif; ?>

                <div class="rd-datos">
                    <span>Por <?= escapar($autor) ?></span>

                    <?php if ($reportaje['fecha_publicacion']): ?>
                        <time datetime="<?= escapar($reportaje['fecha_publicacion']) ?>">
                            <?= escapar(fechaCorta($reportaje['fecha_publicacion'])) ?>
                        </time>
                    <?php endif; ?>
                </div>

                <div class="rd-desarrollo">
                    <?= mostrarDesarrolloConFotos(
                        (string)$reportaje['desarrollo'],
                        $fotos
                    ) ?>
                </div>

                <?php if ($pdf !== ''): ?>
                    <a
                        class="rd-pdf"
                        href="<?= escapar($pdf) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Abrir PDF adjunto
                    </a>
                <?php endif; ?>

                <nav class="rd-volver" aria-label="Volver al listado">
                    <a href="reportajes.php">
                        <span aria-hidden="true">←</span> Reportajes
                    </a>
                </nav>
            </article>

            <aside class="rd-lateral">
                <h2>Últimas noticias</h2>

                <ul class="rd-recientes">
                    <?php foreach ($recientes as $reciente): ?>
                        <li>
                            <a href="reportaje.php?id=<?= (int)$reciente['id'] ?>">
                                <?= escapar($reciente['titulo']) ?>
                            </a>

                            <?php if ($reciente['fecha_publicacion']): ?>
                                <time datetime="<?= escapar($reciente['fecha_publicacion']) ?>">
                                    <?= escapar(fechaCorta($reciente['fecha_publicacion'])) ?>
                                </time>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <section class="rd-archivos" aria-labelledby="titulo-archivos">
                    <h2 id="titulo-archivos">Archivos</h2>
                    <ul>
                        <?php foreach ($archivos as $periodo): ?>
                            <?php
                            $mes = (int)substr($periodo, 5, 2);
                            $etiqueta = $mesesArchivo[$mes] . ' ' . substr($periodo, 0, 4);
                            ?>
                            <li><a href="reportajes.php?archivo=<?= escapar($periodo) ?>"><?= escapar($etiqueta) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </aside>

        </div>

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


<a class="rd-arriba" href="#inicio-pagina" aria-label="Volver arriba"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg></a>
<script>
    const botonMenu = document.querySelector('.rd-menu-boton');
    const menu = document.getElementById('menu-publico');

    botonMenu.addEventListener('click', function () {
        const abierto = menu.classList.toggle('abierto');

        botonMenu.setAttribute('aria-expanded', String(abierto));
        botonMenu.setAttribute(
            'aria-label',
            abierto ? 'Cerrar menú' : 'Abrir menú'
        );
    });
</script>

</body>
</html>
