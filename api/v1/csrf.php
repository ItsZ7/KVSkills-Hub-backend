<?php
declare(strict_types=1);require __DIR__.'/_bootstrap.php';api_method('GET');json_response(['ok'=>true,'token'=>Csrf::token()]);
