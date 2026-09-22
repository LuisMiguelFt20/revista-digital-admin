<?php
declare(strict_types=1);

function erFotos(PDO $db, int $id): array {
    $q=$db->prepare('SELECT id,url_foto,descripcion,orden FROM reportajes_fotos WHERE reportaje_id=? ORDER BY orden,id');
    $q->execute([$id]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function erFirma(array $registro, array $fotos): string {
    return hash('sha256', json_encode([$registro,$fotos], JSON_UNESCAPED_UNICODE));
}
function erBloques(string $texto, array $fotos): array {
    $porOrden=[]; $usadas=[]; $salida=[];
    foreach($fotos as $foto) $porOrden[(int)$foto['orden']][]=$foto;
    $agregar=function(array $foto) use (&$salida,&$usadas) {
        if(isset($usadas[$foto['id']])) return;
        $usadas[$foto['id']]=true;
        $salida[]=['tipo'=>'foto','id'=>(int)$foto['id'],'descripcion'=>$foto['descripcion']??'','url'=>rutaArchivo($foto['url_foto'])];
    };
    foreach(preg_split('/(\[foto:[1-9][0-9]*\])/i',$texto,-1,PREG_SPLIT_DELIM_CAPTURE) as $parte) {
        if(preg_match('/^\[foto:([1-9][0-9]*)\]$/i',$parte,$m)) {
            foreach($porOrden[(int)$m[1]]??[] as $foto) $agregar($foto);
        } elseif(trim($parte)!=='') $salida[]=['tipo'=>'texto','texto'=>trim($parte)];
    }
    foreach($fotos as $foto) $agregar($foto);
    return $salida?:[['tipo'=>'texto','texto'=>'']];
}
function erSubir(string $campo, string $carpeta, bool $pdf, array &$nuevos, ?string $previo=null): ?string {
    $f=$_FILES[$campo]??null;
    if(!$f || $f['error']===UPLOAD_ERR_NO_FILE) return $previo;
    if(!is_scalar($f['error']) || $f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Una imagen o archivo no llegó completo. Selecciónalo otra vez.');
    if($f['size']>10*1024*1024) throw new RuntimeException('Cada archivo debe pesar como máximo 10 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if($pdf ? $mime!=='application/pdf' : !in_array($mime,['image/jpeg','image/png','image/webp'],true)) throw new RuntimeException('Formato de archivo incorrecto. Usa JPG, PNG, WEBP o PDF en el campo correspondiente.');
    if(!$pdf && !@getimagesize($f['tmp_name'])) throw new RuntimeException('La imagen no es válida.');
    $ruta=guardarArchivo($campo,$carpeta,$pdf?['pdf']:['jpg','jpeg','png','webp'],$previo);
    if($ruta && $ruta!==$previo) $nuevos[]=$ruta;
    return $ruta;
}
function erGuardar(PDO $db): int {
    validarCsrf();
    authReport($db,(int)($_POST['id']??0));
    if(($_POST['editor_listo']??'')!=='1') throw new RuntimeException('Espera a que cargue el editor antes de guardar.');
    $bloques=json_decode((string)($_POST['bloques_json']??''),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($bloques)||!array_is_list($bloques)||count($bloques)>100) throw new RuntimeException('El reportaje admite hasta 100 bloques.');
    $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT)?:0;
    if($id<0) throw new RuntimeException('Reportaje inválido.');
    $titulo=trim((string)($_POST['titulo']??'')); $resumen=trim((string)($_POST['resumen_corto']??''));
    $autor=filter_input(INPUT_POST,'autor_id',FILTER_VALIDATE_INT); $fecha=(string)($_POST['fecha_publicacion']??'');
    $estado=(string)($_POST['estado']??'borrador');
    $estadosPermitidos=esRedactor()?['borrador','revision']:['borrador','revision','publicado'];
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
    if($titulo===''||mb_strlen($titulo)>255||mb_strlen($resumen)>500) throw new RuntimeException('Completa el título (máximo 255 caracteres) y revisa el resumen (máximo 500).');
    if(!$autor||!$d||$d->format('Y-m-d')!==$fecha) throw new RuntimeException('Selecciona un autor y una fecha válida.');
    if(!in_array($estado,$estadosPermitidos,true)) throw new RuntimeException('No tienes permiso para seleccionar ese estado editorial.');
    $textoExiste=false; $cantidadFotos=0;
    foreach($bloques as $b) {
        if(!is_array($b)) throw new RuntimeException('Bloque inválido.');
        if(($b['tipo']??'')==='texto') {
            $texto=trim((string)($b['texto']??''));
            if(preg_match('/\[foto:[1-9][0-9]*\]/i',$texto)) throw new RuntimeException('Usa Agregar imagen para insertar fotografías.');
            $textoExiste=$textoExiste||$texto!=='';
        } elseif(($b['tipo']??'')==='foto') {
            $cantidadFotos++;
            if(mb_strlen((string)($b['descripcion']??''))>255) throw new RuntimeException('La descripción de cada foto admite hasta 255 caracteres.');
        } else throw new RuntimeException('Tipo de bloque inválido.');
    }
    if(!$textoExiste) throw new RuntimeException('Escribe al menos un bloque de texto.');
    if($cantidadFotos>18) throw new RuntimeException('Puedes incluir hasta 18 fotos por reportaje en este editor.');
    $nuevos=[]; $borrar=[];
    try {
        $db->beginTransaction(); $anterior=[]; $fotos=[];
        if($id) {
            $q=$db->prepare('SELECT * FROM reportajes WHERE id=? FOR UPDATE'); $q->execute([$id]); $anterior=$q->fetch(PDO::FETCH_ASSOC);
            if(!$anterior) throw new RuntimeException('El reportaje ya no existe.');
            $fotos=erFotos($db,$id);
            if(!hash_equals(erFirma($anterior,$fotos),(string)($_POST['editor_firma']??''))) throw new RuntimeException('Este reportaje cambió en otra ventana. Copia tu texto antes de recargar para revisar la versión actual.');
        }
        $porId=[]; foreach($fotos as $f) $porId[(int)$f['id']]=$f;
        $partes=[]; $guardadas=[]; $idsUsados=[]; $orden=0;
        foreach($bloques as $b) {
            if($b['tipo']==='texto') { if(trim((string)$b['texto'])!=='') $partes[]=trim((string)$b['texto']); continue; }
            $fotoId=(int)($b['id']??0); $previa=null;
            if($fotoId) {
                if(!isset($porId[$fotoId])||isset($idsUsados[$fotoId])) throw new RuntimeException('La fotografía no pertenece a este reportaje o está repetida.');
                $previa=$porId[$fotoId]['url_foto']; $idsUsados[$fotoId]=true;
            }
            $clave=(string)($b['clave']??'');
            if(!preg_match('/^b[0-9]+$/',$clave)) throw new RuntimeException('Identificador de fotografía inválido.');
            $ruta=erSubir('foto_'.$clave,'reportajes_fotos',false,$nuevos,$previa);
            if(!$ruta) throw new RuntimeException('Selecciona una imagen en cada bloque de fotografía.');
            if($previa && $previa!==$ruta) $borrar[]=$previa;
            $orden++; $partes[]='[foto:'.$orden.']';
            $guardadas[]=['id'=>$fotoId,'ruta'=>$ruta,'orden'=>$orden,'descripcion'=>trim((string)($b['descripcion']??''))];
        }
        $principal=erSubir('foto_principal','reportajes',false,$nuevos,$anterior['foto_principal']??null);
        $pdf=erSubir('pdf_adjunto','reportajes_pdf',true,$nuevos,$anterior['pdf_adjunto']??null);
        foreach(['foto_principal'=>$principal,'pdf_adjunto'=>$pdf] as $campo=>$ruta) if(!empty($anterior[$campo])&&$anterior[$campo]!==$ruta) $borrar[]=$anterior[$campo];
        $owner=(int)$_SESSION['usuario_id'];$previousFeatured=0;if($id){$q=$db->prepare('SELECT usuario_id,es_destacado FROM reportajes WHERE id=?');$q->execute([$id]);$original=$q->fetch();$owner=(int)$original['usuario_id'];$previousFeatured=(int)$original['es_destacado'];}
        $destacado=esRedactor()?0:(isset($_POST['es_destacado'])?1:0);
        if($estado!=='publicado') $destacado=0;
        if($destacado&&!esRedactor()) $db->exec('UPDATE reportajes SET es_destacado=0');
        $valores=[$titulo,$resumen?:null,implode("\n\n",$partes),$principal,$pdf,$fecha,$estado,$destacado,$autor,$owner];
        if($id) {
            $valores[]=$id; $db->prepare('UPDATE reportajes SET titulo=?,resumen_corto=?,desarrollo=?,foto_principal=?,pdf_adjunto=?,fecha_publicacion=?,estado=?,es_destacado=?,autor_id=?,usuario_id=? WHERE id=?')->execute($valores);
        } else {
            $db->prepare('INSERT INTO reportajes(titulo,resumen_corto,desarrollo,foto_principal,pdf_adjunto,fecha_publicacion,estado,es_destacado,autor_id,usuario_id) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($valores);
            $id=(int)$db->lastInsertId();
        }
        foreach($guardadas as $g) {
            if($g['id']) $db->prepare('UPDATE reportajes_fotos SET url_foto=?,descripcion=?,orden=? WHERE id=? AND reportaje_id=?')->execute([$g['ruta'],$g['descripcion'],$g['orden'],$g['id'],$id]);
            else $db->prepare('INSERT INTO reportajes_fotos(reportaje_id,url_foto,descripcion,orden) VALUES(?,?,?,?)')->execute([$id,$g['ruta'],$g['descripcion'],$g['orden']]);
        }
        foreach($fotos as $f) if(!isset($idsUsados[(int)$f['id']])) {
            $db->prepare('DELETE FROM reportajes_fotos WHERE id=? AND reportaje_id=?')->execute([$f['id'],$id]); $borrar[]=$f['url_foto'];
        }
        $db->commit();
    } catch(Throwable $e) {
        if($db->inTransaction()) $db->rollBack();
        foreach($nuevos as $ruta) borrarArchivo($ruta);
        throw $e;
    }
    foreach(array_unique($borrar) as $ruta) borrarArchivo($ruta);
    return $id;
}
