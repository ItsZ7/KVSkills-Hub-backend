<?php
declare(strict_types=1);
function required(mixed $value): bool { return trim((string)$value) !== ''; }
function validEmail(string $email): bool { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function minLength(string $text, int $length): bool { return mb_strlen($text) >= $length; }
