<?php

namespace geldek\math;

class Expression
{
    private const HIGH_PRECEDENCE = 4;
    private const MEDIUM_PRECEDENCE = 3;
    private const LOW_PRECEDENCE = 2;
    private const RIGHT_ASSOC = 1;
    private const LEFT_ASSOC = -1;

    protected $expression;
    protected $variables;
    protected $white_list = [];
    private $position = 0;
    private $tokens = [];
    private $length = 0;
    private $output = [];
    private $operator_stack = [];

    public function __construct(string $exp, array $variables = []) {
        if(($exp === null || $exp === '')) {
            throw new \InvalidArgumentException("Expression cannot be null or empty.");
        }
        $this->expression = $exp;
        $this->setVariables($variables);
    }

    public function getVariables() {
        return $this->variables;
    }

    public function setVariables(array $variables) {
        $this->variables = [];
        foreach($variables as $name => $value) {
            $this->setVariable($name, $value);
        }

        return $this;
    }

    public function getVariable($key) {
        return array_key_exists($key, $this->variables) ? $this->variables[$key] : null;
    }

    public function setVariable(string $name, $value) {
        if(is_numeric($name)) {
            throw new \InvalidArgumentException(sprintf("Variable name (%s) cannot be a number.", $name));
        }

        $len = strlen(trim($name));
        if($len == 0) {
            throw new \InvalidArgumentException(sprintf("Variable name (%s) cannot be empty.", $name));
        }

        if(!is_numeric($value)) {
            if($value instanceof \Closure) {
                $reflection = new \ReflectionFunction($value);
                $par_count = $reflection->getNumberOfParameters();
                if($par_count != 1) {
                    throw new \InvalidArgumentException(sprintf("Variable callback can only accept 1 parameter.", $name, $value));
                }
            }
            else {
                throw new \InvalidArgumentException(sprintf("Variable (%s) value (%s) is not a number.", $name, $value));
            }
        }

        $this->variables[trim($name)] = $value;
    }

    public function setWhiteList(array $list) {
        $this->white_list = array_map(function(string $item) {
            return strtolower($item);
        }, $list);
    }

    public function getExpression() {
        return $this->expression;
    }

    public function parse(){
        $this->length = strlen($this->expression);
        $is_negative = false;
        $token = '';

        for($this->position = 0; $this->position < $this->length; $this->position++) {
            $c = $this->expression[$this->position];

            if($c == ' ' || $c == '\t' || $c == '\n' || $c == '\r') {
                continue;
            }

            //Numbers with leading dot "." are supported: .123
            if(($c >= '0' && $c <= '9') || $c == '.') {
                $token = $this->parseNumber($c, $is_negative);
                $this->addTokenToOutput($token, Token::NUMBER_OP);
                $is_negative = false;

                continue;
            }

            //we have a minus sign in front of an identifier.
            //Therefore we have to reverse the sign with "<identifier> * -1" hack.
            //It is important to resolve the sign before any other input is parsed.
            if($is_negative) {
                $this->addTokenToOutput(new Token('-1', Token::NUMBER_OP, -1));
                $this->addTokenToOperatorStack(new Token('*', Token::OPERATOR_OP, -1));
                $is_negative = false;
            }

            if($c == ',') {
                $this->tokens[] = new Token($c, Token::COMMA_OP, $this->position);
            }
            elseif($c == '$') {
                $token = $this->parseVariable($c);
                $this->addTokenToOutput($token);
            }
            elseif($c == '_' || ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z')) {
                $token = $this->parseIdentifier($c);
                switch($token->type) {
                    case Token::METHOD_OP:
                    case Token::FUNCTION_OP:
                        $this->addTokenToOperatorStack($token);
                        break;
                    case Token::VARIABLE_OP:
                        $this->addTokenToOutput($token);
                        break;
                    default:
                        throw new ExpressionException('Unknown token', $token->value, $this->position - strlen($token->value));
                }
            }
            elseif($c == '+' || $c == '-' || $c == '*' || $c == '/' || $c == '^' || $c == '%') {
                if($c == '-') {//for cases where "-" means "negative number": -1+2 or 2*-1 or 3*-(5+3)
                    $last_token = end($this->tokens);
                    if($last_token === false || $last_token->type === Token::OPERATOR_OP || $last_token->type === Token::COMMA_OP) {
                        $is_negative = true;
                        continue;
                    }
                }

                $this->resolveOperatorPrecedence($c);
                $this->addTokenToOperatorStack(new Token($c, Token::OPERATOR_OP, $this->position));
            }
            elseif($c == '(') {
                $this->addTokenToOperatorStack(new Token($c, Token::LEFT_PAREN_OP, $this->position));
            }
            elseif($c == ')') {
                $t = new Token($c, Token::RIGHT_PAREN_OP, $this->position);
                $this->tokens[] = $t;
                $this->resolveParentheses($c);
            }
            else {
                throw new ExpressionException('Unknown token.', $c, $this->position);
            }
        }

        //push remainding operators to output queue
        $stack_count = count($this->operator_stack);
        for($l = $stack_count - 1; $l >= 0; $l--) {
            $this->output[] = array_pop($this->operator_stack);
        }

        return $this->tokens;
    }

