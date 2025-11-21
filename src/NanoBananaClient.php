<?php

namespace YouCast\NanoBanana;

use YouCast\NanoBanana\Enums\Model;
use YouCast\NanoBanana\Exceptions\ApiKeyException;
use YouCast\NanoBanana\Exceptions\ApiRequestException;
use YouCast\NanoBanana\Exceptions\ImageProcessingException;
use YouCast\NanoBanana\Exceptions\FileOperationException;
use YouCast\NanoBanana\Builders\PhotographyPromptBuilder;
use YouCast\NanoBanana\Builders\StickerPromptBuilder;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini APIを使用して画像を生成するクライアントクラス
 */
class NanoBananaClient
{
    public function __construct(
        private string $api_key, 
        private Model $model,
        private bool $is_image_validation = true
    )
    {
    }

    /**
     * Gemini APIを使用して画像を生成し、Base64デコードしてファイルに保存する
     *
     * @param string $prompt 画像生成のプロンプト
     * @param string $output_path 出力ファイルパス
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function generateImage(string $prompt, string $output_path): bool
    {
        try {
            // APIリクエストの準備
            $request_data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];

            // APIリクエストの実行
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ])->timeout(120) // タイムアウトを120秒に設定
              ->post($this->model->getApiUrl(), $request_data);

            if (!$response->successful()) {
                throw new ApiRequestException(
                    'APIリクエストが失敗しました: ' . $response->status() . ' - ' . $response->body(),
                    0,
                    null,
                    [
                        'status_code' => $response->status(),
                        'response_body' => $response->body(),
                        'prompt' => $prompt
                    ]
                );
            }

            $response_data = $response->json();

            // レスポンスからBase64データを抽出
            $base64_data = $this->extractBase64Data($response_data);

            if (empty($base64_data)) {
                throw new ImageProcessingException(
                    'レスポンスからBase64データを抽出できませんでした',
                    0,
                    null,
                    [
                        'response_data' => $response_data,
                        'prompt' => $prompt
                    ]
                );
            }

            // Base64デコードしてファイルに保存
            $image_data = base64_decode($base64_data);
            if ($image_data === false) {
                throw new ImageProcessingException(
                    'Base64デコードに失敗しました',
                    0,
                    null,
                    [
                        'base64_data_length' => strlen($base64_data),
                        'prompt' => $prompt
                    ]
                );
            }

            // ディレクトリが存在しない場合は作成
            $output_dir = dirname($output_path);
            if (!is_dir($output_dir)) {
                if (!mkdir($output_dir, 0755, true)) {
                    throw new FileOperationException(
                        '出力ディレクトリの作成に失敗しました: ' . $output_dir,
                        0,
                        null,
                        [
                            'output_dir' => $output_dir,
                            'prompt' => $prompt
                        ]
                    );
                }
            }

            // ファイルに保存
            $result = file_put_contents($output_path, $image_data);
            if ($result === false) {
                throw new FileOperationException(
                    'ファイルの保存に失敗しました: ' . $output_path,
                    0,
                    null,
                    [
                        'output_path' => $output_path,
                        'image_data_size' => strlen($image_data),
                        'prompt' => $prompt
                    ]
                );
            }

            return true;
        } catch (ApiKeyException | ApiRequestException | ImageProcessingException | FileOperationException $e) {
            // カスタム例外はそのまま再スロー
            throw $e;
        } catch (\Exception $e) {
            // その他の例外はApiRequestExceptionとしてラップ
            throw new ApiRequestException(
                '予期しないエラーが発生しました: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                [
                    'prompt' => $prompt,
                    'output_path' => $output_path,
                    'original_error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * デフォルトのナノバナナ画像を生成する
     *
     * @param string $output_path 出力ファイルパス
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function generateNanoBananaImage(string $output_path): bool
    {
        $prompt = 'Create a picture of a nano banana dish in a fancy restaurant with a Gemini theme';
        return $this->generateImage($prompt, $output_path);
    }

    /**
     * 画像を編集する（テキストと画像による画像変換）
     *
     * @param string $prompt 画像編集のプロンプト
     * @param string $image_path 編集する画像のパス
     * @param string $output_path 出力ファイルパス
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function editImage(string $prompt, string $image_path, string $output_path): bool
    {
        try {
            // 画像ファイルの存在確認
            if (!file_exists($image_path)) {
                throw new FileOperationException(
                    '画像ファイルが見つかりません: ' . $image_path,
                    0,
                    null,
                    ['image_path' => $image_path]
                );
            }

            // 画像ファイルの読み込み
            $image_data = file_get_contents($image_path);
            if ($image_data === false) {
                throw new FileOperationException(
                    '画像ファイルの読み込みに失敗しました: ' . $image_path,
                    0,
                    null,
                    ['image_path' => $image_path]
                );
            }

            // 画像のMIMEタイプを取得
            $mime_type = $this->getMimeType($image_path);
            if (empty($mime_type)) {
                if ($this->is_image_validation) {
                    throw new ImageProcessingException(
                        'サポートされていない画像形式です: ' . $image_path,
                        0,
                        null,
                        ['image_path' => $image_path]
                    );
                }
                return false;
            }

            // 画像をBase64エンコード
            $base64_image = base64_encode($image_data);

            // APIリクエストの準備
            $request_data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime_type,
                                    'data' => $base64_image
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // APIリクエストの実行
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->api_key,
                'Content-Type' => 'application/json'
            ])->post($this->model->getApiUrl(), $request_data);

            if (!$response->successful()) {
                throw new ApiRequestException(
                    'APIリクエストが失敗しました: ' . $response->status() . ' - ' . $response->body(),
                    0,
                    null,
                    [
                        'status_code' => $response->status(),
                        'response_body' => $response->body(),
                        'prompt' => $prompt,
                        'image_path' => $image_path
                    ]
                );
            }

            $response_data = $response->json();

            // レスポンスからBase64データを抽出
            $base64_data = $this->extractBase64Data($response_data);

            if (empty($base64_data)) {
                throw new ImageProcessingException(
                    'レスポンスからBase64データを抽出できませんでした',
                    0,
                    null,
                    [
                        'response_data' => $response_data,
                        'prompt' => $prompt,
                        'image_path' => $image_path
                    ]
                );
            }

            // Base64デコードしてファイルに保存
            $edited_image_data = base64_decode($base64_data);
            if ($edited_image_data === false) {
                throw new ImageProcessingException(
                    'Base64デコードに失敗しました',
                    0,
                    null,
                    [
                        'base64_data_length' => strlen($base64_data),
                        'prompt' => $prompt,
                        'image_path' => $image_path
                    ]
                );
            }

            // ディレクトリが存在しない場合は作成
            $output_dir = dirname($output_path);
            if (!is_dir($output_dir)) {
                if (!mkdir($output_dir, 0755, true)) {
                    throw new FileOperationException(
                        '出力ディレクトリの作成に失敗しました: ' . $output_dir,
                        0,
                        null,
                        [
                            'output_dir' => $output_dir,
                            'prompt' => $prompt,
                            'image_path' => $image_path
                        ]
                    );
                }
            }

            // ファイルに保存
            $result = file_put_contents($output_path, $edited_image_data);
            if ($result === false) {
                throw new FileOperationException(
                    'ファイルの保存に失敗しました: ' . $output_path,
                    0,
                    null,
                    [
                        'output_path' => $output_path,
                        'image_data_size' => strlen($edited_image_data),
                        'prompt' => $prompt,
                        'image_path' => $image_path
                    ]
                );
            }

            return true;
        } catch (ApiKeyException | ApiRequestException | ImageProcessingException | FileOperationException $e) {
            // カスタム例外はそのまま再スロー
            throw $e;
        } catch (\Exception $e) {
            // その他の例外はApiRequestExceptionとしてラップ
            throw new ApiRequestException(
                '予期しないエラーが発生しました: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                [
                    'prompt' => $prompt,
                    'image_path' => $image_path,
                    'output_path' => $output_path,
                    'original_error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * 画像ファイルのMIMEタイプを取得する
     *
     * @param string $image_path 画像ファイルのパス
     * @return string|null MIMEタイプ
     */
    private function getMimeType(string $image_path): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $image_path);
        finfo_close($finfo);

        // サポートされているMIMEタイプ
        $supported_types = [
            'image/jpeg' => 'image/jpeg',
            'image/jpg' => 'image/jpeg',
            'image/png' => 'image/png',
            'image/gif' => 'image/gif',
            'image/webp' => 'image/webp'
        ];

        return $supported_types[$mime_type] ?? null;
    }

    /**
     * レスポンスからBase64データを抽出する
     *
     * @param array $response_data APIレスポンスデータ
     * @return string|null Base64データ
     */
    private function extractBase64Data(array $response_data): ?string
    {
        // レスポンス構造に応じてBase64データを抽出
        if (isset($response_data['candidates'][0]['content']['parts'][0]['data'])) {
            return $response_data['candidates'][0]['content']['parts'][0]['data'];
        }

        // 別の構造の場合の対応
        if (isset($response_data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
            return $response_data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        }

        // 正規表現で"data": "..."の形式を検索
        $response_json = json_encode($response_data);
        if (preg_match('/"data":\s*"([^"]+)"/', $response_json, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * APIキーを設定する
     *
     * @param string $api_key
     */
    public function setApiKey(string $api_key): void
    {
        $this->api_key = $api_key;
    }

    /**
     * 現在のAPIキーを取得する（マスク済み）
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return substr($this->api_key, 0, 8) . '...' . substr($this->api_key, -4);
    }

    /**
     * フォトリアリスティックな画像を生成する
     *
     * @param string $subject 被写体
     * @param string $output_path 出力ファイルパス
     * @param array $photography_params 写真用語のパラメータ
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function generatePhotorealisticImage(string $subject, string $output_path, array $photography_params = []): bool
    {
        $builder = new PhotographyPromptBuilder();
        $builder->setSubject($subject);

        // 写真用語のパラメータを適用
        if (isset($photography_params['camera_angle'])) {
            $builder->setCameraAngle($photography_params['camera_angle']);
        }
        if (isset($photography_params['lens_type'])) {
            $builder->setLensType($photography_params['lens_type']);
        }
        if (isset($photography_params['lighting'])) {
            $builder->setLighting($photography_params['lighting']);
        }
        if (isset($photography_params['mood'])) {
            $builder->setMood($photography_params['mood']);
        }
        if (isset($photography_params['background'])) {
            $builder->setBackground($photography_params['background']);
        }
        if (isset($photography_params['style'])) {
            $builder->setStyle($photography_params['style']);
        }
        if (isset($photography_params['details']) && is_array($photography_params['details'])) {
            foreach ($photography_params['details'] as $detail) {
                $builder->addDetail($detail);
            }
        }
        if (isset($photography_params['quality'])) {
            $builder->setQuality($photography_params['quality']);
        }
        if (isset($photography_params['preset'])) {
            $builder->applyPreset($photography_params['preset']);
        }

        $prompt = $builder->build();
        return $this->generateImage($prompt, $output_path);
    }

    /**
     * プリセットを使用してフォトリアリスティックな画像を生成する
     *
     * @param string $subject 被写体
     * @param string $output_path 出力ファイルパス
     * @param string $preset プリセット名 (portrait, landscape, macro, street, studio)
     * @param array $additional_params 追加のパラメータ
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function generatePhotorealisticImageWithPreset(string $subject, string $output_path, string $preset, array $additional_params = []): bool
    {
        $params = array_merge(['preset' => $preset], $additional_params);
        return $this->generatePhotorealisticImage($subject, $output_path, $params);
    }

    /**
     * フォトリアリスティックな画像を編集する
     *
     * @param string $subject 被写体の説明
     * @param string $image_path 編集する画像のパス
     * @param string $output_path 出力ファイルパス
     * @param array $photography_params 写真用語のパラメータ
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function editPhotorealisticImage(string $subject, string $image_path, string $output_path, array $photography_params = []): bool
    {
        $builder = new PhotographyPromptBuilder();
        $builder->setSubject($subject);

        // 写真用語のパラメータを適用
        if (isset($photography_params['camera_angle'])) {
            $builder->setCameraAngle($photography_params['camera_angle']);
        }
        if (isset($photography_params['lens_type'])) {
            $builder->setLensType($photography_params['lens_type']);
        }
        if (isset($photography_params['lighting'])) {
            $builder->setLighting($photography_params['lighting']);
        }
        if (isset($photography_params['mood'])) {
            $builder->setMood($photography_params['mood']);
        }
        if (isset($photography_params['background'])) {
            $builder->setBackground($photography_params['background']);
        }
        if (isset($photography_params['style'])) {
            $builder->setStyle($photography_params['style']);
        }
        if (isset($photography_params['details']) && is_array($photography_params['details'])) {
            foreach ($photography_params['details'] as $detail) {
                $builder->addDetail($detail);
            }
        }
        if (isset($photography_params['quality'])) {
            $builder->setQuality($photography_params['quality']);
        }
        if (isset($photography_params['preset'])) {
            $builder->applyPreset($photography_params['preset']);
        }

        $prompt = $builder->build();
        return $this->editImage($prompt, $image_path, $output_path);
    }

    /**
     * ステッカーを生成する
     *
     * @param string $subject ステッカーの被写体
     * @param string $output_path 出力ファイルパス
     * @param array $sticker_params ステッカーのパラメータ
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function generateSticker(string $subject, string $output_path, array $sticker_params = []): bool
    {
        $builder = new StickerPromptBuilder();
        $builder->setSubject($subject);

        // ステッカーパラメータを適用
        if (isset($sticker_params['style'])) {
            $builder->setStyle($sticker_params['style']);
        }
        if (isset($sticker_params['background'])) {
            $builder->setBackground($sticker_params['background']);
        }
        if (isset($sticker_params['outline'])) {
            $builder->setOutline($sticker_params['outline']);
        }
        if (isset($sticker_params['shading'])) {
            $builder->setShading($sticker_params['shading']);
        }
        if (isset($sticker_params['color_palette'])) {
            $builder->setColorPalette($sticker_params['color_palette']);
        }
        if (isset($sticker_params['size'])) {
            $builder->setSize($sticker_params['size']);
        }
        if (isset($sticker_params['mood'])) {
            $builder->setMood($sticker_params['mood']);
        }
        if (isset($sticker_params['details']) && is_array($sticker_params['details'])) {
            foreach ($sticker_params['details'] as $detail) {
                $builder->addDetail($detail);
            }
        }
        if (isset($sticker_params['preset'])) {
            $builder->applyPreset($sticker_params['preset']);
        }

        $prompt = $builder->build();
        return $this->generateImage($prompt, $output_path);
    }

    /**
     * イラストを生成する
     *
     * @param string $subject イラストの被写体
     * @param string $output_path 出力ファイルパス
     * @param array $illustration_params イラストのパラメータ
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function generateIllustration(string $subject, string $output_path, array $illustration_params = []): bool
    {
        // デフォルトのイラストパラメータ
        $default_params = [
            'style' => 'anime',
            'background' => 'detailed',
            'quality' => 'high',
            'mood' => 'cheerful',
            'composition' => 'balanced'
        ];

        $params = array_merge($default_params, $illustration_params);

        // イラストプロンプトを構築
        $prompt = $this->buildIllustrationPrompt($subject, $params);

        return $this->generateImage($prompt, $output_path);
    }


    /**
     * プロンプトファイルを使用して画像を編集する
     *
     * @param string $prompt_file_path プロンプトファイルのパス（./Prompt/からの相対パス）
     * @param string $image_path 編集する画像のパス
     * @param string $output_path 出力ファイルパス
     * @return bool 成功した場合true
     * @throws ApiKeyException
     * @throws ApiRequestException
     * @throws ImageProcessingException
     * @throws FileOperationException
     */
    public function editImageWithPromptFile(string $prompt_file_path, string $image_path, string $output_path): bool
    {
        try {
            // プロンプトファイルの完全パスを構築
            $full_prompt_path = __DIR__ . '/Prompt/' . ltrim($prompt_file_path, './');

            // プロンプトファイルの存在確認
            if (!file_exists($full_prompt_path)) {
                throw new FileOperationException(
                    'プロンプトファイルが見つかりません: ' . $full_prompt_path,
                    0,
                    null,
                    ['prompt_file_path' => $full_prompt_path]
                );
            }

            // プロンプトファイルの読み込み
            $prompt = file_get_contents($full_prompt_path);
            if ($prompt === false) {
                throw new FileOperationException(
                    'プロンプトファイルの読み込みに失敗しました: ' . $full_prompt_path,
                    0,
                    null,
                    ['prompt_file_path' => $full_prompt_path]
                );
            }

            // プロンプトが空でないことを確認
            $prompt = trim($prompt);
            if (empty($prompt)) {
                throw new FileOperationException(
                    'プロンプトファイルが空です: ' . $full_prompt_path,
                    0,
                    null,
                    ['prompt_file_path' => $full_prompt_path]
                );
            }

            // editImageメソッドを呼び出し
            return $this->editImage($prompt, $image_path, $output_path);
        } catch (ApiKeyException | ApiRequestException | ImageProcessingException | FileOperationException $e) {
            // カスタム例外はそのまま再スロー
            throw $e;
        } catch (\Exception $e) {
            // その他の例外はFileOperationExceptionとしてラップ
            throw new FileOperationException(
                '予期しないエラーが発生しました: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                [
                    'prompt_file_path' => $prompt_file_path,
                    'image_path' => $image_path,
                    'output_path' => $output_path,
                    'original_error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * イラストプロンプトを構築する
     *
     * @param string $subject 被写体
     * @param array $params パラメータ
     * @return string プロンプト
     */
    private function buildIllustrationPrompt(string $subject, array $params): string
    {
        $style = $params['style'] ?? 'anime';
        $background = $params['background'] ?? 'detailed';
        $quality = $params['quality'] ?? 'high';
        $mood = $params['mood'] ?? 'cheerful';
        $composition = $params['composition'] ?? 'balanced';

        $prompt = "A {$quality} quality {$style}-style illustration of {$subject}. ";

        // スタイルの詳細を追加
        if ($style === 'anime') {
            $prompt .= "The illustration features anime/manga art style with detailed character design. ";
        } elseif ($style === 'realistic') {
            $prompt .= "The illustration features realistic art style with detailed textures and lighting. ";
        } elseif ($style === 'cartoon') {
            $prompt .= "The illustration features cartoon art style with simplified but expressive design. ";
        } elseif ($style === 'watercolor') {
            $prompt .= "The illustration features watercolor art style with soft, flowing colors. ";
        }

        // ムードの指定
        $prompt .= "The overall mood should be {$mood}. ";

        // 背景の指定
        if ($background === 'transparent') {
            $prompt .= "The background must be transparent. ";
        } elseif ($background === 'detailed') {
            $prompt .= "Include a detailed, atmospheric background that complements the subject. ";
        } else {
            $prompt .= "The background should be {$background}. ";
        }

        // 構図の指定
        $prompt .= "Use a {$composition} composition with good visual balance. ";

        // 品質の指定
        if ($quality === 'high') {
            $prompt .= "The illustration should be highly detailed with professional quality rendering. ";
        }

        $prompt .= "This should be suitable for use as a digital illustration, artwork, or visual asset.";

        return $prompt;
    }
}
