<?php
declare(strict_types=1);
require_once __DIR__.'/config/seguridad.php';
require_once __DIR__.'/config/conexion.php';
require_once __DIR__.'/config/utilidades.php';
exigirAdmin();

$tablas=['reportajes','noticias','boletines','podcasts','videos'];
$mensaje='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        validarCsrf();
        $base=(string)$conexion->query('SELECT DATABASE()')->fetchColumn();
        foreach($tablas as $tabla){
            $q=$conexion->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
            $q->execute([$base,$tabla,'estado']);
            if(!(int)$q->fetchColumn()) $conexion->exec("ALTER TABLE `$tabla` ADD COLUMN `estado` VARCHAR(20) NOT NULL DEFAULT 'publicado' AFTER `fecha_publicacion`");
            $conexion->exec("UPDATE `$tabla` SET estado='publicado' WHERE estado IS NULL OR estado NOT IN ('borrador','revision','publicado')");
        }
        $mensaje='Estados editoriales instalados correctamente. El contenido anterior continúa como publicado.';
    }catch(Throwable $e){$error='No se pudo instalar: '.$e->getMessage();}
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar estados editoriales</title><link rel="stylesheet" href="../assets/css/core/libs.min.css"><link rel="stylesheet" href="../assets/css/hope-ui.min.css?v=4.0.0"><style>body{background:#f4f6fb}.instalador{max-width:680px;margin:7vh auto;padding:20px}.card{border:0;border-radius:18px;box-shadow:0 12px 35px rgba(30,45,90,.09)}.paso{background:#eef2ff;border-radius:12px;padding:14px 16px}</style></head><body><main class="instalador"><div class="card"><div class="card-body p-4 p-md-5"><span class="badge bg-primary mb-3">CONFIGURACIÓN ÚNICA</span><h1 class="h3">Estados editoriales</h1><p class="text-muted">Agrega Borrador, En revisión y Publicado a reportajes, noticias, boletines, pódcasts y videos.</p><?php if($mensaje):?><div class="alert alert-success"><?=e($mensaje)?></div><a class="btn btn-primary" href="index.php">Ir al panel</a><?php else:?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?><div class="paso mb-4"><strong>Es seguro ejecutarlo una vez.</strong><br><small>Los registros existentes quedarán con estado Publicado.</small></div><form method="post"><input type="hidden" name="csrf_token" value="<?=e(tokenCsrf())?>"><button class="btn btn-primary w-100" type="submit">Instalar estados editoriales</button></form><?php endif?></div></div></main></body></html>
