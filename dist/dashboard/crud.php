<?php
declare(strict_types=1);
require_once __DIR__ . '/config/seguridad.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/utilidades.php';
require_once __DIR__ . '/config/modulos.php';

$config = configuracionModulo($modulo ?? '');
$tabla = $config['tabla'];
if(esRedactor()){if($tabla!=='reportajes')denegarAcceso();authReport($conexion,(int)($_GET['editar']??0));authReport($conexion,(int)($_POST['id']??0));if(($_POST['accion']??'')==='eliminar')denegarAcceso();}

$mensaje = ''; $error = ''; $edicion = null;
$editorFallo=false;
$editorPost=$tabla==='reportajes' && $_SERVER['REQUEST_METHOD']==='POST' && (($_POST['accion']??'')==='guardar' || !$_POST);
if($tabla==='reportajes') require_once __DIR__.'/config/editor_reportajes.php';
if($editorPost) {
    try {
        if(!$_POST) throw new RuntimeException('El envío supera el límite del servidor. Vuelve atrás y sube menos imágenes o archivos más pequeños.');
        $guardado=erGuardar($conexion);
        header('Location: reportajes.php?editar='.$guardado.'&guardado=1');
        exit;
    } catch(Throwable $ex) {
        $editorFallo=true;
        $error=$ex instanceof PDOException?'No se pudo guardar. Revisa el autor seleccionado y los datos.':$ex->getMessage();
    }
}
if($tabla==='reportajes' && isset($_GET['guardado'])) $mensaje='Reportaje guardado correctamente.';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$editorPost) {
        validarCsrf();
        $accion = $_POST['accion'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        if ($accion === 'eliminar') {
            $consulta = $conexion->prepare("SELECT * FROM `$tabla` WHERE id=?"); $consulta->execute([$id]); $viejo=$consulta->fetch();
            $conexion->prepare("DELETE FROM `$tabla` WHERE id=?")->execute([$id]);
            foreach ($config['campos'] as $nombre=>$campo) if (str_starts_with($campo[1], 'file-')) borrarArchivo($viejo[$nombre] ?? null);
            $mensaje = ucfirst($config['singular']) . ' eliminado correctamente.';
        } elseif ($accion === 'guardar') {
            $anterior = null;
            if ($id) { $q=$conexion->prepare("SELECT * FROM `$tabla` WHERE id=?"); $q->execute([$id]); $anterior=$q->fetch(); if(!$anterior) throw new RuntimeException('El registro ya no existe.'); }
            $datos=[];
            foreach ($config['campos'] as $nombre=>$campo) {
                [$etiqueta,$tipo,$requerido]=$campo;
                if ($tipo === 'session') { $datos[$nombre]=$anterior[$nombre]??(int)$_SESSION['usuario_id']; continue; }
                if ($tipo === 'checkbox') { $datos[$nombre]=isset($_POST[$nombre])?1:0; continue; }
                if (str_starts_with($tipo,'file-')) {
                    $previo=$anterior[$nombre]??null; $ext=$tipo==='file-pdf'?['pdf']:['jpg','jpeg','png','webp'];
                    $datos[$nombre]=guardarArchivo($nombre,$campo[3],$ext,$previo);
                    if ($requerido && !$datos[$nombre]) throw new RuntimeException("El campo $etiqueta es obligatorio.");
                    continue;
                }
                $valor=trim((string)($_POST[$nombre]??''));
                if ($requerido && $valor==='') throw new RuntimeException("El campo $etiqueta es obligatorio.");
                if ($tipo==='url' && $valor!=='' && !filter_var($valor,FILTER_VALIDATE_URL)) throw new RuntimeException("El campo $etiqueta debe contener una URL válida.");
                if ($tipo==='select' && !array_key_exists($valor,$campo[3]??[])) throw new RuntimeException("Selecciona una opción válida en $etiqueta.");
                $datos[$nombre]=$valor===''?null:$valor;
            }
            if ($tabla==='reportajes' && ($datos['estado']??'')!=='publicado') $datos['es_destacado']=0;
            if ($tabla==='reportajes' && !empty($datos['es_destacado'])) $conexion->exec('UPDATE reportajes SET es_destacado=0');
            if ($id) {
                $partes=array_map(fn($c)=>"`$c`=:$c",array_keys($datos)); $datos['id']=$id;
                $conexion->prepare("UPDATE `$tabla` SET ".implode(',',$partes).' WHERE id=:id')->execute($datos);
                $mensaje=ucfirst($config['singular']).' actualizado correctamente.';
            } else {
                $cols=array_keys($datos); $marcas=array_map(fn($c)=>":$c",$cols);
                $conexion->prepare("INSERT INTO `$tabla` (`".implode('`,`',$cols).'`) VALUES ('.implode(',',$marcas).')')->execute($datos);
                $mensaje=ucfirst($config['singular']).' registrado correctamente.';
            }
        }
    }
} catch (Throwable $ex) {
    $error = $ex instanceof PDOException ? 'No se pudo completar la operación. Revisa datos duplicados o registros relacionados.' : $ex->getMessage();
}

