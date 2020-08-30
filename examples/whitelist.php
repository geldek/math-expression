<?php
require_once(__DIR__  . '\..\vendor\autoload.php');

use geldek\math\Expression;
use geldek\math\ExpressionException;
use geldek\math\Token;

try {
    $expression = new Expression('cos(0)');
    $expression->setWhiteList(['sin']);
    $tokens = $expression->parse();
    $result = $expression->calculate();
    var_dump($result);
}
catch(ExpressionException $ex) {
    var_dump($ex->getMessage());
    //"Function is blacklisted."
    var_dump($ex->getToken());
    //string(3) "cos"
    var_dump($ex->getPosition());
    //int(0)
}