<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
if(class_exists(\\Dotenv\\Dotenv::class)){\\Dotenv\\Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();}
use App\\Database;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$name=trim((string)($argv[1]??''));$email=strtolower(trim((string)($argv[2]??'')));$password=(string)($argv[3]??'');
if(strlen($name)<3||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Uso: php bin/create-admin.php \"Nome Admin\" admin@dominio.mz \"Senha-com-12-ou-mais\"".PHP_EOL);exit(1);}
$db=Database::connection();$s=$db->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,'admin','active')");$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);fwrite(STDOUT,"Administrador criado: {$email}".PHP_EOL);
