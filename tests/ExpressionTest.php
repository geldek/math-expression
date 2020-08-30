<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use geldek\math\Expression;
use geldek\math\ExpressionException;

class EvaluatorTest extends TestCase
{
    public function testEmptyExpression()
    {
        $this->expectException(\InvalidArgumentException::class);
        $e = new Expression('');
    }

    /**
     * @dataProvider invalidVariablesProvider
     */
    public function testInvalidVariables($var) {
        $this->expectException(\InvalidArgumentException::class);
        $e = new Expression('1 + x', $var);
    }

    /**
     * @dataProvider validVariablesProvider
     */
    public function testValidVariables($var) {
        $e = new Expression('x * y * z', $var);
        $arr = $e->getVariables();
        $diff = array_udiff_assoc($var, $arr, function($a, $b) {
            if($a instanceof \Closure && $b instanceof \Closure) {
                return 0;
            }
            else {
                return ($a == $b ? 0 : ($a > $b ? 1 : -1));
            }
        });
        $this->assertEquals(count($diff), 0);
    }

    /**
     * @dataProvider validVariablesProvider
     */
    public function testGetVariable($var) {
        $e = new Expression('x', $var);
        $val = $e->getVariable('x');
        $this->assertEquals($val, $var['x']);
    }

    /**
     * @dataProvider validExpressionProvider
     */
    public function testGetExpression($exp, $res, $vars) {
        $e = new Expression($exp);
        $result = $e->getExpression();
        $this->assertEquals($result, $exp);
    }

    /**
     * @dataProvider validExpressionProvider
     */
    public function testCalculate($exp, $res, $var) {
        $e = new Expression($exp, $var);
        $e->parse();
        $result = $e->calculate();
        $this->assertEquals($result, $res);
    }

    /**
     * @dataProvider invalidExpressionProvider
     */
    public function testInvalidExpressionParse($exp) {
        $e = new Expression($exp);
        $this->expectException(ExpressionException::class);
        $e->parse();
        $result = $e->calculate();
    }

    public function testFunctionCall() {
        function mymax($a, $b) {
            return $a > $b ? $a : $b;
        }

        $e = new Expression('mymax(2,3)');
        $e->parse();
        $result = $e->calculate();
        $this->assertEquals($result, 3);
    }

    public function testMethodCall() {
        $obj = new class("mymax(4,10)", []) extends Expression {
            public function __construct($exp, $vars) {
                parent::__construct($exp, $vars);
            }
            public function mymax($a, $b) {
                return $a > $b ? $a : $b;
            }
        };

        $obj->parse();
        $result = $obj->calculate();
        $this->assertEquals($result, 10);
    }

    public function testVariableInvalidReturnFunctionCall() {
        $e = new Expression('1 + x', ['x' => function($name) { return 'invalid'; }]);
        $this->expectException(ExpressionException::class);
        $e->parse();
        $result = $e->calculate();
    }

    public function invalidVariablesProvider() {
        return [
            [[ 0 => 1]],
            [[ 123 => 1]],
            [[ 'x' => 'y']],
            [[ 'x' => '']],
            [[ 0 => 1, 1 => 1]],
            [[ '0_abc' => 1, 1 => 1]],
        ];
    }

    public function validVariablesProvider() {
        return [
            [['x' => 1]],
            [['x' => 0, 'y' => 1, 'z' => 2]],
            [['x' => function($name) { return 1; }]]
        ];
    }

    public function invalidExpressionProvider() {
        return [
            ['1.2E'],
            ['3.4E-'],
            ['3.4E01'],
            ['3.4E-02'],
            //['0011'],
            ['1.2.3'],
            ['&'],
            ['1+*2'],
            ['*'],
            ['1+foock'],
            ['expression not valid at all'],
            ['((1+2)^3']
        ];
    }

    public function validExpressionProvider() {
        return [
            ['0xFF', 0xFF, []],
            ['0X1ADF', 0x1ADF, []],
            ['0b11', 3, []],
            ['0B0011', 3, []],
            ['-cos(0)', -1, []],
            ['floor(4.3)', 4, []],
            ['1', "1", []],
            ['1+2', 3, []],
            ['1+-2', -1, []],
            ['1+x', 10, ['x' => 9]],
            ['1+2*3', 7, []],
            ['8/4*3-1', 5, []],
            ['abc * x + b', 10, ['abc' => 2, 'x' => 3, 'b' => 4]],
            ['5*-4', -20,[]],
            ['3 + 4 * 4 / abs( 3 - 5 ) ^ 2 ^ 3', 3.0625, []],
            ['(3+3)*2', 12, []],
            ['-(3+3)*2', -12, []],
            ['-(3+3)^2', -36, []],
            ['.5', 0.5, []],
            ['1.9e2', 190, []],
            ['1.9e-2', 0.019, []],
            ['5%2', 1, []],
            ['0 + 1', 1, []],
            ['0011', 11, []],
            ['1 + x', 3, ['x' => function($name) { return 2; }]]
        ];
    }
}
