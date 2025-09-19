<?php

namespace YouCast\NanoBanana\Exceptions;

/**
 * APIリクエスト関連の例外クラス
 */
class ApiRequestException extends NanoBananaException
{
    public function __construct(string $message = "APIリクエストが失敗しました", int $code = 0, ?\Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
