<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$name=getenv('CREATOR_NAME')?:'System Creator';$username=getenv('CREATOR_USERNAME')?:'creator';$password=getenv('CREATOR_PASSWORD')?:'';$email=getenv('CREATOR_EMAIL')?:'';
if(strlen($password)<12){fwrite(STDERR,"CREATOR_PASSWORD minimal 12 karakter.\n");exit(1);} 
$count=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='creator'")->fetchColumn();
if($count>0){echo"Creator sudah tersedia.\n";exit(0);} 
$st=db()->prepare("INSERT INTO users(name,username,email,password_hash,role,permission_overrides,is_active,force_password_change,created_at) VALUES(?,?,?,?, 'creator','{}',1,1,NOW())");
$st->execute([$name,$username,$email,password_hash($password,PASSWORD_DEFAULT)]);echo"Creator berhasil dibuat: $username\n";
