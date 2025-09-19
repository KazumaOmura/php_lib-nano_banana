<?php

namespace YouCast\NanoBanana\Exceptions;

use Exception;

/**
 * NanoBananaライブラリの基底例外クラス
 */
class NanoBananaException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * コンテキスト情報を取得する
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * コンテキスト情報を設定する
     *
     * @param array $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}