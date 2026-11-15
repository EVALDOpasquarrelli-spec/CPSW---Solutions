<?php
declare(strict_types=1);
namespace App;
use PDO;

final class Auth {
 public function __construct(private PDO $db){}
 public function login(array $d):never {
  $email=strtolower(trim((string)($d['email']??'')));$pass=(string)($d['password']??'');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$pass==='')Http::json(['error'=>'Credenciais inválidas'],422);
  $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$key=hash('sha256',$email.'|'.$ip);
  $limit=$this->db->prepare('SELECT attempts,blocked_until FROM auth_rate_limits WHERE bucket_key=?');$limit->execute([$key]);$rate=$limit->fetch();
  if($rate&&$rate['blocked_until']&&strtotime((string)$rate['blocked_until'])>time())Http::json(['error'=>'Demasiadas tentativas. Aguarde 15 minutos antes de tentar novamente.'],429);
  $s=$this->db->prepare('SELECT id,name,email,phone,password_hash,role,status FROM users WHERE email=? LIMIT 1');$s->execute([$email]);$u=$s->fetch();
  if(!$u||$u['status']!=='active'||!password_verify($pass,$u['password_hash'])){
   $this->db->prepare("INSERT INTO auth_rate_limits(bucket_key,attempts,window_started_at,blocked_until) VALUES(?,1,NOW(),NULL) ON DUPLICATE KEY UPDATE blocked_until=IF(window_started_at < DATE_SUB(NOW(),INTERVAL 15 MINUTE),NULL,IF(attempts>=7,DATE_ADD(NOW(),INTERVAL 15 MINUTE),blocked_until)),attempts=IF(window_started_at < DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),window_started_at=IF(window_started_at < DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),window_started_at)")->execute([$key]);
   Http::json(['error'=>'E-mail ou palavra-passe incorrectos'],401);
  }
  $this->db->prepare('DELETE FROM auth_rate_limits WHERE bucket_key=?')->execute([$key]);
  session_regenerate_id(true);$touch=$this->db->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');$touch->execute([(int)$u['id']]);unset($u['password_hash']);$_SESSION['user']=$u;$_SESSION['csrf_token']??=bin2hex(random_bytes(32));Http::json(['user'=>$u,'csrf_token'=>$_SESSION['csrf_token']]);
 }
 public function logout():never {$_SESSION=[];session_destroy();Http::json(['ok'=>true]);}
}