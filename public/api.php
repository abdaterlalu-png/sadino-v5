<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
$user=require_login();
$action=(string)($_GET['action']??'');
$method=(string)($_SERVER['REQUEST_METHOD']??'GET');
$input=[];
if($action==='dashboard'){
    if($method!=='GET') json_response(['ok'=>false,'message'=>'Method tidak diizinkan.'],405);
}else{
    if($method!=='POST') json_response(['ok'=>false,'message'=>'Method tidak diizinkan.'],405);
    verify_csrf((string)($_SERVER['HTTP_X_CSRF_TOKEN']??''));
    $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))$input=[];
}
try{
    switch($action){
        case 'dashboard': require_permission('dashboard.view');$year=(int)($_GET['year']??date('Y'));json_response(['ok'=>true,'data'=>dashboard_data($year)]);
        case 'import_stage': require_permission('import.upload');json_response(stage_import((array)($input['payload']??[]),(string)($input['filename']??'upload.xlsx'),(string)($input['hash']??''),(bool)($input['publish']??false)));
        case 'import_publish': json_response(publish_import((int)($input['id']??0)));
        case 'import_rollback': json_response(rollback_import((int)($input['id']??0),(string)($input['confirm_password']??'')));
        case 'design_draft': json_response(save_design((array)($input['config']??[]),'draft'));
        case 'design_publish': json_response(publish_design((array)($input['config']??[])));
        case 'design_rollback': json_response(rollback_design((int)($input['id']??0)));
        case 'user_save': require_permission('users.manage');json_response(api_user_save($input));
        case 'user_delete': require_permission('users.manage');json_response(api_user_delete((int)($input['id']??0),(string)($input['confirm_password']??'')));
        case 'user_revoke_sessions': require_permission('users.manage');json_response(api_user_revoke_sessions((int)($input['id']??0),(string)($input['confirm_password']??'')));
        case 'creator_bulk_replace': require_permission('creator.data_lab');json_response(api_creator_bulk_replace($input));
        default: json_response(['ok'=>false,'message'=>'Action tidak dikenal.'],404);
    }
}catch(Throwable$e){json_response(['ok'=>false,'message'=>(string)env('APP_ENV','production')==='production'?'Terjadi kesalahan server. Periksa audit/log.':$e->getMessage()],500);}

