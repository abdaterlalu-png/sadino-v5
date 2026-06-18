<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
try{db()->query('SELECT 1');header('Content-Type: application/json');echo json_encode(['ok'=>true,'app'=>'SADINO','version'=>app_version(),'time'=>date(DATE_ATOM)]);}catch(Throwable$e){http_response_code(503);echo json_encode(['ok'=>false]);}
