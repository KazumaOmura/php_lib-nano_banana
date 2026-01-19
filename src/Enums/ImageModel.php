<?php

namespace YouCast\NanoBanana\Enums;

enum ImageModel: string 
{
    const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    // gemini-2.5-flash
    // gemini-3-pro-image-preview
    case GEMINI_2_5_FLASH_IMAGE = 'gemini-2.5-flash-image';
    case GEMINI_3_PRO_IMAGE_PREVIEW = 'gemini-3-pro-image-preview';

    public function getApiUrl(bool $is_batch = false): string
    {
        $suffix = match($is_batch) {
            true => 'batchGenerateContent',
            false => 'generateContent',
        };

        return match($this) {
            self::GEMINI_2_5_FLASH_IMAGE => self::BASE_URL . $this->value . ":{$suffix}",
            self::GEMINI_3_PRO_IMAGE_PREVIEW => self::BASE_URL . $this->value . ":{$suffix}",
        };
    }
}