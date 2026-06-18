<?php
declare(strict_types=1);

function available_years(): array
{
    $years = db()->query('SELECT DISTINCT year FROM financial_monthly ORDER BY year DESC')->fetchAll(PDO::FETCH_COLUMN);
    if (!$years) $years = [date('Y')];
    return array_map('intval', $years);
}

function latest_month_for_year(int $year): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(month),1) FROM financial_monthly WHERE year=?');
    $stmt->execute([$year]);
    return max(1, (int)$stmt->fetchColumn());
}

function dashboard_data(int $year): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM financial_monthly WHERE year=? ORDER BY month');
    $stmt->execute([$year]);
    $rows = $stmt->fetchAll();
    $byMonth = [];
    foreach ($rows as $r) $byMonth[(int)$r['month']] = $r;
    $monthly = [];
    for ($m=1; $m<=12; $m++) {
        $r = $byMonth[$m] ?? [];
        $monthly[] = [
            'idx'=>$m-1,'month'=>month_short($m),'monthLong'=>month_long($m),
            'revenue'=>(float)($r['revenue']??0),'cogs'=>(float)($r['cogs']??0),'opex'=>(float)($r['opex']??0),
            'expense'=>(float)($r['expense']??0),'gross'=>(float)($r['gross_profit']??0),'net'=>(float)($r['net_profit']??0),
            'ebitda'=>(float)($r['ebitda']??0),'depreciation'=>(float)($r['depreciation']??0),
            'cashIn'=>(float)($r['cash_in']??0),'cashOut'=>(float)($r['cash_out']??0),'cashNet'=>(float)($r['cash_net']??0),
            'cashBalance'=>(float)($r['cash_balance']??0),
        ];
    }
    $latestMonth = latest_month_for_year($year);
    $sum = fn(string $k): float => array_reduce($monthly, fn($s,$d)=>$s+(float)$d[$k], 0.0);
    $revenue=$sum('revenue'); $cogs=$sum('cogs'); $opex=$sum('opex'); $expense=$sum('expense');
    $gross=$sum('gross'); $net=$sum('net'); $ebitda=$sum('ebitda'); $depr=$sum('depreciation');

    $services = fetch_aggregate_rows('service_monthly', $year, ['name'], ['SUM(value) value','SUM(tx_count) count'], 'value DESC');
    $customers = fetch_aggregate_rows('customer_monthly', $year, ['name','alias'], ['SUM(value) value','SUM(tx_count) count'], 'value DESC');

    $stmt = $pdo->prepare('SELECT invoice_no invoice, original_invoice originalInvoice, tax_invoice taxInvoice, invoice_date date, month, customer, alias, service, category, dpp, ppn, status FROM invoices WHERE year=? ORDER BY invoice_date, id');
    $stmt->execute([$year]);
    $invoices = array_map(function($x) use($year){
        $x['month'] = month_short((int)$x['month']);
        $x['monthLong'] = month_long(normalize_month($x['month']));
        $x['monthIdx'] = normalize_month($x['month'])-1;
        $x['year']=$year; $x['dpp']=(float)$x['dpp']; $x['ppn']=(float)$x['ppn'];
        return $x;
    }, $stmt->fetchAll());

    $ar = fetch_snapshot('receivable_snapshots', $year, $latestMonth, 'customer');
    $ar = array_map(fn($x)=>[
        'customer'=>$x['customer'],'alias'=>$x['alias'],'debit'=>(float)$x['debit'],'credit'=>(float)$x['credit'],
        'ending'=>(float)$x['ending'],'status'=>$x['status'],'dueDate'=>$x['due_date']
    ], $ar);
    $ap = fetch_snapshot('payable_snapshots', $year, $latestMonth, 'vendor');
    $ap = array_map(fn($x)=>[
        'vendor'=>$x['vendor'],'beginning'=>(float)$x['beginning'],'payment'=>(float)$x['payment'],'credit'=>(float)$x['credit'],
        'outstanding'=>(float)$x['outstanding'],'status'=>$x['status'],'priority'=>$x['priority'],'dueDate'=>$x['due_date']
    ], $ap);
    $assets = fetch_snapshot('asset_snapshots', $year, $latestMonth, 'name');
    $assets = array_map(fn($x)=>['name'=>$x['name'],'value'=>(float)$x['value'],'category'=>$x['category']], $assets);
    $expenses = fetch_aggregate_rows('expense_monthly', $year, ['name'], ['SUM(value) value'], 'value DESC');
    $targets = $pdo->query('SELECT year,revenue,ebitda,margin,type FROM targets ORDER BY year')->fetchAll();
    $targets = array_map(fn($x)=>['year'=>(int)$x['year'],'revenue'=>(float)$x['revenue'],'ebitda'=>(float)$x['ebitda'],'margin'=>(float)$x['margin'],'type'=>$x['type']],$targets);

    $cash = (float)($monthly[$latestMonth-1]['cashBalance'] ?? 0);
    $arTotal = array_reduce($ar, fn($s,$x)=>$s+$x['ending'], 0.0);
    $apTotal = array_reduce($ap, fn($s,$x)=>$s+$x['outstanding'], 0.0);
    $currentAssets = array_reduce($assets, fn($s,$x)=>$s+($x['category']==='Current'?$x['value']:0), 0.0);
    $fixedAssets = array_reduce($assets, fn($s,$x)=>$s+($x['category']==='Fixed'?$x['value']:0), 0.0);
    $totalAssets = array_reduce($assets, fn($s,$x)=>$s+$x['value'], 0.0);
    $liabilities = $apTotal;
    $equity = $totalAssets-$liabilities;
    $summary = [
        'revenue'=>$revenue,'cogs'=>$cogs,'grossProfit'=>$gross,'opex'=>$opex,'operatingProfit'=>$gross-$opex,
        'otherRevenue'=>0,'otherExpense'=>0,'netProfit'=>$net,'depreciation'=>$depr,'ebitda'=>$ebitda,
        'cash'=>$cash,'ar'=>$arTotal,'ap'=>$apTotal,'assets'=>$totalAssets,'liabilities'=>$liabilities,'equity'=>$equity,
        'grossMargin'=>$revenue!==0?$gross/$revenue*100:0,'netMargin'=>$revenue!==0?$net/$revenue*100:0,
        'ebitdaMargin'=>$revenue!==0?$ebitda/$revenue*100:0,'currentAssets'=>$currentAssets,'fixedAssets'=>$fixedAssets,
    ];

    $arInvoices = array_map(fn($x)=>[
        'date'=>$x['date'],'customer'=>$x['customer'],'alias'=>$x['alias'],'invoice'=>$x['originalInvoice'] ?: $x['invoice'],
        'displayInvoice'=>$x['invoice'],'description'=>$x['service'],'amount'=>$x['dpp']+$x['ppn']
    ], $invoices);

    return [
        'meta'=>['company'=>'PT DND JAVA INDONESIA','year'=>$year,'source'=>'SADINO Database','latestMonth'=>$latestMonth,'isDemo'=>(bool)get_setting('demo_mode', true)],
        'summary'=>$summary,'monthly'=>$monthly,'services'=>$services,'customers'=>$customers,'invoices'=>$invoices,
        'ar'=>$ar,'arInvoices'=>$arInvoices,'ap'=>$ap,'assets'=>$assets,'expenses'=>$expenses,'targets'=>$targets
    ];
}

