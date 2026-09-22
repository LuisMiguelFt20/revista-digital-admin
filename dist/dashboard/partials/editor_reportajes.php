<?php
$editorFotos=erFotos($conexion,(int)($edicion['id']??0));
$editorBloques=erBloques((string)($edicion['desarrollo']??''),$editorFotos);
$editorFirma=$edicion?erFirma($edicion,$editorFotos):'';
if($editorFallo??false) {
    $recuperados=json_decode((string)($_POST['bloques_json']??''),true);
    if(is_array($recuperados)&&array_is_list($recuperados)&&count($recuperados)<=100) {
        $editorBloques=[]; $urls=[];
        foreach($editorFotos as $f) $urls[(int)$f['id']]=rutaArchivo($f['url_foto']);
        foreach($recuperados as $b) {
            if(!is_array($b)) continue;
            if(($b['tipo']??'')==='texto') $editorBloques[]=['tipo'=>'texto','texto'=>(string)($b['texto']??'')];
            elseif(($b['tipo']??'')==='foto') $editorBloques[]=['tipo'=>'foto','id'=>(int)($b['id']??0),'descripcion'=>(string)($b['descripcion']??''),'url'=>$urls[(int)($b['id']??0)]??''];
        }
    }
    $editorFirma=(string)($_POST['editor_firma']??'');
}
?>
<input type="hidden" name="editor_listo" id="editor-listo" value="0">
<input type="hidden" name="editor_firma" value="<?=e($editorFirma)?>">
<input type="hidden" name="bloques_json" id="bloques-json">
<div id="editor-reportajes">
    <p class="text-muted">Escribe el contenido y agrega las imágenes en el lugar que quieras. Usa Subir y Bajar para ordenarlas.</p>
    <?php if($editorFallo??false):?><div class="alert alert-warning">Se conservó el texto. Vuelve a seleccionar los archivos nuevos antes de guardar.</div><?php endif?>
    <noscript><div class="alert alert-danger">Activa JavaScript para usar este editor.</div></noscript>
    <div id="editor-bloques" aria-label="Contenido del reportaje"></div>
    <div class="d-flex gap-2 flex-wrap my-3">
        <button type="button" id="agregar-texto" class="btn btn-outline-primary">＋ Agregar texto</button>
        <button type="button" id="agregar-foto" class="btn btn-outline-primary">＋ Agregar imagen</button>
    </div>
    <p class="small text-muted">Las imágenes quitadas se eliminarán del reportaje al guardar. Puedes deshacer antes de guardar.</p>
    <button type="button" id="editor-deshacer" class="btn btn-sm btn-outline-secondary" disabled>Deshacer eliminación</button>
    <p id="editor-estado" role="status" class="small mt-2"></p>
</div>
<script type="application/json" id="editor-datos"><?=json_encode($editorBloques,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<style>
#editor-reportajes .eb-bloque{border:1px solid var(--bs-border-color,#dfe3ee);border-radius:12px;padding:16px;margin:16px 0;background:var(--bs-body-bg,transparent)}
#editor-reportajes .eb-barra{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}
#editor-reportajes .eb-botones{display:flex;gap:6px;flex-wrap:wrap}
#editor-reportajes img{max-width:100%;max-height:380px;object-fit:contain;display:block;margin:12px auto;border-radius:8px}
#editor-reportajes textarea{min-height:160px;resize:vertical;line-height:1.6}
#editor-reportajes label{display:block;margin-top:12px;margin-bottom:6px}
.editor-ancho .sticky-form{position:static!important;max-height:none!important}
</style>
<script src="editor-reportajes.js"></script>
