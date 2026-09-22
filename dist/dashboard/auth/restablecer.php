<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth_base.php';
require_once __DIR__.'/../config/conexion.php';
$mensaje='';$error='';$completado=false;
$token=authText($_POST['token']??$_GET['token']??null);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  authCheckCsrf();if(!authLimit('reset-use',$_SERVER['REMOTE_ADDR']??'',20,900))throw new RuntimeException('Espera 15 minutos antes de reintentar.');
  $p=authText($_POST['password']??null);authPassword($p);
  if($p!==authText($_POST['confirmacion']??null))throw new RuntimeException('Las contraseñas no coinciden.');
  if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Enlace inválido.');
  $key=hash('sha256',$token);
  $email=authState(function(&$s)use($conexion,$key,$p){
   $r=$s['resets'][$key]??null;
   if(!$r||($r['version']??0)!==2||$r['expires']<time())throw new RuntimeException('El enlace expiró o ya fue utilizado. Solicita uno nuevo.');
   $q=$conexion->prepare('SELECT password_hash,email FROM usuarios WHERE id=?');$q->execute([$r['id']]);$u=$q->fetch(PDO::FETCH_ASSOC);
   if(!$u||$u['email']!==$r['email']||!hash_equals($r['fingerprint'],hash('sha256',$u['password_hash'])))throw new RuntimeException('El enlace ya no es válido.');
   $q=$conexion->prepare('UPDATE usuarios SET password_hash=? WHERE id=? AND password_hash=? AND email=?');$q->execute([password_hash($p,PASSWORD_DEFAULT),$r['id'],$u['password_hash'],$r['email']]);
   if($q->rowCount()!==1)throw new RuntimeException('La cuenta cambió. Solicita un nuevo enlace.');
   foreach($s['resets']as $k=>$v)if($v['id']===$r['id'])unset($s['resets'][$k]);return $u['email'];
  });
  $_SESSION=[];session_regenerate_id(true);$mensaje='Contraseña actualizada. Inicia sesión con tu nueva contraseña.';$completado=true;
  try{authMail($email,'Tu contraseña de Revista Digital fue actualizada. Si no reconoces el cambio, contacta al administrador.');}catch(Throwable $e){error_log('Revista: aviso de cambio no entregado.');}
 }catch(Throwable $e){$error=$e instanceof PDOException?'No se pudo actualizar la contraseña.':$e->getMessage();}
}
$esRestablecer=true;
$titulo='Nueva contraseña';require __DIR__.'/seguridad-vista.php';
