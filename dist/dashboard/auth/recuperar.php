<?php
declare(strict_types=1);
require_once __DIR__.'/../config/auth_base.php';
require_once __DIR__.'/../config/conexion.php';
$mensaje='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  authCheckCsrf();$email=trim(authText($_POST['email']??null));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Introduce un correo válido.');
  $ipOk=authLimit('reset-ip',$_SERVER['REMOTE_ADDR']??'',10,900);$emailOk=authLimit('reset-email',strtolower($email),3,900);
  $mensaje='Si la cuenta existe, se preparará un enlace de recuperación. Revisa tu correo. El enlace caduca en 20 minutos.';
  if($ipOk&&$emailOk){
   $q=$conexion->prepare('SELECT id,email,password_hash FROM usuarios WHERE email=?');$q->execute([$email]);$u=$q->fetch(PDO::FETCH_ASSOC);
   if($u){
    $recoveryToken=bin2hex(random_bytes(32));$key=hash('sha256',$recoveryToken);
    authState(function(&$s)use($key,$u){foreach(($s['resets']??[])as $k=>$r)if($r['expires']<time()||$r['id']==$u['id'])unset($s['resets'][$k]);$s['resets'][$key]=['version'=>2,'id'=>(int)$u['id'],'email'=>$u['email'],'fingerprint'=>hash('sha256',$u['password_hash']),'expires'=>time()+1200];});
    try{authMail($u['email'],"Solicitaste recuperar tu acceso. Abre este enlace antes de 20 minutos:\n".AUTH_BASE_URL.'/auth/restablecer.php?token='.$recoveryToken."\nSi no fuiste tú, ignora este mensaje.");}catch(Throwable $e){authState(function(&$s)use($key){unset($s['resets'][$key]);});error_log('Revista: no se pudo entregar el correo de recuperación.');}
   }
  }
 }catch(Throwable $e){$error='No se pudo procesar la solicitud. Comprueba el correo y vuelve a intentarlo.';}
}
$esRestablecer=false;
$titulo='Recuperar contraseña';require __DIR__.'/seguridad-vista.php';