$editarId=filter_input(INPUT_GET,'editar',FILTER_VALIDATE_INT)?:0;
if ($editarId) { $q=$conexion->prepare("SELECT * FROM `$tabla` WHERE id=?");$q->execute([$editarId]);$edicion=$q->fetch()?:null; }
if($editorFallo) {
    $postId=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT)?:0;
    $edicion=['id'=>$postId];
    foreach($config['campos'] as $n=>$c) {
        if(str_starts_with($c[1],'file-')||$c[1]==='session') continue;
        $edicion[$n]=$c[1]==='checkbox'?(isset($_POST[$n])?1:0):(string)($_POST[$n]??'');
    }
}
$sql=$config['list_sql']??"SELECT * FROM `$tabla` ORDER BY id DESC";
if(esRedactor())$sql='SELECT * FROM reportajes WHERE usuario_id='.(int)$_SESSION['usuario_id'].' ORDER BY id DESC';
$registros=$conexion->query($sql)->fetchAll();
$tituloPagina=$config['titulo'];$paginaActiva=$config['activa'];
require __DIR__.'/partials/head.php';
?>
<section class="section-hero"><div class="d-flex align-items-center gap-3 position-relative" style="z-index:2"><div class="section-icon"><?=e($config['icono'])?></div><div><div class="small opacity-75 mb-1">GESTIÓN DE CONTENIDOS</div><h1><?=e($config['titulo'])?></h1><p><?=e($config['descripcion'])?></p></div></div></section>
<?php if($mensaje):?><div class="alert alert-success"><?=e($mensaje)?></div><?php endif?>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?>
<div class="row g-4 <?= $tabla==='reportajes'?'editor-ancho':'' ?>"><div class="<?= $tabla==='reportajes'?'col-12':'col-xl-4' ?>"><div class="card sticky-form"><div class="card-header bg-transparent border-bottom py-3 px-4"><div class="d-flex align-items-center gap-3"><span class="metric-icon soft-blue"><?= $edicion?'✏️':'＋' ?></span><div><div class="panel-title"><?= $edicion?'Editar':'Registrar' ?> <?=e($config['singular'])?></div><small class="text-muted"><?= $edicion?'Modifica los datos seleccionados':'Completa la información del formulario' ?></small></div></div></div><div class="card-body p-4">
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(tokenCsrf())?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?=e($edicion['id']??'')?>">
<?php foreach($config['campos'] as $nombre=>$campo): [$etiqueta,$tipo,$requerido]=$campo;if($tipo==='session'||(esRedactor()&&$nombre==='es_destacado'))continue;$valor=$edicion[$nombre]??'';?>
<div class="mb-3"><?php if($tipo==='checkbox'):?><div class="form-check form-switch rounded-3 p-3 ps-5"><input class="form-check-input" type="checkbox" role="switch" id="<?=e($nombre)?>" name="<?=e($nombre)?>" <?=!empty($valor)?'checked':''?>><label class="form-check-label fw-semibold" for="<?=e($nombre)?>"><?=e($etiqueta)?></label></div>
<?php else:?><label class="form-label" for="<?=e($nombre)?>"><?=e($etiqueta)?><?=$requerido?' <span class="text-danger">*</span>':''?></label>
<?php if($tabla==='reportajes' && $nombre==='desarrollo'):?><?php require __DIR__.'/partials/editor_reportajes.php'; ?>
<?php elseif($tipo==='textarea'):?><textarea class="form-control" rows="<?= $nombre==='desarrollo'?8:3 ?>" id="<?=e($nombre)?>" name="<?=e($nombre)?>" <?=$requerido?'required':''?>><?=e($valor)?></textarea>
<?php elseif($tipo==='foreign'): $opciones=$conexion->query($campo[3])->fetchAll();?><select class="form-select" id="<?=e($nombre)?>" name="<?=e($nombre)?>" <?=$requerido?'required':''?>><option value="">Seleccione...</option><?php foreach($opciones as $op):?><option value="<?=e($op['id'])?>" <?=((string)$valor===(string)$op['id'])?'selected':''?>><?=e($op['etiqueta'])?></option><?php endforeach?></select>
<?php elseif($tipo==='select'):?><select class="form-select" id="<?=e($nombre)?>" name="<?=e($nombre)?>" <?=$requerido?'required':''?>><?php foreach($campo[3] as $opValor=>$opTexto):if(esRedactor()&&$nombre==='estado'&&$opValor==='publicado')continue;?><option value="<?=e($opValor)?>" <?=((string)$valor===(string)$opValor||($valor===''&&$opValor==='borrador'))?'selected':''?>><?=e($opTexto)?></option><?php endforeach?></select><?php if($nombre==='estado'):?><small class="text-muted d-block mt-1"><?=esRedactor()?'Guarda como borrador o envíalo a revisión.':'Selecciona Publicado cuando el contenido esté aprobado.'?></small><?php endif?>
<?php elseif(str_starts_with($tipo,'file-')):?><input class="form-control" type="file" id="<?=e($nombre)?>" name="<?=e($nombre)?>" accept="<?=$tipo==='file-pdf'?'.pdf':'image/jpeg,image/png,image/webp'?>" <?=($requerido&&!$edicion)?'required':''?>><small class="text-muted d-block mt-1"><?=$tipo==='file-pdf'?'Solo PDF, máximo 10 MB':'JPG, PNG o WEBP, máximo 10 MB'?></small><?php if($valor):?><a class="file-preview" target="_blank" href="<?=e(rutaArchivo($valor))?>">👁 Ver archivo actual</a><?php endif?>
<?php else:?><input class="form-control" type="<?=e($tipo)?>" id="<?=e($nombre)?>" name="<?=e($nombre)?>" value="<?=e($valor)?>" <?=$requerido?'required':''?>><?php endif?><?php endif?></div><?php endforeach?>
<div class="d-flex gap-2 pt-2"><button class="btn btn-primary flex-grow-1" type="submit"><?= $edicion?'✓ Actualizar cambios':'✓ Guardar registro' ?></button><?php if($edicion):?><a class="btn btn-light" href="<?=e(basename($_SERVER['PHP_SELF']))?>">Cancelar</a><?php endif?></div></form></div></div></div>
<div class="<?= $tabla==='reportajes'?'col-12':'col-xl-8' ?>"><div class="card"><div class="card-header bg-transparent border-bottom py-3 px-4"><div class="d-flex justify-content-between align-items-center gap-3"><div><div class="panel-title">Registros guardados</div><small class="text-muted"><?=esRedactor()?'Aquí aparecen únicamente tus reportajes.':'Consulta, modifica o elimina la información de esta sección'?></small></div><span class="record-count"><?=count($registros)?> registro(s)</span></div></div><div class="mobile-table-help">Desliza la tabla hacia los lados para ver todas las columnas.</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-modern mb-0"><thead><tr><?php foreach($config['columnas'] as $titulo):?><th><?=e($titulo)?></th><?php endforeach?><th>Acciones</th></tr></thead><tbody>
<?php if(!$registros):?><tr><td colspan="<?=count($config['columnas'])+1?>"><div class="empty-state"><div class="empty-icon mb-2"><?=e($config['icono'])?></div><h5>Sin registros todavía</h5><p class="text-muted mb-0">Usa el formulario para agregar el primer registro.</p></div></td></tr><?php endif?>
<?php foreach($registros as $fila):?><tr data-fila><?php foreach($config['columnas'] as $nombre=>$titulo):$valor=$fila[$nombre]??'';?><td><?php if(in_array($nombre,['foto','foto_principal','foto_portada','url_foto'],true)&&$valor):?><img src="<?=e(rutaArchivo($valor))?>" alt="" style="width:70px;height:50px;object-fit:cover;border-radius:9px"><?php elseif(in_array($nombre,['pdf_adjunto','archivo_pdf'],true)&&$valor):?><a class="btn btn-sm btn-light text-primary" target="_blank" href="<?=e(rutaArchivo($valor))?>">📄 Ver PDF</a><?php elseif(in_array($nombre,['link_externo','url_embed'],true)&&$valor):?><a class="btn btn-sm btn-light text-primary" target="_blank" rel="noopener" href="<?=e($valor)?>">↗ Abrir</a><?php elseif($nombre==='estado'):?><?php $claseEstado=$valor==='publicado'?'success':($valor==='revision'?'warning text-dark':'secondary');?><span class="badge rounded-pill bg-<?=$claseEstado?>"><?=$valor==='publicado'?'Publicado':($valor==='revision'?'En revisión':'Borrador')?></span><?php elseif(in_array($nombre,['es_destacado','es_nickname'],true)):?><span class="badge rounded-pill bg-<?=$valor?'success':'secondary'?>"><?=$valor?'✓ Sí':'No'?></span><?php else:?><?=e(mb_strimwidth((string)$valor,0,90,'…'))?><?php endif?></td><?php endforeach?><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" title="Editar" href="?editar=<?=e($fila['id'])?>">✏️</a><?php if(!esRedactor()):?><form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este registro?')"><input type="hidden" name="csrf_token" value="<?=e(tokenCsrf())?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=e($fila['id'])?>"><button class="btn btn-sm btn-outline-danger" title="Eliminar">🗑️</button></form><?php endif?></td></tr><?php endforeach?></tbody></table></div></div></div></div></div>
<?php require __DIR__.'/partials/footer.php'; ?>
