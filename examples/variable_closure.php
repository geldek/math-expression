<?php
require_once(__DIR__  . '\..\vendor\autoload.php');

use geldek\math\Expression;
use geldek\math\ExpressionException;
use geldek\math\Token;

$expression = new Expression('var1 + var2 + z_1', [
    'var1' => 1,
    'var2' => function($name) {
         return $name == 'y' ? 2 : 3;
    }
]);
$expression->setVariable('z_1', 5);
$tokens = $expression->parse();
$result = $expression->calculate();
var_dump($result);
//int(9)