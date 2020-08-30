<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use geldek\math\Token;

class TokenTest extends TestCase
{
    /**
     * @dataProvider validTokenTypeProvider
     */
    public function testValidTokenType($type)
    {
        $token = new Token('', $type);
        $this->assertEquals($token->type, $type);
    }

    /**
     * @dataProvider invalidTokenTypeProvider
     */
    public function testInvalidTokenType($type) {
        $this->expectException(\TypeError::class);
        $token = new Token('', $type);
    }

    public function validTokenTypeProvider() {
        return [
            [Token::NUMBER_OP],
            [Token::FUNCTION_OP],
            [Token::OPERATOR_OP],
            [Token::LEFT_PAREN_OP],
            [Token::RIGHT_PAREN_OP],
            [Token::METHOD_OP],
            [Token::UNKNOWN_OP],
        ];
    }

    public function invalidTokenTypeProvider() {
        return [
            ['cube'],
            [[]],
            [new class {}]
        ];
    }
}