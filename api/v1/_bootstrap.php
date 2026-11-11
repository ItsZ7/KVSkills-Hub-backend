<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function api_method(string ...$allowed): void
{
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,$allowed,true)){
        header('Allow: '.implode(', ',$allowed));
        json_response(['ok'=>false,'message'=>'Method not allowed'],405);
    }
}

function api_body(): array
{
    $raw=file_get_contents('php://input');
    if(!$raw)return $_POST;
    try{$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);return is_array($data)?$data:[];}
    catch(Throwable){json_response(['ok'=>false,'message'=>'JSON tidak sah'],400);}
}
