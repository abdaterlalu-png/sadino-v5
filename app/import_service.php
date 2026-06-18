<?php
declare(strict_types=1);

function validate_import_payload(array $payload): array
{
    $errors=[];$warnings=[];
    $meta=$payload['meta']??[];
    $year=(int)($meta['year']??0);$month=normalize_month($meta['month']??1);
    if($year<2020||$year>2100)$errors[]='Year pada UPLOAD_META tidak valid.';
    $monthly=$payload['monthly']??[];
    if(!is_array($monthly)||count($monthly)<1)$errors[]='Sheet MONTHLY wajib memiliki minimal satu baris.';
    $required=['revenue','cogs','opex','expense','gross_profit','net_profit','ebitda','depreciation','cash_in','cash_out','cash_net','cash_balance'];
    foreach($monthly as $i=>$r){foreach($required as $k){if(!array_key_exists($k,$r))$errors[]='MONTHLY baris '.($i+2).' kehilangan kolom '.$k;}}
    foreach(['services','customers','invoices','ar','ap','assets','expenses','targets'] as $sheet){
        if(!isset($payload[$sheet])||!is_array($payload[$sheet]))$warnings[]='Sheet '.strtoupper($sheet).' kosong atau tidak ditemukan.';
    }
    return ['ok'=>!$errors,'errors'=>$errors,'warnings'=>$warnings,'year'=>$year,'month'=>$month,
        'counts'=>array_map(fn($k)=>count($payload[$k]??[]),['monthly','services','customers','invoices','ar','ap','assets','expenses','targets'])];
}

