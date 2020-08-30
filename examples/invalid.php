<?php
require_once(__DIR__  . '\..\vendor\autoload.php');

use geldek\math\Expression;
use geldek\math\ExpressionException;
use geldek\math\Token;

try {
    $expression = new Expression('((1+2)+3');
    $tokens = $expression->parse();
    $result = $expression->calculate();
    var_dump($result);
}
catch(ExpressionException $ex) {
    var_dump($ex->getMessage());
    var_dump($ex->getToken());
    //string(1) "("
    var_dump($ex->getPosition());
    //int(0)
}