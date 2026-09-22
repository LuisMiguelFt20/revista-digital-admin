<?php
declare(strict_types=1);
require_once __DIR__.'/../dashboard/config/conexion.php';

$protocolo=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$host=$_SERVER['HTTP_HOST']??'localhost';
$directorio=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
$base=$protocolo.'://'.$host.$directorio;
$urls=[
 ['loc'=>$base.'/index.php','lastmod'=>date('Y-m-d')],
 ['loc'=>$base.'/reportajes.php','lastmod'=>date('Y-m-d')],
 ['loc'=>$base.'/boletines.php','lastmod'=>date('Y-m-d')],
 ['loc'=>$base.'/contacto.php','lastmod'=>date('Y-m-d')],
];
$q=$conexion->query("SELECT id,fecha_publicacion FROM reportajes WHERE estado='publicado' ORDER BY fecha_publicacion DESC,id DESC");
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $fila)$urls[]=['loc'=>$base.'/reportaje.php?id='.(int)$fila['id'],'lastmod'=>$fila['fecha_publicacion']];
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>',"\n";
?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach($urls as $url):?>  <url><loc><?=htmlspecialchars($url['loc'],ENT_XML1|ENT_QUOTES,'UTF-8')?></loc><lastmod><?=htmlspecialchars((string)$url['lastmod'],ENT_XML1|ENT_QUOTES,'UTF-8')?></lastmod></url>
<?php endforeach?></urlset>
