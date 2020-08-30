<?php

namespace geldek\math;

class ExpressionException extends \Exception
{
    private $token;
    private $position;

    public function __construct($message, $token, $position, $code = 0, Exception $previous = null) {
        $this->token = $token;
        $this->position = $position;

        parent::__construct($message, $code, $previous);
    }

    public function getToken() {
        return $this->token;
    }

    public function getPosition() {
        return $this->position;
    }
}