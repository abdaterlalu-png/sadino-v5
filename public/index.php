<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';

$design = published_design();
$gradientMap = [
 'contour-atlas'=>'linear-gradient(135deg,#0b3760,#1565a8 52%,#1e7a1a)',
 'prism-refract'=>'linear-gradient(125deg,#0b3760,#6d28d9 42%,#0891b2 70%,#1e7a1a)',
 'matrix-grid'=>'linear-gradient(135deg,#0f2740,#1565a8 58%,#0f766e)',
 'cubic-blocks'=>'linear-gradient(135deg,#111827,#1565a8 55%,#b45309)',
 'aurora-ribbon'=>'linear-gradient(135deg,#0d4a85,#6d28d9 46%,#2d7a27)',
 'matte-carbon'=>'linear-gradient(135deg,#090f1b,#182235 58%,#0d4a85)',
 'clean-silk'=>'linear-gradient(135deg,#0d4a85,#1565a8 55%,#2d7a27)',
 'circuit-board'=>'linear-gradient(135deg,#071e33,#0e7490 48%,#166534)',
 'polygon-luminary'=>'linear-gradient(135deg,#0d4a85,#4f46e5 48%,#b45309)',
 'wave-line'=>'linear-gradient(135deg,#0b3760,#0891b2 52%,#2d7a27)',
 'hex-mesh'=>'linear-gradient(135deg,#10253c,#1565a8 52%,#0f766e)',
 'shiny-overlay'=>'linear-gradient(135deg,#0d4a85,#2563eb 48%,#6d28d9)',
];
$loaderGradient=$gradientMap[$design['loadingPreset']??'prism-refract']??$gradientMap['prism-refract'];

if (isset($_GET['logout'])) { logout_user(); redirect('/?page=login'); }
$page = (string)($_GET['page'] ?? '');