    public function calculate() {
        if(empty($this->output)) {
            return null;
        }

        $len = count($this->output);
        $stack = [];
        for($i = 0; $i < $len; $i++) {
            $token = $this->output[$i];
            if($token->type == Token::NUMBER_OP) {
                $stack[] = $token->value;
            }
            elseif($token->type == Token::VARIABLE_OP) {
                $value = $this->variables[$token->value];
                if($value instanceof \Closure) {
                    if(!empty($this->white_list) && !in_array(strtolower($value), $this->white_list)) {
                        throw new ExpressionException('Function is blacklisted.', $token->value, $token->position);
                    }
                    else {
                        $result = $value($token->value);
                        if(!is_numeric($result)) {
                            throw new ExpressionException('Result of variable function callback is not a number.', $token->value, $token->position);
                        }
                    }
                }
                else {
                    $result = $this->variables[$token->value];
                }

                $stack[] = $result;
            }
            elseif($token->type == Token::OPERATOR_OP) {
                $var2 = array_pop($stack);
                $var1 = array_pop($stack);
                //TODO: probably invalid expression
                //$var2 = $var2 === null ? 0 : $var2;
                //$var1 = $var1 === null ? 0 : $var1;
                if($var1 === null || $var2 === null) {
                    throw new ExpressionException(sprintf("Invalid expression at position (%s).", $token->position), $token->value, $token->position);
                }
                if($token->value == '+') {
                    $stack[] = $var1 + $var2;
                }
                elseif($token->value == '-') {
                    $stack[] = $var1 - $var2;
                }
                elseif($token->value == '*') {
                    $stack[] = $var1 * $var2;
                }
                elseif($token->value == '/') {
                    $stack[] = $var1 / $var2;
                }
                elseif($token->value == '^') {
                    $stack[] = pow($var1, $var2);
                }
                elseif($token->value == '%') {
                    $stack[] = $var1 % $var2;
                }
                else {
                    throw new ExpressionException(sprintf("Unsupported operator (%s) of type (%s).", $token->value, $token->type), $token->value, $token->position);
                }
            }
            elseif($token->type == Token::METHOD_OP) {
                $pars = [];
                $reflection = new \ReflectionMethod($this, $token->value);
                $par_count = $reflection->getNumberOfParameters();
                for($j= 0; $j < $par_count; $j++) {
                    $pars[] = array_pop($stack);
                }
                $func = $token->value;
                if(empty($pars)) {
                    $stack[] = $this->$func();
                }
                else {
                    $stack[] = call_user_func_array([$this, $func], array_reverse($pars));
                }
            }
            elseif($token->type == Token::FUNCTION_OP) {
                if(!empty($this->white_list) && !in_array(strtolower($token->value), $this->white_list)) {
                    throw new ExpressionException('Function is blacklisted.', $token->value, $token->position);
                }

                $reflection = new \ReflectionFunction($token->value);
                $par_count = $reflection->getNumberOfParameters();
                $pars = [];
                for($j = 0; $j < $par_count; $j++) {
                    $pars[] = array_pop($stack);
                }

                //$par1 = array_pop($stack);
                $func = $token->value;
                //$stack[] = $func($par1);
                if(empty($pars)) {
                    $stack[] = $func();
                }
                else {
                    $result = call_user_func_array($token->value, array_reverse($pars));
                    if($result === false) {
                        throw new ExpressionException('Function call failed.', $token->value, $token->position);
                    }
                    $stack[] = $result;
                }
            }
            elseif($token->type == Token::UNKNOWN_OP) {
                if($token->value == ')') {
                    $message = 'Mismatched parentheses.';
                }
                else {
                    $message = 'Unknown token.';
                }
                throw new ExpressionException($message, $token->value, $token->position);
            }
            else {
                throw new ExpressionException('Unknown token.', $token->value, $token->position);
            }
        }

        return array_pop($stack);
    }

