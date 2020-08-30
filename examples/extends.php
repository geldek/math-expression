<?php
require_once(__DIR__  . '\..\vendor\autoload.php');

use geldek\math\Expression;
use geldek\math\ExpressionException;
use geldek\math\Token;

class ExpressionEx extends Expression
{
    public function __construct($exp, $vars = []) {
        parent::__construct($exp, $vars);
    }

    public function mymin($a, $b) {
        return $a > $b ? $b : $a;
    }
}

$expression = new ExpressionEx('mymin(2,-3)');
$tokens = $expression->parse();
$result = $expression->calculate();
var_dump($result);
//int(-3)