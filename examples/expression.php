<?php
require_once(__DIR__  . '\..\vendor\autoload.php');

use geldek\math\Expression;
use geldek\math\ExpressionException;
use geldek\math\Token;

$expression = new Expression('(.1 + 2.9e3)^2 * 3 / -cos(0) + 0x1F % 0b11');
$tokens = $expression->parse();
$result = $expression->calculate();
var_dump($result);
//double(-25231739.03)
