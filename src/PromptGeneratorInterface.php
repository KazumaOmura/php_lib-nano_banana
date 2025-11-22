<?php

namespace YouCast\NanoBanana;

/**
 * プロンプトジェネレーターのインターフェース
 */
interface PromptGeneratorInterface
{
    /**
     * プロンプトを生成
     */
    public function generate(): string;

    /**
     * 変数を設定
     */
    public function setVariable(string $key, string $value): self;

    /**
     * 複数の変数を一括設定
     */
    public function setVariables(array $variables): self;

    /**
     * 変数を取得
     */
    public function getVariable(string $key): ?string;

    /**
     * 全変数を取得
     */
    public function getAllVariables(): array;

    /**
     * テンプレートを検証
     */
    public function validate(): bool;
}

/**
 * テンプレートのインターフェース
 */
interface PromptTemplateInterface
{
    /**
     * テンプレート文字列を取得
     */
    public function getTemplate(): string;

    /**
     * デフォルト変数を取得
     */
    public function getDefaultVariables(): array;

    /**
     * 必須変数のリストを取得
     */
    public function getRequiredVariables(): array;

    /**
     * テンプレート名を取得
     */
    public function getName(): string;

    /**
     * テンプレートの説明を取得
     */
    public function getDescription(): string;
}

/**
 * 抽象プロンプトジェネレータークラス
 */
abstract class AbstractPromptGenerator implements PromptGeneratorInterface
{
    protected array $variables = [];
    protected PromptTemplateInterface $template;

    public function __construct(PromptTemplateInterface $template)
    {
        $this->template = $template;
        $this->variables = $template->getDefaultVariables();
    }

    /**
     * {@inheritdoc}
     */
    public function setVariable(string $key, string $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setVariables(array $variables): self
    {
        $this->variables = array_merge($this->variables, $variables);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariable(string $key): ?string
    {
        return $this->variables[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllVariables(): array
    {
        return $this->variables;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): bool
    {
        $requiredVariables = $this->template->getRequiredVariables();
        
        foreach ($requiredVariables as $required) {
            if (!isset($this->variables[$required]) || empty($this->variables[$required])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(): string
    {
        if (!$this->validate()) {
            throw new RuntimeException('必須変数が設定されていません');
        }

        return $this->replaceVariables($this->template->getTemplate());
    }

    /**
     * テンプレート内の変数を置換
     */
    protected function replaceVariables(string $template): string
    {
        foreach ($this->variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }

    /**
     * テンプレート名を取得
     */
    public function getTemplateName(): string
    {
        return $this->template->getName();
    }

    /**
     * テンプレートの説明を取得
     */
    public function getTemplateDescription(): string
    {
        return $this->template->getDescription();
    }

    /**
     * 変数をリセット
     */
    public function resetVariables(): self
    {
        $this->variables = $this->template->getDefaultVariables();
        return $this;
    }
}

/**
 * 抽象テンプレートクラス
 */
abstract class AbstractPromptTemplate implements PromptTemplateInterface
{
    protected string $name;
    protected string $description;
    protected string $template;
    protected array $defaultVariables = [];
    protected array $requiredVariables = [];

    /**
     * {@inheritdoc}
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultVariables(): array
    {
        return $this->defaultVariables;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredVariables(): array
    {
        return $this->requiredVariables;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}

/**
 * セールスプロモーション用テンプレート
 */
class SalesPromotionTemplate extends AbstractPromptTemplate
{
    public function __construct()
    {
        $this->name = 'Sales Promotion Banner';
        $this->description = '花火背景の販促バナー用テンプレート';
        
        $this->template = <<<PROMPT
A vibrant summer sales promotional banner featuring fireworks in the background. 
The composition includes:

Main Elements:
- Spectacular fireworks display in the night sky with vivid colors ({{fireworks_colors}})
- {{product_name}} prominently displayed in the center-right area
- Energetic, festival-like atmosphere

Text Overlays (in Japanese style):
- Main campaign period: "{{campaign_date}}まで" in orange banner (top-left)
- Campaign tagline: "{{campaign_slogan}}" 
- Large bold headline: "{{main_headline}}" in white text
- Vertical text on the left: "{{vertical_text_1}}{{vertical_text_2}}{{vertical_text_3}}"
- Brand logo "{{brand_name}}" in red vertical banner (right edge)
- Product specifications: "{{product_specs}}"
- Secondary product mention: "{{secondary_product}}" in top-right
- Call-to-action: "今すぐ{{brand_name}}公式サイトへ" in red banner with arrows (bottom)

Visual Style:
- Photorealistic product rendering
- Festival/celebration atmosphere with bokeh effects
- Dark blue/purple gradient background
- High contrast between text and background
- Professional advertising photography style
- Dynamic composition with diagonal elements

Color Scheme:
- Deep blue/purple night sky
- Vibrant orange/yellow text boxes
- Bright red accent elements
- Colorful firework bursts
PROMPT;

        $this->defaultVariables = [
            'campaign_date' => '8/28',
            'campaign_slogan' => 'この夏、決断を！',
            'main_headline' => '買い替え応援サマーセール',
            'product_name' => 'ThinkPad X1 Carbon Gen 13',
            'product_specs' => 'インテル® Core™ Ultra 7プロセッサー',
            'brand_name' => 'Lenovo',
            'fireworks_colors' => 'pink, blue, orange, and golden',
            'secondary_product' => 'Lenovo IdeaCentre Mini Q WARRIOR',
            'vertical_text_1' => 'あなたに',
            'vertical_text_2' => '3大特典を',
            'vertical_text_3' => 'お見逃しなく'
        ];

        $this->requiredVariables = [
            'campaign_date',
            'main_headline',
            'product_name',
            'brand_name'
        ];
    }
}

/**
 * シンプルな商品紹介用テンプレート
 */
class SimpleProductTemplate extends AbstractPromptTemplate
{
    public function __construct()
    {
        $this->name = 'Simple Product Showcase';
        $this->description = 'シンプルな商品紹介用テンプレート';
        
        $this->template = <<<PROMPT
A clean and professional product showcase image.

Main Elements:
- {{product_name}} as the central focus
- {{background_style}} background
- Professional studio lighting

Product Details:
- Product: {{product_name}}
- Key Feature: {{key_feature}}
- Brand: {{brand_name}}

Visual Style:
- {{visual_style}}
- High-resolution product photography
- Minimalist composition
- Professional color grading

Color Scheme:
- {{color_scheme}}
PROMPT;

        $this->defaultVariables = [
            'product_name' => 'Product Name',
            'background_style' => 'Pure white',
            'key_feature' => 'Premium Quality',
            'brand_name' => 'Brand',
            'visual_style' => 'Modern and clean',
            'color_scheme' => 'Neutral tones with accent colors'
        ];

        $this->requiredVariables = [
            'product_name',
            'brand_name'
        ];
    }
}

/**
 * イベント告知用テンプレート
 */
class EventAnnouncementTemplate extends AbstractPromptTemplate
{
    public function __construct()
    {
        $this->name = 'Event Announcement';
        $this->