    private function parseNumber($current, $is_negative = false) {
        $pos = $this->position;
        $peek = $this->peek(2);

        if($peek == '0x' || $peek == '0X') {
            return new Token($this->parseHexNumber(), Token::NUMBER_OP, $pos);
        }

        if($peek == '0b' || $peek == '0B') {
            return new Token($this->parseBinNumber(), Token::NUMBER_OP, $pos);
        }

        $result = '';
        $is_decimal = false;

        if($current == '.') {
            $result = '0.';
            ++$this->position;
            $is_decimal = true;
        }

        for(; $this->position < $this->length; $this->position++) {
            $c = $this->expression[$this->position];
            if($c >= '0' && $c <= '9') {
                $result .= $c;
                continue;
            }

            if($c == '.') {
                if($is_decimal === false) {
                    $result .= $c;
                    $is_decimal = true;
                    continue;
                }
                else {
                    throw new ExpressionException("Multiple decimal points are not allowed.", $result, $this->position);
                }
            }

            if($c == 'e' || $c == 'E') {
                $exponent = $this->parseExponent($result);
                return new Token(pow(10, $exponent) * $result, Token::NUMBER_OP, $pos);
            }

        break;
        }

        $this->position -= 1;

        return new Token(($is_negative === false ? $result : -$result), Token::NUMBER_OP, $pos);
    }

    private function parseHexNumber() {
        $result = '';
        $this->position += 2;//skip 0x prefix
        for(; $this->position < $this->length; $this->position++) {
            $c = $this->expression[$this->position];
            if($c >= '0' && $c <= '9' || $c >= 'a' && $c <= 'f' || $c >= 'A' && $c <= 'F') {
                $result .= $c;
            }
            else {
            break;
            }
        }

        $this->position -= 1;

        return base_convert($result, 16, 10);
    }

    private function parseBinNumber() {
        $result = '';
        $this->position += 2;//skip 0x prefix
        for(; $this->position < $this->length; $this->position++) {
            $c = $this->expression[$this->position];
            if($c >= '0' && $c <= '1') {
                $result .= $c;
            }
            else {
            break;
            }
        }

        $this->position -= 1;

        return base_convert($result, 2, 10);
    }

    private function parseExponent($buffer) {
        $tmp_pos = $this->position + 1;
        if($tmp_pos >= $this->length) {//no exponent found: 1.2E
            throw new ExpressionException("Invalid scientific notation.", $buffer . $this->expression[$this->position], $this->position);
        }

        $exponent = '';
        if($this->expression[$tmp_pos] == '-') {
            $exponent .= '-';
            ++$tmp_pos;
        }
        
        if($tmp_pos >= $this->length) {//no exponent found: 1.2E-
            throw new ExpressionException("Invalid scientific notation.", $buffer . $this->expression[$this->position] . $exponent, $this->position);
        }

        if($this->expression[$tmp_pos] == '0') {//leading zeros are not allowed
            throw new ExpressionException("Leading zeroes in exponent are not allowed.", $buffer . $this->expression[$this->position] . $exponent, $this->position);
        }

        for(;$tmp_pos < $this->length; $tmp_pos++) {
            $c = $this->expression[$tmp_pos];
            if($c >= '0' && $c <= '9') {
                $exponent .= $c;
            }
            else {
            break;
            }
        }

        $this->position = $tmp_pos - 1;

        return $exponent;
    }

