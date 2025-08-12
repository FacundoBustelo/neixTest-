<?php
// app/Domain/Validation.php
namespace App\Domain;

final class Validation {
  public static function str($v, $min=1, $max=100): string {
    if (!is_string($v)) throw new \InvalidArgumentException('string expected');
    $v = trim($v);
    if (strlen($v) < $min || strlen($v) > $max) throw new \InvalidArgumentException('invalid length');
    return $v;
  }
  public static function posNum($v): float {
    if (!is_numeric($v) || $v <= 0) throw new \InvalidArgumentException('positive number expected');
    return (float)$v;
  }
  public static function side($v): string {
    $v = strtolower((string)$v);
    if (!in_array($v, ['buy','sell'])) throw new \InvalidArgumentException('side must be buy/sell');
    return $v;
  }
}
