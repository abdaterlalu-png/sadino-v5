<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require_login();
$file=dirname(__DIR__).'/templates/SADINO_MONTHLY_UPLOAD_TEMPLATE.xlsx';if(!is_file($file)){http_response_code(404);exit('Template belum tersedia.');}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="SADINO_MONTHLY_UPLOAD_TEMPLATE.xlsx"');header('Content-Length: '.filesize($file));readfile($file);
