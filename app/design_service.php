<?php
declare(strict_types=1);

function design_presets(): array
{
    return [
        ['id'=>'contour-atlas','name'=>'Contour Atlas','desc'=>'Kontur topografi Jawa yang halus.'],
        ['id'=>'prism-refract','name'=>'Prism Refract','desc'=>'Bidang cahaya prisma futuristik.'],
        ['id'=>'matrix-grid','name'=>'Matrix Grid','desc'=>'Grid presisi dan titik data.'],
        ['id'=>'cubic-blocks','name'=>'Cubic Blocks','desc'=>'Blok isometrik berlapis.'],
        ['id'=>'aurora-ribbon','name'=>'Aurora Ribbon','desc'=>'Pita cahaya lembut.'],
        ['id'=>'matte-carbon','name'=>'Matte Carbon','desc'=>'Karbon matte gelap elegan.'],
        ['id'=>'clean-silk','name'=>'Clean Silk','desc'=>'Putih bersih dengan kilau sutra.'],
        ['id'=>'circuit-board','name'=>'Circuit Board','desc'=>'Jejak sirkuit minimal.'],
        ['id'=>'polygon-luminary','name'=>'Polygon Luminary','desc'=>'Poligon berpendar lembut.'],
        ['id'=>'wave-line','name'=>'Wave Line','desc'=>'Gelombang garis modern.'],
        ['id'=>'hex-mesh','name'=>'Hex Mesh','desc'=>'Mesh hexagonal teknis.'],
        ['id'=>'shiny-overlay','name'=>'Shiny Overlay','desc'=>'Overlay reflektif premium.'],
    ];
}

function default_design_config(): array
{
    return [
        'loadingPreset'=>'prism-refract','loginPreset'=>'clean-silk','dashboardPreset'=>'contour-atlas',
        'motion'=>'balanced','zoomCap'=>1.04,'tableContrast'=>'balanced','publishedVersion'=>0
    ];
}

function published_design(): array
{
    $stmt=db()->query("SELECT * FROM design_versions WHERE status='published' ORDER BY id DESC LIMIT 1");
    $row=$stmt->fetch();
    if(!$row)return default_design_config();
    $cfg=json_decode((string)$row['config_json'],true);if(!is_array($cfg))$cfg=default_design_config();
    $cfg['publishedVersion']=(int)$row['id'];return$cfg;
}

function design_versions(int$limit=30):array
{
    $stmt=db()->prepare('SELECT d.id,d.status,d.config_json,d.created_at,d.published_at,u.name creator_name FROM design_versions d LEFT JOIN users u ON u.id=d.created_by ORDER BY d.id DESC LIMIT ?');
    $stmt->bindValue(1,$limit,PDO::PARAM_INT);$stmt->execute();$rows=$stmt->fetchAll();
    foreach($rows as&$r)$r['config']=json_decode((string)$r['config_json'],true);return$rows;
}

function validate_design_config(array$c):array
{
    $ids=array_column(design_presets(),'id');$base=default_design_config();
    foreach(['loadingPreset','loginPreset','dashboardPreset']as$k)if(!in_array($c[$k]??'', $ids,true))$c[$k]=$base[$k];
    if(!in_array($c['motion']??'', ['off','subtle','balanced','immersive'],true))$c['motion']='balanced';
    $c['zoomCap']=max(1,min(1.12,(float)($c['zoomCap']??1.04)));
    if(!in_array($c['tableContrast']??'', ['soft','balanced','strong'],true))$c['tableContrast']='balanced';
    return$c;
}

function save_design(array$config,string$status='draft'):array
{
    require_permission('design.manage');$config=validate_design_config($config);$u=current_user();
    $st=db()->prepare('INSERT INTO design_versions (status,config_json,created_by,created_at) VALUES (?,?,?,NOW())');
    $st->execute([$status,json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$u['id']]);$id=(int)db()->lastInsertId();
    audit('design.save_'.$status,'design_version',(string)$id,$config);
    return['ok'=>true,'message'=>$status==='draft'?'Draft desain tersimpan.':'Desain tersimpan.','id'=>$id];
}

function publish_design(array$config):array
{
    require_permission('design.manage');$pdo=db();$pdo->beginTransaction();
    try{$pdo->exec("UPDATE design_versions SET status='archived' WHERE status='published'");$u=current_user();$config=validate_design_config($config);
        $st=$pdo->prepare("INSERT INTO design_versions (status,config_json,created_by,created_at,published_at) VALUES ('published',?,?,NOW(),NOW())");
        $st->execute([json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$u['id']]);$id=(int)$pdo->lastInsertId();$pdo->commit();audit('design.publish','design_version',(string)$id,$config);return['ok'=>true,'message'=>'Desain berhasil dipublish.','id'=>$id];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();return['ok'=>false,'message'=>$e->getMessage()];}
}

function rollback_design(int$id):array
{
    require_permission('design.manage');$st=db()->prepare('SELECT config_json FROM design_versions WHERE id=?');$st->execute([$id]);$json=$st->fetchColumn();
    if($json===false)return['ok'=>false,'message'=>'Versi desain tidak ditemukan.'];$cfg=json_decode((string)$json,true);if(!is_array($cfg))return['ok'=>false,'message'=>'Konfigurasi desain rusak.'];
    $r=publish_design($cfg);if($r['ok'])audit('design.rollback','design_version',(string)$id);return$r;
}
