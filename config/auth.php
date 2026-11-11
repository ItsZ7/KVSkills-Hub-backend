<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function isLoggedIn(): bool { return Auth::check(); }
function currentUser(): ?array { return Auth::user(); }
function logout(): void { Auth::logout(); redirect('index.php'); }
