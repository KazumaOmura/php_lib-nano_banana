# NanoBanana Client

Google Gemini APIを使用して画像を生成するPHPライブラリです。

## 機能

- Google Gemini APIを使用した画像生成
- Base64デコードとファイル保存
- カスタム例外処理
- エラーハンドリングとコンテキスト情報

## インストール

### VCSリポジトリを使用したインストール

composer.jsonファイルに以下の設定を追加してください：

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/KazumaOmura/php_lib-nano_banana"
        }
    ],
    "require": {
        "youcast/nano-banana-client": "^1.0"
    }
}
```

その後、以下のコマンドを実行してください：

```bash
composer install
```

## 使用方法

### 基本的な使用方法

```php
use YouCast\NanoBanana\NanoBananaClient;
use YouCast\NanoBanana\Exceptions\NanoBananaException;

try {
    $client = new NanoBananaClient('your-api-key');
    $client->generateNanoBananaImage('output.png');
} catch (NanoBananaException $e) {
    echo 'エラー: ' . $e->getMessage();
    echo 'コンテキスト: ' . json_encode($e->getContext());
}
```

### カスタムプロンプトでの画像生成

```php
use YouCast\NanoBanana\NanoBananaClient;

$client = new NanoBananaClient();
$client->generateImage('Create a beautiful sunset landscape', 'sunset.png');
```

### 環境変数を使用

```bash
export GEMINI_API_KEY=your-api-key-here
```

```php
$client = new NanoBananaClient(); // 環境変数から自動取得
```

## 例外処理

このライブラリは以下のカスタム例外を提供します：

- `NanoBananaException`: 基底例外クラス
- `ApiKeyException`: APIキー関連のエラー
- `ApiRequestException`: APIリクエスト関連のエラー
- `ImageProcessingException`: 画像処理関連のエラー
- `FileOperationException`: ファイル操作関連のエラー

## 要件

- PHP 8.1以上
- Illuminate/Support 9.0以上
- GuzzleHttp/Guzzle 7.0以上

## ライセンス

MIT License

## 開発者

YouCast
