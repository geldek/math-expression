<?php

namespace geldek\math;

class Token
{
    public const NUMBER_OP = 1;
    public const FUNCTION_OP = 2;
    public const OPERATOR_OP = 3;
    public const LEFT_PAREN_OP = 4;
    public const RIGHT_PAREN_OP = 5;
    public const METHOD_OP = 6;
    public const VARIABLE_OP = 7;
    public const CONSTANT_OP = 8;
    public const IDENTIFIER_OP = 9;
    public const COMMA_OP = 10;
    public const UNKNOWN_OP = 11;

    public $value;
    public $type;
    public $position;

    public function __construct($value, int $type, int $position = 0) {
        $this->position = $position;
        $this->value = $value;
        switch($type) {
            case Token::NUMBER_OP:
            case Token::FUNCTION_OP:
            case Token::OPERATOR_OP:
            case Token::LEFT_PAREN_OP:
            case Token::RIGHT_PAREN_OP:
            case Token::METHOD_OP:
            case Token::IDENTIFIER_OP:
            case Token::VARIABLE_OP:
            case Token::COMMA_OP:
                $this->type = $type;
                break;
            default:
                $this->type = Token::UNKNOWN_OP;
                break;
        }
    }
}