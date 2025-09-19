<?php

namespace YouCast\NanoBanana\Exceptions;

/**
 * APIキー関連の例外クラス
 */
class ApiKeyException extends NanoBananaException
{
    public function __construct(string $message = "APIキーが設定されていません", int $code = 0, ?\Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}