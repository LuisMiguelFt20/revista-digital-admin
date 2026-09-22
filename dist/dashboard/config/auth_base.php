<?php
declare(strict_types=1);
// No se modifica el esquema del docente. Estado privado fuera de htdocs.
const AUTH_BASE_URL = 'http://localhost/revista-digital-admin/dist/dashboard';
const AUTH_MAIL_MODE = 'mail'; // local: prueba en este equipo; mail: requiere PHP mail configurado.
function authText($value): string { return is_string($value) ? $value : ''; }
function authStart(): void {
 if(session_status()!==PHP_SESSION_ACTIVE){
  ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
  session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);session_start();
 }
 header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
}
function authDir(): string {
 $root=realpath($_SERVER['DOCUMENT_ROOT']??'');
 if(!$root || $root===DIRECTORY_SEPARATOR) throw new RuntimeException('Configura DOCUMENT_ROOT.');
 $dir=dirname($root).'/revista-seguridad';
 if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('No se pudo crear almacenamiento privado.');
 return $dir;
}
function authState(callable $fn) {
 $f=fopen(authDir().'/estado.json','c+');if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Almacenamiento no disponible.');
 try{$raw=stream_get_contents($f);$data=$raw===''?[]:json_decode($raw,true,512,JSON_THROW_ON_ERROR);$result=$fn($data);rewind($f);ftruncate($f,0);fwrite($f,json_encode($data,JSON_THROW_ON_ERROR));fflush($f);return $result;}finally{flock($f,LOCK_UN);fclose($f);}
}
function authLimit(string $scope,string $key,int $limit,int $seconds): bool {
 return authState(function(&$s)use($scope,$key,$limit,$seconds){
  foreach(($s['limits']??[])as $k=>$v)if($v['until']<time())unset($s['limits'][$k]);
  $k=hash('sha256',$scope.$key);$v=$s['limits'][$k]??['until'=>time()+$seconds,'n'=>0];
  $v['n']++;$s['limits'][$k]=$v;return $v['n']<=$limit;
 });
}
function authCsrf(): string { return $_SESSION['auth_csrf']??=bin2hex(random_bytes(32)); }
function authCheckCsrf(): void {if(!hash_equals(authCsrf(),authText($_POST['csrf_token']??null)))throw new RuntimeException('Solicitud inválida. Recarga la página.');}
function authPassword(string $p): void {if(strlen($p)<12||strlen($p)>72||str_contains($p,"\0"))throw new RuntimeException('Usa una contraseña de 12 a 72 bytes, sin caracteres nulos.');}
function authMail(string $email,string $body): void {
 if(AUTH_MAIL_MODE==='local'){
  if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true))throw new RuntimeException('El modo de prueba solo funciona en localhost.');
  $file=authDir().'/correo-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.txt';
  if(file_put_contents($file,"Para: $email\nAsunto: Recuperación de acceso\n\n$body",LOCK_EX)===false)throw new RuntimeException('No se pudo preparar el correo.');
 }elseif(!mail($email,'Recuperacion de acceso - Revista Digital',$body,"Content-Type: text/plain; charset=UTF-8\r\n"))throw new RuntimeException('Correo no disponible.');
}
authStart();