function stage_import(array $payload,string $filename,string $hash,bool $publishNow=false): array
{
    $v=validate_import_payload($payload);if(!$v['ok'])return['ok'=>false,'message'=>'Validasi gagal.','validation'=>$v];
    $u=current_user();$status=$publishNow&&can('import.publish',$u)?'draft':'draft';
    $stmt=db()->prepare('INSERT INTO upload_batches (year,month,filename,file_hash,status,payload_json,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([$v['year'],$v['month'],clean_text($filename,190),$hash,$status,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$u['id']]);
    $id=(int)db()->lastInsertId();
    audit('import.stage','upload_batch',(string)$id,['filename'=>$filename,'year'=>$v['year'],'month'=>$v['month'],'counts'=>$v['counts']]);
    if($publishNow&&can('import.publish',$u))return publish_import($id);
    return ['ok'=>true,'message'=>'File berhasil disimpan sebagai draft dan menunggu publish.','batchId'=>$id,'validation'=>$v];
}

function snapshot_year(int $year): array
{
    $pdo=db();$tables=['financial_monthly','service_monthly','customer_monthly','invoices','receivable_snapshots','payable_snapshots','asset_snapshots','expense_monthly'];$out=[];
    foreach($tables as $t){$s=$pdo->prepare("SELECT * FROM `$t` WHERE year=?");$s->execute([$year]);$out[$t]=$s->fetchAll();}
    $s=$pdo->prepare('SELECT * FROM targets WHERE year>=? AND year<=?');$s->execute([$year,$year+10]);$out['targets']=$s->fetchAll();
    return$out;
}

function publish_import(int $batchId): array
{
    require_permission('import.publish');
    $pdo=db();$stmt=$pdo->prepare('SELECT * FROM upload_batches WHERE id=? FOR UPDATE');
    $pdo->beginTransaction();
    try{
        $stmt->execute([$batchId]);$b=$stmt->fetch();
        if(!$b)throw new RuntimeException('Batch upload tidak ditemukan.');
        if($b['status']==='published')throw new RuntimeException('Batch sudah dipublish.');
        $payload=json_decode((string)$b['payload_json'],true);if(!is_array($payload))throw new RuntimeException('Payload upload rusak.');
        $year=(int)$b['year'];$snap=snapshot_year($year);
        apply_import_payload($payload);
        $u=current_user();
        $pdo->prepare('UPDATE upload_batches SET status="published",pre_snapshot_json=?,published_by=?,published_at=NOW() WHERE id=?')
            ->execute([json_encode($snap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$u['id'],$batchId]);
        set_setting('demo_mode',false);
        $pdo->commit();audit('import.publish','upload_batch',(string)$batchId,['year'=>$year]);
        return['ok'=>true,'message'=>'Data berhasil dipublish dan dashboard realtime sudah diperbarui.','batchId'=>$batchId];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();return['ok'=>false,'message'=>$e->getMessage()];}
}

function rollback_import(int $batchId,string $confirmPassword=''): array
{
    require_permission('import.rollback');
    if(!confirm_current_password($confirmPassword))return['ok'=>false,'message'=>'Password Creator tidak valid.'];
    $pdo=db();$pdo->beginTransaction();
    try{
        $s=$pdo->prepare('SELECT * FROM upload_batches WHERE id=? FOR UPDATE');$s->execute([$batchId]);$b=$s->fetch();
        if(!$b||$b['status']!=='published')throw new RuntimeException('Batch published tidak ditemukan.');
        $snap=json_decode((string)$b['pre_snapshot_json'],true);if(!is_array($snap))throw new RuntimeException('Snapshot rollback tidak tersedia.');
        restore_snapshot((int)$b['year'],$snap);
        $pdo->prepare('UPDATE upload_batches SET status="rolled_back" WHERE id=?')->execute([$batchId]);
        $pdo->commit();audit('import.rollback','upload_batch',(string)$batchId,['year'=>(int)$b['year']]);
        return['ok'=>true,'message'=>'Rollback selesai. Data tahun terkait dikembalikan ke kondisi sebelum upload.'];
    }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();return['ok'=>false,'message'=>$e->getMessage()];}
}

function apply_import_payload(array $p): void
{
    $pdo=db();$meta=$p['meta']??[];$year=(int)($meta['year']??date('Y'));$defaultMonth=normalize_month($meta['month']??1);
    $upsert=function(string$sql,array$rows,callable$args)use($pdo){$st=$pdo->prepare($sql);foreach($rows as$r)$st->execute($args($r));};
    $upsert('INSERT INTO financial_monthly (year,month,revenue,cogs,opex,expense,gross_profit,net_profit,ebitda,depreciation,cash_in,cash_out,cash_net,cash_balance,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE revenue=VALUES(revenue),cogs=VALUES(cogs),opex=VALUES(opex),expense=VALUES(expense),gross_profit=VALUES(gross_profit),net_profit=VALUES(net_profit),ebitda=VALUES(ebitda),depreciation=VALUES(depreciation),cash_in=VALUES(cash_in),cash_out=VALUES(cash_out),cash_net=VALUES(cash_net),cash_balance=VALUES(cash_balance),updated_at=NOW()',
        $p['monthly']??[],fn($r)=>[(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),as_float($r['revenue']??0),as_float($r['cogs']??0),as_float($r['opex']??0),as_float($r['expense']??0),as_float($r['gross_profit']??0),as_float($r['net_profit']??0),as_float($r['ebitda']??0),as_float($r['depreciation']??0),as_float($r['cash_in']??0),as_float($r['cash_out']??0),as_float($r['cash_net']??0),as_float($r['cash_balance']??0)]);
    $upsert('INSERT INTO service_monthly (year,month,name,value,tx_count,updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),tx_count=VALUES(tx_count),updated_at=NOW()',
        $p['services']??[],fn($r)=>[(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),clean_text($r['name']??'',180),as_float($r['value']??0),(int)($r['count']??$r['tx_count']??0)]);
    $upsert('INSERT INTO customer_monthly (year,month,name,alias,value,tx_count,updated_at) VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE alias=VALUES(alias),value=VALUES(value),tx_count=VALUES(tx_count),updated_at=NOW()',
        $p['customers']??[],fn($r)=>[(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),clean_text($r['name']??'',190),clean_text($r['alias']??$r['name']??'',80),as_float($r['value']??0),(int)($r['count']??$r['tx_count']??0)]);
    foreach($p['invoices']??[]as$r){$m=normalize_month($r['month']??$defaultMonth);$inv=clean_text($r['invoice_no']??$r['invoice']??'',100);if($inv==='')$inv='INV/'.str_pad((string)random_int(1,999),3,'0',STR_PAD_LEFT).'/DNDJ/'.roman_month($m).'/'.$year;
        $pdo->prepare('INSERT INTO invoices (year,month,invoice_no,original_invoice,tax_invoice,invoice_date,customer,alias,service,category,dpp,ppn,status,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE original_invoice=VALUES(original_invoice),tax_invoice=VALUES(tax_invoice),invoice_date=VALUES(invoice_date),customer=VALUES(customer),alias=VALUES(alias),service=VALUES(service),category=VALUES(category),dpp=VALUES(dpp),ppn=VALUES(ppn),status=VALUES(status),updated_at=NOW()')
        ->execute([(int)($r['year']??$year),$m,$inv,clean_text($r['original_invoice']??'',100),clean_text($r['tax_invoice']??'',100),clean_text($r['date']??date('Y-m-d'),20),clean_text($r['customer']??'',190),clean_text($r['alias']??$r['customer']??'',80),clean_text($r['service']??'',500),clean_text($r['category']??'',150),as_float($r['dpp']??0),as_float($r['ppn']??0),clean_text($r['status']??'APPROVED',40)]);}
    replace_snapshot_rows('receivable_snapshots',$p['ar']??[],$year,$defaultMonth);
    replace_snapshot_rows('payable_snapshots',$p['ap']??[],$year,$defaultMonth);
    replace_snapshot_rows('asset_snapshots',$p['assets']??[],$year,$defaultMonth);
    $upsert('INSERT INTO expense_monthly (year,month,name,value,updated_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=NOW()',
        $p['expenses']??[],fn($r)=>[(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),clean_text($r['name']??'',190),as_float($r['value']??0)]);
    $upsert('INSERT INTO targets (year,revenue,ebitda,margin,type,updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE revenue=VALUES(revenue),ebitda=VALUES(ebitda),margin=VALUES(margin),type=VALUES(type),updated_at=NOW()',
        $p['targets']??[],fn($r)=>[(int)($r['year']??$year),as_float($r['revenue']??0),as_float($r['ebitda']??0),as_float($r['margin']??0),clean_text($r['type']??'Target',30)]);
}

function replace_snapshot_rows(string$table,array$rows,int$year,int$defaultMonth):void
{
    if(!$rows)return;$pdo=db();$group=[];foreach($rows as$r){$y=(int)($r['year']??$year);$m=normalize_month($r['month']??$defaultMonth);$group["$y-$m"]=[$y,$m];}
    foreach($group as[$y,$m])$pdo->prepare("DELETE FROM `$table` WHERE year=? AND month=?")->execute([$y,$m]);
    if($table==='receivable_snapshots'){$st=$pdo->prepare('INSERT INTO receivable_snapshots (year,month,customer,alias,debit,credit,ending,status,due_date,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');foreach($rows as$r)$st->execute([(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),clean_text($r['customer']??'',190),clean_text($r['alias']??$r['customer']??'',80),as_float($r['debit']??0),as_float($r['credit']??0),as_float($r['ending']??0),clean_text($r['status']??'Open',40),($r['due_date']??null)?:null]);}
    if($table==='payable_snapshots'){$st=$pdo->prepare('INSERT INTO payable_snapshots (year,month,vendor,beginning,payment,credit,outstanding,status,priority,due_date,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');foreach($rows as$r)$st->execute([(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),clean_text($r['vendor']??'',190),as_float($r['beginning']??0),as_float($r['payment']??0),as_float($r['credit']??0),as_float($r['outstanding']??0),clean_text($r['status']??'Belum Dibayar',40),clean_text($r['priority']??'Medium',30),($r['due_date']??null)?:null]);}
    if($table==='asset_snapshots'){$st=$pdo->prepare('INSERT INTO asset_snapshots (year,month,name,value,category,updated_at) VALUES (?,?,?,?,?,NOW())');foreach($rows as$r)$st->execute([(int)($r['year']??$year),normalize_month($r['month']??$defaultMonth),clean_text($r['name']??'',190),as_float($r['value']??0),clean_text($r['category']??'Current',30)]);}
}

function restore_snapshot(int$year,array$snap):void
{
    $pdo=db();foreach(['financial_monthly','service_monthly','customer_monthly','invoices','receivable_snapshots','payable_snapshots','asset_snapshots','expense_monthly']as$t)$pdo->prepare("DELETE FROM `$t` WHERE year=?")->execute([$year]);
    $pdo->prepare('DELETE FROM targets WHERE year>=? AND year<=?')->execute([$year,$year+10]);
    foreach($snap as$t=>$rows){if(!$rows)continue;foreach($rows as$r){unset($r['id']);$cols=array_keys($r);$sql='INSERT INTO `'.$t.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';$pdo->prepare($sql)->execute(array_values($r));}}
}
