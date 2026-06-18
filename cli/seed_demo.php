<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
if((int)db()->query('SELECT COUNT(*) FROM financial_monthly')->fetchColumn()>0){echo"Data sudah ada, demo seed dilewati.\n";exit(0);} 
$seed=json_decode(file_get_contents(dirname(__DIR__).'/database/demo_seed.json'),true);if(!is_array($seed))throw new RuntimeException('Demo seed invalid');
$p=['meta'=>['year'=>2025,'month'=>12],
'monthly'=>array_map(fn($r)=>['year'=>2025,'month'=>$r['idx']+1,'revenue'=>$r['revenue'],'cogs'=>$r['cogs'],'opex'=>$r['opex'],'expense'=>$r['expense'],'gross_profit'=>$r['gross'],'net_profit'=>$r['net'],'ebitda'=>$r['ebitda'],'depreciation'=>$r['depreciation'],'cash_in'=>$r['cashIn'],'cash_out'=>$r['cashOut'],'cash_net'=>$r['cashNet'],'cash_balance'=>$r['cashBalance']],$seed['monthly']),
'services'=>array_map(fn($r)=>['year'=>2025,'month'=>12,'name'=>$r['name'],'value'=>$r['value'],'count'=>$r['count']],$seed['services']),
'customers'=>array_map(fn($r)=>['year'=>2025,'month'=>12,'name'=>$r['name'],'alias'=>$r['alias'],'value'=>$r['value'],'count'=>$r['count']],$seed['customers']),
'invoices'=>array_map(fn($r)=>['year'=>2025,'month'=>$r['monthIdx']+1,'invoice_no'=>$r['invoice'],'original_invoice'=>$r['invoice'],'tax_invoice'=>$r['taxInvoice'],'date'=>$r['date'],'customer'=>$r['customer'],'alias'=>$r['alias'],'service'=>$r['service'],'category'=>$r['category'],'dpp'=>$r['dpp'],'ppn'=>$r['ppn'],'status'=>$r['status']],$seed['invoices']),
'ar'=>array_map(fn($r)=>['year'=>2025,'month'=>12,'customer'=>$r['customer'],'alias'=>$r['alias'],'debit'=>$r['debit'],'credit'=>$r['credit'],'ending'=>$r['ending'],'status'=>$r['status']],$seed['ar']),
'ap'=>array_map(fn($r)=>['year'=>2025,'month'=>12,'vendor'=>$r['vendor'],'beginning'=>$r['beginning'],'payment'=>$r['payment'],'credit'=>$r['credit'],'outstanding'=>$r['outstanding'],'status'=>$r['status'],'priority'=>$r['priority']],$seed['ap']),
'assets'=>array_map(function($r){$fixed=str_contains(strtolower($r['name']),'tetap')||str_contains(strtolower($r['name']),'mesin')||str_contains(strtolower($r['name']),'bangunan');return['year'=>2025,'month'=>12,'name'=>$r['name'],'value'=>$r['value'],'category'=>$fixed?'Fixed':'Current'];},$seed['assets']),
'expenses'=>array_map(fn($r)=>['year'=>2025,'month'=>12,'name'=>$r['name'],'value'=>$r['value']],$seed['expenses']),
'targets'=>$seed['targets']];
db()->beginTransaction();try{apply_import_payload($p);set_setting('demo_mode',true);db()->commit();echo"Demo data ±10% berhasil ditanam.\n";}catch(Throwable$e){db()->rollBack();throw$e;}