    private function parseIdentifier() {
        $pos = $this->position;
        $result = '';
        $type = Token::UNKNOWN_OP;

        for(; $this->position < $this->length; $this->position++) {
            $c = $this->expression[$this->position];
            if($c == '_' || ($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')) {
                $result .= $c;
            }
            else {
            break;
            }
        }

        if($this->position >= $this->length && isset($this->variables[$result])) {
            return new Token($result, Token::VARIABLE_OP, $pos);
        }

        for($paren_pos = $this->position; $paren_pos < $this->length; $paren_pos++) {
            $c = $this->expression[$paren_pos];
            if($c == ' ' || $c == '\t' || $c == '\r' || $c == '\n') {
                continue;
            }
            if($c == '(') {
                if(method_exists($this, $result)) {
                    $type = Token::METHOD_OP;
                }
                elseif(function_exists($result)) {
                    $type = Token::FUNCTION_OP;
                }
                else {
                    throw new ExpressionException('Unknown function.', $result, $pos);
                }

                break;
            }
            else {
                if(isset($this->variables[$result])) {
                    $type = Token::VARIABLE_OP;
                }
                else {
                    throw new ExpressionException('Variable not defined.', $result, $pos);
                }

                break;
            }
        }

        $this->position += $paren_pos - $this->position - 1;

        return new Token($result, $type, $pos);
    }

    private function peek($len = 1) {
        if($len < 1 || $this->position + $len >= $this->length) {
            return null;
        }

        return substr($this->expression, $this->position, $len);
    }

    private function addTokenToOutput(Token $token) {
        $this->tokens[] = $token;
        $this->output[] = $token;
    }

    private function addTokenToOperatorStack(Token $token) {
        $this->tokens[] = $token;
        $this->operator_stack[] = $token;
    }

    private function resolveOperatorPrecedence($c) {
        $rules = [
            '^' => ['precedence' => Expression::HIGH_PRECEDENCE, 'asoc' => Expression::RIGHT_ASSOC],
            '*' => ['precedence' => Expression::MEDIUM_PRECEDENCE, 'asoc' => Expression::LEFT_ASSOC],
            '/' => ['precedence' => Expression::MEDIUM_PRECEDENCE, 'asoc' => Expression::LEFT_ASSOC],
            '%' => ['precedence' => Expression::MEDIUM_PRECEDENCE, 'asoc' => Expression::LEFT_ASSOC],
            '+' => ['precedence' => Expression::LOW_PRECEDENCE, 'asoc' => Expression::LEFT_ASSOC],
            '-' => ['precedence' => Expression::LOW_PRECEDENCE, 'asoc' => Expression::LEFT_ASSOC],
        ];

        $stack_count = count($this->operator_stack);
        for($k = $stack_count - 1; $k >= 0; $k--) {
            $op = end($this->operator_stack);
            $op_rule = array_key_exists($op->value, $rules) ? $rules[$op->value] : null;
            $token_rule = array_key_exists($c, $rules) ? $rules[$c] : null;

            if(
                (
                    $op->type == Token::FUNCTION_OP
                    || $op->type == Token::METHOD_OP
                    || $op_rule['precedence'] > $token_rule['precedence']
                    || ($op_rule['precedence'] == $token_rule['precedence'] && $token_rule['asoc'] == Expression::LEFT_ASSOC)

                )
                && ($op->type != Token::LEFT_PAREN_OP)) {
                    $this->output[] = array_pop($this->operator_stack);
            }
            else {
                break;
            }
        }
    }

    private function resolveParentheses($c) {
        $stack_count = count($this->operator_stack);
        for($j = $stack_count - 1; $j >= -1; $j--) {
            if($j == -1) {
                throw new ExpressionException('Parenthesis mismatch.', $c, $this->position);
            }

            $op = array_pop($this->operator_stack);
            if($op->type !== Token::LEFT_PAREN_OP) {
                $this->output[] = $op;
            }
            else {
                break;
            }
        }
    }
}