if (!current_user()) {
    if ($page !== 'login') redirect('/?page=login');
    $error='';
    if (is_post()) {
        $token=(string)($_POST['csrf_token']??'');
        if(!hash_equals(csrf_token(),$token))$error='Sesi login kedaluwarsa. Muat ulang halaman.';
        else {[$ok,$msg]=attempt_login((string)($_POST['username']??''),(string)($_POST['password']??''));if($ok)redirect('/?page=dashboard');$error=$msg;}
    }
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login SADINO V5</title><link rel="stylesheet" href="/assets/css/app.css"></head>
    <body data-dashboard-preset="<?=e($design['loginPreset']??'clean-silk')?>" data-motion="<?=e($design['motion']??'balanced')?>">
    <div id="loader" data-preset="<?=e($design['loadingPreset']??'prism-refract')?>" style="--loader-bg:<?=e($loaderGradient)?>"><div class="loader-ring"></div><div class="loader-title">SADINO V5</div><div class="loader-sub">FINANCIAL AGENT</div><div class="loader-credit">created by aliplovesrawon</div></div><script>setTimeout(()=>document.getElementById('loader')?.classList.add('hide'),2000);</script>
    <main class="login-shell"><section class="login-card"><div class="login-hero"><img class="login-logo" src="/assets/img/dnd-java.png"><h1>SADINO V5</h1><p>Financial Intelligence Agent DND JAVA. Database realtime, monthly Excel workflow, role access, audit trail, dan one-click VPS deployment.</p></div><div class="login-form"><h2>Secure Access</h2><p class="hint">Masuk menggunakan akun Creator, Finance Manager, Accountant, atau Director.</p><?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="field"><label>Username</label><input name="username" autocomplete="username" required autofocus></div><div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary" style="width:100%">Login ke SADINO</button></form><p class="tiny" style="margin-top:18px">PT DND JAVA Indonesia · Bojonegoro</p></div></section></main></body></html><?php exit;
}
$user=require_login();
if ($page === 'login' || $page === '') redirect('/?page=dashboard');
if((int)$user['force_password_change']===1){
    $error='';
    if(is_post()&&isset($_POST['new_password'])){
        if(!hash_equals(csrf_token(),(string)($_POST['csrf_token']??'')))$error='Sesi formulir kedaluwarsa.';
        elseif(strlen((string)$_POST['new_password'])<12)$error='Password minimal 12 karakter.';
        elseif((string)$_POST['new_password']!==(string)($_POST['confirm_password']??''))$error='Konfirmasi password tidak sama.';
        else{db()->prepare('UPDATE users SET password_hash=?,force_password_change=0,updated_at=NOW() WHERE id=?')->execute([password_hash((string)$_POST['new_password'],PASSWORD_DEFAULT),(int)$user['id']]);audit('password.initial_change','user',(string)$user['id']);redirect('/?page=dashboard');}
    }
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ganti Password SADINO</title><link rel="stylesheet" href="/assets/css/app.css"></head><body data-dashboard-preset="clean-silk"><main class="login-shell"><section class="login-card" style="grid-template-columns:1fr"><div class="login-form"><h2>Ganti Password Awal</h2><p class="hint">Untuk keamanan, akun wajib mengganti password sebelum menggunakan SADINO.</p><?php if($error):?><div class="alert alert-error"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="field"><label>Password Baru</label><input type="password" name="new_password" required minlength="12"></div><div class="field"><label>Konfirmasi</label><input type="password" name="confirm_password" required minlength="12"></div><button class="btn btn-primary">Simpan Password</button></form></div></section></main></body></html><?php exit;
}
$years=available_years();$year=(int)($_GET['year']??$years[0]);$data=dashboard_data($year);
$bootstrap=[
 'user'=>user_public($user),'csrf'=>csrf_token(),'data'=>$data,'years'=>$years,'design'=>$design,'presets'=>design_presets(),
 'uploads'=>can('import.upload',$user)?upload_batches():[],
 'users'=>can('users.manage',$user)?list_users():[],
 'audit'=>can('audit.view',$user)?list_audit():[],
 'designVersions'=>can('design.manage',$user)?design_versions():[],
 'appVersion'=>app_version(),'schemaVersion'=>get_setting('schema_version','unknown')
];
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"><title>SADINO V5 — Financial Agent DND JAVA</title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body data-dashboard-preset="<?=e($design['dashboardPreset']??'contour-atlas')?>" data-motion="<?=e($design['motion']??'balanced')?>" data-table-contrast="<?=e($design['tableContrast']??'balanced')?>">
<div id="loader" data-preset="<?=e($design['loadingPreset']??'prism-refract')?>" style="--loader-bg:<?=e($loaderGradient)?>"><div class="loader-ring"></div><div class="loader-title">SADINO V5</div><div class="loader-sub">FINANCIAL INTELLIGENCE AGENT</div><div class="loader-credit">created by aliplovesrawon</div></div><script>setTimeout(()=>document.getElementById('loader')?.classList.add('hide'),2000);</script>
<div class="overlay" id="overlay" onclick="toggleMenu(false)"></div><div class="app"><aside class="side" id="side"><img class="logo" src="/assets/img/dnd-java.png"><div class="agent"><b>SADINO V5</b><small>Financial Agent · <?=e(role_label($user['role']))?></small><small>created by aliplovesrawon</small></div><nav class="nav" id="nav"></nav><div class="foot">PT DND JAVA INDONESIA<br>V<?=e(app_version())?> · Docker Manager Native</div></aside><main class="main"><header><button class="menu" onclick="toggleMenu()">☰</button><div class="ht"><h1 id="pageTitle">Dashboard</h1><p>Realtime shared database · monthly Excel workflow · audited role access</p></div><div class="header-actions"><select onchange="setYear(this.value)" aria-label="Tahun"><?php foreach($years as$y):?><option value="<?=$y?>" <?=$y===$year?'selected':''?>>FY<?=$y?></option><?php endforeach;?></select><div class="user-chip"><div class="avatar"><?=e(strtoupper(substr($user['name'],0,1)))?></div><span><b style="font-size:11px"><?=e($user['name'])?></b><small style="display:block;font-size:9px;color:#64748b"><?=e(role_label($user['role']))?></small></span></div><a class="btn btn-ghost" href="/?logout=1">Logout</a></div></header><section class="content" id="app"></section></main></div>
<div class="modal-backdrop" id="modalBackdrop" onclick="if(event.target===this)closeModal()"><div class="modal" id="modal"></div></div><div class="toast-wrap" id="toastWrap"></div>
<script>window.SADINO_BOOTSTRAP=<?=json_encode($bootstrap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="/assets/js/xlsx.full.min.js"></script><script src="/assets/js/app.js"></script></body></html>
