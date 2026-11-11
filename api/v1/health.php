<?php
declare(strict_types=1);require __DIR__.'/_bootstrap.php';api_method('GET');
try{db()->query('SELECT 1');json_response(['ok'=>true,'service'=>'kvskills-hub','time'=>date(DATE_ATOM)]);}catch(Throwable){json_response(['ok'=>false,'service'=>'kvskills-hub','message'=>'database unavailable'],503);}
