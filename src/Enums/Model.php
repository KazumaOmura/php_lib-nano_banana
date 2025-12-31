<?php

namespace YouCast\NanoBanana\Enums;

enum Model: string {
    case GEMINI_2_5 = 'gemini-2.5';
    case GEMINI_3 = 'gemini-3';

    public function getApiUrl(bool $is_batch = false): string
    {
        $suffix = match($is_batch) {
            true => 'batchGenerateContent',
            false => 'generateContent',
        };

        return match($this) {
            self::GEMINI_2_5 => "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:{$suffix}",
            self::GEMINI_3 => "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:{$suffix}",
        };
    }
}