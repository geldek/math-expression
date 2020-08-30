<?php
require_once(__DIR__  . '\..\vendor\autoload.php');

use geldek\math\Expression;
use geldek\math\ExpressionException;
use geldek\math\Token;

function mymax($a, $b) {
    return $a > $b ? $a : $b;
}

$expression = new Expression('mymax(2,-3)');
$tokens = $expression->parse();
$result = $expression->calculate();
var_dump($result);
//int(2)