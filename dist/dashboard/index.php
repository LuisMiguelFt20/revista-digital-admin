<?php
declare(strict_types=1);
require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/utilidades.php';
$vistaRedactor=esRedactor();

$tablas = ['reportajes'=>'Reportajes','noticias'=>'Noticias','boletines'=>'Boletines','podcasts'=>'Pódcasts','videos'=>'Videos','autores'=>'Autores','reportajes_fotos'=>'Fotos','usuarios'=>'Usuarios'];
$totales = [];
foreach ($tablas as $tabla=>$nombre) $totales[$tabla]=(int)$conexion->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
$tiposPublicacion=['reportajes','noticias','boletines','podcasts','videos'];
$totalPublicaciones=array_sum(array_intersect_key($totales,array_flip($tiposPublicacion)));
$totalMultimedia=$totales['podcasts']+$totales['videos'];
$destacados=(int)$conexion->query('SELECT COUNT(*) FROM reportajes WHERE es_destacado=1')->fetchColumn();

$filasMes=$conexion->query(
 "SELECT DATE_FORMAT(fecha_publicacion,'%Y-%m') mes,'reportajes' tipo,COUNT(*) total FROM reportajes GROUP BY mes
  UNION ALL SELECT DATE_FORMAT(fecha_publicacion,'%Y-%m'),'noticias',COUNT(*) FROM noticias GROUP BY DATE_FORMAT(fecha_publicacion,'%Y-%m')
  UNION ALL SELECT DATE_FORMAT(fecha_publicacion,'%Y-%m'),'boletines',COUNT(*) FROM boletines GROUP BY DATE_FORMAT(fecha_publicacion,'%Y-%m')
  UNION ALL SELECT DATE_FORMAT(fecha_publicacion,'%Y-%m'),'podcasts',COUNT(*) FROM podcasts GROUP BY DATE_FORMAT(fecha_publicacion,'%Y-%m')
  UNION ALL SELECT DATE_FORMAT(fecha_publicacion,'%Y-%m'),'videos',COUNT(*) FROM videos GROUP BY DATE_FORMAT(fecha_publicacion,'%Y-%m')"
)->fetchAll();
$mapaMeses=[];foreach($filasMes as $fila)$mapaMeses[$fila['mes']][$fila['tipo']]=(int)$fila['total'];
$nombresMes=[1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
$etiquetasMes=[];$series=array_fill_keys($tiposPublicacion,[]);$totalesMes=[];$inicio=new DateTimeImmutable('first day of this month');
for($i=11;$i>=0;$i--){$fecha=$inicio->modify("-$i months");$clave=$fecha->format('Y-m');$etiquetasMes[]=$nombresMes[(int)$fecha->format('n')].' '.$fecha->format('y');$suma=0;foreach($tiposPublicacion as $tipo){$cantidad=$mapaMeses[$clave][$tipo]??0;$series[$tipo][]=$cantidad;$suma+=$cantidad;}$totalesMes[]=$suma;}
$mesActual=end($totalesMes)?:0;$mesAnterior=$totalesMes[count($totalesMes)-2]??0;
$variacion=$mesAnterior>0?round((($mesActual-$mesAnterior)/$mesAnterior)*100):($mesActual>0?100:0);

$actividad=$conexion->query(
 "SELECT 'Reportaje' tipo,titulo,fecha_publicacion fecha,'reportajes.php' enlace FROM reportajes
  UNION ALL SELECT 'Noticia',titulo,fecha_publicacion,'noticias.php' FROM noticias
  UNION ALL SELECT 'Boletín',numero_boletin,fecha_publicacion,'boletines.php' FROM boletines
  UNION ALL SELECT 'Pódcast',titulo,fecha_publicacion,'podcasts.php' FROM podcasts
  UNION ALL SELECT 'Video',titulo,fecha_publicacion,'videos.php' FROM videos ORDER BY fecha DESC LIMIT 7"
)->fetchAll();
$ultimosReportajes=$conexion->query(
 "SELECT r.id,r.titulo,r.resumen_corto,r.foto_principal,r.fecha_publicacion,r.es_destacado,CONCAT_WS(' ',a.nombres,a.ap_paterno,a.ap_materno) autor
  FROM reportajes r JOIN autores a ON a.id=r.autor_id ORDER BY r.fecha_publicacion DESC,r.id DESC LIMIT 4"
)->fetchAll();
$autoresActivos=$conexion->query(
 "SELECT CONCAT_WS(' ',a.nombres,a.ap_paterno) autor,COUNT(r.id) total FROM autores a LEFT JOIN reportajes r ON r.autor_id=a.id
  GROUP BY a.id,a.nombres,a.ap_paterno ORDER BY total DESC,a.nombres LIMIT 5"
)->fetchAll();
if($vistaRedactor){
 $usuarioId=(int)$_SESSION['usuario_id'];
 $q=$conexion->prepare('SELECT COUNT(*) FROM reportajes WHERE usuario_id=?');$q->execute([$usuarioId]);$totalPropio=(int)$q->fetchColumn();
 $q=$conexion->prepare("SELECT COUNT(*) FROM reportajes WHERE usuario_id=? AND DATE_FORMAT(fecha_publicacion,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");$q->execute([$usuarioId]);$mesActual=(int)$q->fetchColumn();
 $q=$conexion->prepare("SELECT r.id,r.titulo,r.resumen_corto,r.foto_principal,r.fecha_publicacion,r.es_destacado,CONCAT_WS(' ',a.nombres,a.ap_paterno,a.ap_materno) autor FROM reportajes r JOIN autores a ON a.id=r.autor_id WHERE r.usuario_id=? ORDER BY r.fecha_publicacion DESC,r.id DESC LIMIT 5");$q->execute([$usuarioId]);$ultimosReportajes=$q->fetchAll();
 $totalPublicaciones=$totalPropio;$totales['reportajes']=$totalPropio;$totalMultimedia=0;$destacados=0;
}
$tituloPagina='Panel principal';$paginaActiva='index';require __DIR__.'/partials/head.php';
?>
<style>
.dashboard-hero{background:linear-gradient(120deg,#173a9b 0%,#3a76ed 70%,#45a3ff 100%);position:relative;overflow:hidden;min-height:210px}.dashboard-hero:after{content:"";position:absolute;width:380px;height:380px;border:70px solid rgba(255,255,255,.08);border-radius:50%;right:-80px;top:-180px}.metric-card{transition:transform .2s,box-shadow .2s;border:0}.metric-card:hover{transform:translateY(-5px);box-shadow:0 12px 30px rgba(35,70,184,.13)}.metric-icon{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;font-size:25px}.soft-blue{background:#e8edff}.soft-cyan{background:#dff8fa}.soft-orange{background:#fff0df}.soft-purple{background:#f0e7ff}.content-thumb{width:62px;height:48px;object-fit:cover;border-radius:8px;background:#e9ecf5}.timeline-dot{width:12px;height:12px;border-radius:50%;background:#3a57e8;box-shadow:0 0 0 5px #e8edff;flex:none;margin-top:6px}.quick-action{transition:.2s;border:1px solid #e8eaf2}.quick-action:hover{border-color:#3a57e8;background:#f4f6ff;transform:translateX(4px)}html.dark .quick-action{border-color:#34384a}html.dark .quick-action:hover{background:#282c3b}.chart-box{min-height:330px}
</style>
<?php if(isset($_GET['permiso'])):?><div class="alert alert-warning">Tu rol no permite administrar usuarios.</div><?php endif?>
<section class="dashboard-hero rounded-4 text-white p-4 p-md-5 mb-4"><div class="position-relative" style="z-index:2"><span class="badge bg-white text-primary mb-3"><?=$vistaRedactor?'MI ESPACIO DE REDACCIÓN':'RESUMEN EDITORIAL'?></span><h1 class="text-white mb-2"><?=$vistaRedactor?'Hola, '.e($_SESSION['usuario_nombre']??'Redactor'):'Panel de la Revista Digital'?></h1><p class="mb-4 opacity-75"><?=$vistaRedactor?'Crea y administra tus reportajes desde un solo lugar.':'Controla las publicaciones, autores y contenido multimedia desde un solo lugar.'?></p><div class="d-flex flex-wrap gap-2"><a class="btn btn-light text-primary" href="reportajes.php">＋ Nuevo reportaje</a><?php if(!$vistaRedactor):?><a class="btn btn-outline-light" href="noticias.php">＋ Nueva noticia</a><?php endif?></div></div></section>

<div class="row g-3 mb-4">
<?php $metricas=$vistaRedactor?[['📰','Mis reportajes',$totalPublicaciones,'Contenido creado por ti','soft-blue'],['📅','Creados este mes',$mesActual,'Tu actividad del mes','soft-cyan']]:[['🗂️','Total publicado',$totalPublicaciones,'Todos los contenidos','soft-blue'],['📅','Este mes',$mesActual,($variacion>=0?'▲ ':'▼ ').abs($variacion).'% frente al mes anterior','soft-cyan'],['📰','Reportajes',$totales['reportajes'],$destacados.' destacado(s)','soft-orange'],['▶️','Multimedia',$totalMultimedia,$totales['podcasts'].' pódcast · '.$totales['videos'].' video(s)','soft-purple']];foreach($metricas as [$ico,$titulo,$numero,$detalle,$clase]):?>
<div class="col-sm-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="metric-icon <?=$clase?>"><?=$ico?></div><div><div class="text-muted small"><?=e($titulo)?></div><div class="fs-2 fw-bold lh-sm"><?=e($numero)?></div><small class="<?=str_contains($detalle,'▼')?'text-danger':'text-success'?>"><?=e($detalle)?></small></div></div></div></div><?php endforeach?>
</div>

<?php if($vistaRedactor):?>
<div class="row g-4"><div class="col-xl-8"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center gap-3"><div><h4 class="mb-1">Mis últimos reportajes</h4><small class="text-muted">Acceso rápido al contenido que puedes editar</small></div><a href="reportajes.php" class="btn btn-sm btn-outline-primary">Ver todos</a></div><div class="mobile-table-help">Desliza la tabla hacia los lados para ver todas las columnas.</div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Reportaje</th><th>Fecha</th><th>Acción</th></tr></thead><tbody><?php if(!$ultimosReportajes):?><tr><td colspan="3" class="text-center p-5">Todavía no creaste reportajes.</td></tr><?php endif?><?php foreach($ultimosReportajes as $r):?><tr data-fila><td><strong><?=e($r['titulo'])?></strong><small class="d-block text-muted"><?=e(mb_strimwidth((string)$r['resumen_corto'],0,60,'…'))?></small></td><td><?=e(date('d/m/Y',strtotime($r['fecha_publicacion'])))?></td><td><a class="btn btn-sm btn-outline-primary" href="reportajes.php?editar=<?=e($r['id'])?>">Editar</a></td></tr><?php endforeach?></tbody></table></div></div></div></div><div class="col-xl-4"><div class="card h-100"><div class="card-header"><h4 class="mb-1">Tareas del redactor</h4><small class="text-muted">Flujo de trabajo recomendado</small></div><div class="card-body"><ol class="mb-0 ps-3"><li class="mb-3">Crea un reportaje y completa los campos obligatorios.</li><li class="mb-3">Revisa el texto, la fecha y los archivos adjuntos.</li><li>Guarda los cambios para que el editor pueda revisarlos.</li></ol></div></div></div></div>
<?php else:?>
<div class="row g-4 mb-4">
<div class="col-xl-8"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><div><h4 class="mb-1">Publicaciones por mes</h4><small class="text-muted">Actividad de los últimos doce meses</small></div><select id="rangoGrafico" class="form-select form-select-sm" style="width:145px"><option value="6">Últimos 6 meses</option><option value="12" selected>Últimos 12 meses</option></select></div><div class="card-body"><div id="graficoPublicaciones" class="chart-box"></div></div></div></div>
<div class="col-xl-4"><div class="card h-100"><div class="card-header"><h4 class="mb-1">Distribución del contenido</h4><small class="text-muted">Participación por tipo de publicación</small></div><div class="card-body"><div id="graficoDistribucion" class="chart-box"></div><div class="text-center text-muted small">Total: <strong><?=$totalPublicaciones?></strong> publicaciones</div></div></div></div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-8"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><div><h4 class="mb-1">Últimos reportajes</h4><small class="text-muted">Contenido editorial publicado recientemente</small></div><a href="reportajes.php" class="btn btn-sm btn-outline-primary">Ver todos</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Publicación</th><th>Autor</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
<?php if(!$ultimosReportajes):?><tr><td colspan="4" class="text-center p-5">Todavía no existen reportajes.</td></tr><?php endif?><?php foreach($ultimosReportajes as $r):?><tr><td><div class="d-flex align-items-center gap-3"><?php if($r['foto_principal']):?><img class="content-thumb" src="<?=e(rutaArchivo($r['foto_principal']))?>" alt=""><?php else:?><div class="content-thumb d-grid" style="place-items:center">📰</div><?php endif?><div><strong><?=e($r['titulo'])?></strong><small class="d-block text-muted"><?=e(mb_strimwidth((string)$r['resumen_corto'],0,60,'…'))?></small></div></div></td><td><?=e($r['autor'])?></td><td><?=e(date('d/m/Y',strtotime($r['fecha_publicacion'])))?></td><td><span class="badge bg-<?=$r['es_destacado']?'warning text-dark':'success'?>"><?=$r['es_destacado']?'Destacado':'Publicado'?></span></td></tr><?php endforeach?>
</tbody></table></div></div></div></div>
<div class="col-xl-4"><div class="card h-100"><div class="card-header"><h4 class="mb-1">Actividad reciente</h4><small class="text-muted">Movimientos de publicación</small></div><div class="card-body"><?php if(!$actividad):?><p class="text-muted text-center py-5">No existe actividad todavía.</p><?php endif?><?php foreach($actividad as $a):?><a href="<?=e($a['enlace'])?>" class="d-flex gap-3 text-reset text-decoration-none mb-4"><span class="timeline-dot"></span><div><strong><?=e($a['tipo'])?></strong><div><?=e(mb_strimwidth($a['titulo'],0,45,'…'))?></div><small class="text-muted"><?=e(date('d/m/Y',strtotime($a['fecha'])))?></small></div></a><?php endforeach?></div></div></div>
</div>

<div class="row g-4">
<div class="col-lg-5"><div class="card h-100"><div class="card-header"><h4 class="mb-1">Autores con más reportajes</h4><small class="text-muted">Participación del equipo editorial</small></div><div class="card-body"><?php $maxAutor=max(array_column($autoresActivos,'total')?:[1]);foreach($autoresActivos as $i=>$autor):$porcentaje=$maxAutor>0?round(((int)$autor['total']/$maxAutor)*100):0;?><div class="mb-4"><div class="d-flex justify-content-between mb-2"><span><span class="badge rounded-pill bg-primary me-2"><?=$i+1?></span><?=e($autor['autor'])?></span><strong><?=$autor['total']?></strong></div><div class="progress" style="height:7px"><div class="progress-bar" style="width:<?=$porcentaje?>%"></div></div></div><?php endforeach?></div></div></div>
<div class="col-lg-7"><div class="card h-100"><div class="card-header"><h4 class="mb-1">Acciones rápidas</h4><small class="text-muted">Accede directamente a las tareas más frecuentes</small></div><div class="card-body"><div class="row g-3"><?php foreach([['reportajes.php','📰','Crear reportaje','Publicar una nota completa'],['reportajes_fotos.php','🖼️','Agregar fotografías','Completar una galería'],['noticias.php','📣','Publicar noticia','Compartir un enlace externo'],['boletines.php','📄','Subir boletín','Adjuntar una nueva edición'],['podcasts.php','🎙️','Agregar pódcast','Publicar un episodio'],['videos.php','🎬','Agregar video','Publicar contenido audiovisual']] as [$url,$ico,$titulo,$texto]):?><div class="col-md-6"><a href="<?=$url?>" class="quick-action d-flex align-items-center gap-3 rounded-3 p-3 text-reset text-decoration-none"><span class="fs-3"><?=$ico?></span><span><strong><?=e($titulo)?></strong><small class="d-block text-muted"><?=e($texto)?></small></span><span class="ms-auto">›</span></a></div><?php endforeach?></div></div></div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
const etiquetas=<?=json_encode($etiquetasMes,JSON_UNESCAPED_UNICODE)?>;
const datos={reportajes:<?=json_encode($series['reportajes'])?>,noticias:<?=json_encode($series['noticias'])?>,boletines:<?=json_encode($series['boletines'])?>,podcasts:<?=json_encode($series['podcasts'])?>,videos:<?=json_encode($series['videos'])?>};
const nombres={reportajes:'Reportajes',noticias:'Noticias',boletines:'Boletines',podcasts:'Pódcasts',videos:'Videos'},colores=['#3a57e8','#08b1ba','#f16a1b','#8854d0','#24a148'];
const preparar=n=>Object.keys(datos).map(tipo=>({name:nombres[tipo],data:datos[tipo].slice(-n)}));
const principal=new ApexCharts(document.querySelector('#graficoPublicaciones'),{series:preparar(12),chart:{type:'area',height:330,toolbar:{show:false},zoom:{enabled:false}},colors:colores,dataLabels:{enabled:false},stroke:{curve:'smooth',width:3},fill:{type:'gradient',gradient:{opacityFrom:.28,opacityTo:.03}},xaxis:{categories:etiquetas},yaxis:{min:0,forceNiceScale:true},legend:{position:'top',horizontalAlign:'right'},grid:{borderColor:'#e9ecf3'},tooltip:{shared:true,intersect:false}});principal.render();
document.getElementById('rangoGrafico').addEventListener('change',function(){const n=Number(this.value);principal.updateOptions({xaxis:{categories:etiquetas.slice(-n)}});principal.updateSeries(preparar(n));});
new ApexCharts(document.querySelector('#graficoDistribucion'),{series:[<?=$totales['reportajes']?>,<?=$totales['noticias']?>,<?=$totales['boletines']?>,<?=$totales['podcasts']?>,<?=$totales['videos']?>],chart:{type:'donut',height:320},labels:['Reportajes','Noticias','Boletines','Pódcasts','Videos'],colors:colores,legend:{position:'bottom'},dataLabels:{enabled:true},plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Publicaciones'}}}}}}).render();
});
</script>
<?php endif?>
<?php require __DIR__.'/partials/footer.php'; ?>
