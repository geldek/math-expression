# Simple expression parser and evaluator

PHP parser and evaluator library for mathematical expressions. The library
implements following features:

* expression parsing and evaluation
* hexadecimal, binary and scientific notation number parsing
* variables
* using anonymous functions to resolve variable values at runtime
* executing custom php functions
* extending the Expression class for executing custom methods
* function whitelist

The library does not support functions with variable number of arguments.
It is an implementation of [Shunting-yard algorithm](https://en.wikipedia.org/wiki/Shunting-yard_algorithm)
as described by Wikipedia.

## Installation

Use composer to install the package.

```bash
    composer require geldek/math-expression
```

## Usage

Following code evaluates this expression:
__(.1 + 2.9e3)^2 * 3 / -cos(0) + 0x1F % 0b11__

```php
    use geldek\math\Expression;
    use geldek\math\ExpressionException;
    use geldek\math\Token;

    $expression = new Expression('(.1 + 2.9e3)^2 * 3 / -cos(0) + 0x1F % 0b11');
    $tokens = $expression->parse();
    $result = $expression->calculate();
    var_dump($result);
```

Following code is using variables and closures to calculate the variable value
when calculating the expression.

```php
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
```

Following code is using custom php function.

```php
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
```

Following code is extending the Expression class to call custom method.

```php
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
```

Following code handles invalid expression by catching ExpressionException.
Besides the error message you can get the token value and position that
caused the error.

```php
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
        $token = $ex->getToken();
        $position = $ex->getPosition();
    }
```

Following code will throw and exception, because the function in the expression
is not whitelisted.

```php
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
        $token = $ex->getToken();
        $position = $ex->getPosition();
    }
```

## Running tests

If you install dev dependencies you can run the test as follows:

```cmd
    .\vendor\bin\phpunit tests
```