function fetch_aggregate_rows(string $table, int $year, array $groups, array $selects, string $order): array
{
    $allowed = ['service_monthly','customer_monthly','expense_monthly'];
    if (!in_array($table,$allowed,true)) return [];
    $groupSql = implode(',', array_map(fn($x)=>"`$x`", $groups));
    $sql = 'SELECT '.$groupSql.','.implode(',',$selects)." FROM `$table` WHERE year=? GROUP BY $groupSql ORDER BY $order";
    $stmt=db()->prepare($sql); $stmt->execute([$year]);
    return array_map(function($r){ foreach($r as $k=>$v){ if(in_array($k,['value','count'],true))$r[$k]=(float)$v; } return $r; },$stmt->fetchAll());
}

function fetch_snapshot(string $table, int $year, int $month, string $order): array
{
    $allowed=['receivable_snapshots','payable_snapshots','asset_snapshots'];
    if(!in_array($table,$allowed,true))return[];
    $stmt=db()->prepare("SELECT MAX(month) FROM `$table` WHERE year=? AND month<=?");
    $stmt->execute([$year,$month]); $m=(int)$stmt->fetchColumn();
    if($m<1)return[];
    $stmt=db()->prepare("SELECT * FROM `$table` WHERE year=? AND month=? ORDER BY `$order`");
    $stmt->execute([$year,$m]); return $stmt->fetchAll();
}

function get_setting(string $key, mixed $default=null): mixed
{
    $stmt=db()->prepare('SELECT value_json FROM settings WHERE setting_key=?'); $stmt->execute([$key]);
    $v=$stmt->fetchColumn(); if($v===false)return$default;
    $decoded=json_decode((string)$v,true); return json_last_error()===JSON_ERROR_NONE?$decoded:$v;
}
function set_setting(string $key, mixed $value): void
{
    $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    db()->prepare('INSERT INTO settings (setting_key,value_json,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_at=NOW()')->execute([$key,$json]);
}

function user_public(array $u): array
{
    return [
        'id'=>(int)$u['id'],'name'=>$u['name'],'username'=>$u['username'],'email'=>$u['email'],'role'=>$u['role'],
        'roleLabel'=>role_label($u['role']),'permissions'=>effective_permissions($u),'forcePasswordChange'=>(bool)$u['force_password_change']
    ];
}

function list_users(): array
{
    return array_map(function($u){unset($u['password_hash']);$u['id']=(int)$u['id'];$u['is_active']=(bool)$u['is_active'];return$u;},db()->query('SELECT id,name,username,email,role,permission_overrides,is_active,force_password_change,last_login_at,created_at FROM users ORDER BY role,name')->fetchAll());
}

function list_audit(int $limit=200): array
{
    $stmt=db()->prepare('SELECT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT ?');
    $stmt->bindValue(1,$limit,PDO::PARAM_INT);$stmt->execute();return$stmt->fetchAll();
}

function upload_batches(int $limit=100): array
{
    $stmt=db()->prepare('SELECT b.id,b.year,b.month,b.filename,b.file_hash,b.status,b.created_at,b.published_at,u.name uploaded_by_name,p.name published_by_name FROM upload_batches b LEFT JOIN users u ON u.id=b.uploaded_by LEFT JOIN users p ON p.id=b.published_by ORDER BY b.id DESC LIMIT ?');
    $stmt->bindValue(1,$limit,PDO::PARAM_INT);$stmt->execute();return$stmt->fetchAll();
}