function api_user_save(array$i):array
{
    $id=(int)($i['id']??0);$name=clean_text($i['name']??'',120);$username=clean_text($i['username']??'',80);$email=clean_text($i['email']??'',160);$role=(string)($i['role']??'director');$password=(string)($i['password']??'');$active=(int)($i['is_active']??1)===1?1:0;
    if($name===''||$username==='')return['ok'=>false,'message'=>'Nama dan username wajib.'];if(!in_array($role,['creator','finance_manager','accountant','director'],true))return['ok'=>false,'message'=>'Role tidak valid.'];
    $perm=$i['permission_overrides']??[];if(!is_array($perm))$perm=[];$permJson=json_encode($perm,JSON_UNESCAPED_UNICODE);
    $pdo=db();
    if($id>0){$st=$pdo->prepare('SELECT * FROM users WHERE id=?');$st->execute([$id]);$old=$st->fetch();if(!$old)return['ok'=>false,'message'=>'User tidak ditemukan.'];
        if((int)$old['id']===(int)current_user()['id']&&$active===0)return['ok'=>false,'message'=>'Creator tidak boleh menonaktifkan akun yang sedang digunakan.'];
        if($old['role']==='creator'&&($role!=='creator'||$active===0)){$c=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='creator' AND is_active=1")->fetchColumn();if($c<=1)return['ok'=>false,'message'=>'Creator aktif terakhir tidak boleh diubah/dinonaktifkan.'];}
        $sql='UPDATE users SET name=?,username=?,email=?,role=?,permission_overrides=?,is_active=?,updated_at=NOW()';$args=[$name,$username,$email?:null,$role,$permJson,$active];if($password!==''){if(strlen($password)<12)return['ok'=>false,'message'=>'Password minimal 12 karakter.'];$sql.=',password_hash=?,force_password_change=1,session_version=session_version+1';$args[]=password_hash($password,PASSWORD_DEFAULT);}$sql.=' WHERE id=?';$args[]=$id;$pdo->prepare($sql)->execute($args);audit('user.update','user',(string)$id,['role'=>$role,'active'=>$active]);return['ok'=>true,'message'=>'User berhasil diperbarui.'];
    }
    if(strlen($password)<12)return['ok'=>false,'message'=>'Password user baru minimal 12 karakter.'];
    $pdo->prepare('INSERT INTO users(name,username,email,password_hash,role,permission_overrides,is_active,force_password_change,created_at) VALUES(?,?,?,?,?,?,?,1,NOW())')->execute([$name,$username,$email?:null,password_hash($password,PASSWORD_DEFAULT),$role,$permJson,$active]);$new=(int)$pdo->lastInsertId();audit('user.create','user',(string)$new,['role'=>$role]);return['ok'=>true,'message'=>'User berhasil dibuat.'];
}
function api_user_delete(int$id,string$confirmPassword):array
{
    if(!confirm_current_password($confirmPassword))return['ok'=>false,'message'=>'Password Creator tidak valid.'];
    $pdo=db();$st=$pdo->prepare('SELECT * FROM users WHERE id=?');$st->execute([$id]);$u=$st->fetch();if(!$u)return['ok'=>false,'message'=>'User tidak ditemukan.'];if($id===(int)current_user()['id'])return['ok'=>false,'message'=>'Akun yang sedang digunakan tidak boleh dihapus.'];if($u['role']==='creator'){$c=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='creator' AND is_active=1")->fetchColumn();if($c<=1)return['ok'=>false,'message'=>'Creator aktif terakhir tidak boleh dihapus.'];}
    $refs=0;foreach(['audit_logs'=>'user_id','upload_batches'=>'uploaded_by','design_versions'=>'created_by']as$t=>$c){$s=$pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE `$c`=?");$s->execute([$id]);$refs+=(int)$s->fetchColumn();}
    if($refs>0){$pdo->prepare('UPDATE users SET is_active=0,updated_at=NOW() WHERE id=?')->execute([$id]);$msg='User memiliki histori, sehingga dinonaktifkan dan credential dicabut.';}else{$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);$msg='User tanpa histori berhasil dihapus permanen.';}audit('user.delete','user',(string)$id,['mode'=>$refs>0?'deactivate':'hard_delete']);return['ok'=>true,'message'=>$msg];
}
function api_user_revoke_sessions(int$id,string$confirmPassword):array
{
    if(!confirm_current_password($confirmPassword))return['ok'=>false,'message'=>'Password Creator tidak valid.'];
    $st=db()->prepare('SELECT id,name FROM users WHERE id=?');$st->execute([$id]);$u=$st->fetch();if(!$u)return['ok'=>false,'message'=>'User tidak ditemukan.'];
    if($id===(int)current_user()['id'])return['ok'=>false,'message'=>'Gunakan logout untuk mengakhiri sesi sendiri.'];
    db()->prepare('UPDATE users SET session_version=session_version+1,updated_at=NOW() WHERE id=?')->execute([$id]);audit('user.revoke_sessions','user',(string)$id);return['ok'=>true,'message'=>'Semua sesi aktif user berhasil diputus.'];
}
function api_creator_bulk_replace(array$i):array
{
    if(!confirm_current_password((string)($i['confirm_password']??'')))return['ok'=>false,'message'=>'Password Creator tidak valid.'];
    $entity=(string)($i['entity']??'');$year=(int)($i['year']??0);$rows=$i['rows']??[];if(!is_array($rows)||$year<2020)return['ok'=>false,'message'=>'Entity/year/rows tidak valid.'];
    $allowed=['monthly','services','customers','invoices','ar','ap','assets','expenses','targets'];if(!in_array($entity,$allowed,true))return['ok'=>false,'message'=>'Entity tidak diizinkan.'];
    $pdo=db();$snap=snapshot_year($year);$payload=['meta'=>['year'=>$year,'month'=>latest_month_for_year($year)]];foreach($allowed as$k)$payload[$k]=[];$payload[$entity]=$rows;
    // Normalize current dashboard JSON back to import format.
    if($entity==='monthly')$payload['monthly']=array_map(fn($r)=>['year'=>$year,'month'=>normalize_month($r['month']??(($r['idx']??0)+1)),'revenue'=>$r['revenue']??0,'cogs'=>$r['cogs']??0,'opex'=>$r['opex']??0,'expense'=>$r['expense']??0,'gross_profit'=>$r['gross']??$r['gross_profit']??0,'net_profit'=>$r['net']??$r['net_profit']??0,'ebitda'=>$r['ebitda']??0,'depreciation'=>$r['depreciation']??0,'cash_in'=>$r['cashIn']??$r['cash_in']??0,'cash_out'=>$r['cashOut']??$r['cash_out']??0,'cash_net'=>$r['cashNet']??$r['cash_net']??0,'cash_balance'=>$r['cashBalance']??$r['cash_balance']??0],$rows);
    $map=['services'=>'service_monthly','customers'=>'customer_monthly','invoices'=>'invoices','ar'=>'receivable_snapshots','ap'=>'payable_snapshots','assets'=>'asset_snapshots','expenses'=>'expense_monthly'];
    $pdo->beginTransaction();try{
        if(isset($map[$entity]))$pdo->prepare("DELETE FROM `{$map[$entity]}` WHERE year=?")->execute([$year]);
        if($entity==='monthly')$pdo->prepare('DELETE FROM financial_monthly WHERE year=?')->execute([$year]);
        if($entity==='targets')$pdo->prepare('DELETE FROM targets WHERE year>=? AND year<=?')->execute([$year,$year+10]);
        apply_import_payload($payload);$u=current_user();
        $pdo->prepare('INSERT INTO upload_batches(year,month,filename,file_hash,status,payload_json,pre_snapshot_json,uploaded_by,published_by,created_at,published_at) VALUES(?,?,?,? ,"published",?,?,?,?,NOW(),NOW())')->execute([$year,latest_month_for_year($year),'CREATOR_DATA_LAB_'.$entity.'.json',hash('sha256',json_encode($rows)),json_encode($payload,JSON_UNESCAPED_UNICODE),json_encode($snap,JSON_UNESCAPED_UNICODE),(int)$u['id'],(int)$u['id']]);
        set_setting('demo_mode',false);$pdo->commit();audit('creator.bulk_replace',$entity,(string)$year,['rows'=>count($rows)]);return['ok'=>true,'message'=>'Dataset '.$entity.' berhasil diganti dan audit snapshot dibuat.'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();return['ok'=>false,'message'=>$e->getMessage()];}
}
