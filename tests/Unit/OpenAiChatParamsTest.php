<?php

namespace Tests\Unit;

use App\Support\OpenAiChatParams;
use PHPUnit\Framework\TestCase;

class OpenAiChatParamsTest extends TestCase
{
    private array $messages = [
        ['role' => 'system', 'content' => 's'],
        ['role' => 'user', 'content' => 'u'],
    ];

    public function test_reasoning_family_uses_completion_tokens_and_low_effort(): void
    {
        foreach (['gpt-5.4-mini', 'gpt-5.4', 'o3-mini', 'o1'] as $model) {
            $params = OpenAiChatParams::json($model, $this->messages, 600);

            // Reasoning modelleri max_tokens/temperature'ı 400 ile reddeder
            $this->assertArrayNotHasKey('max_tokens', $params, $model);
            $this->assertArrayNotHasKey('temperature', $params, $model);
            // Görünmez düşünme tokenları da tavandan yer — pay eklenmiş olmalı
            $this->assertSame(4600, $params['max_completion_tokens'], $model);
            $this->assertSame('low', $params['reasoning_effort'], $model);
            $this->assertSame(['type' => 'json_object'], $params['response_format'], $model);
        }
    }

    public function test_legacy_family_uses_plain_max_tokens(): void
    {
        foreach (['gpt-4o', 'gpt-4o-mini', 'gpt-test-dest'] as $model) {
            $params = OpenAiChatParams::json($model, $this->messages, 300);

            $this->assertSame(300, $params['max_tokens'], $model);
            $this->assertArrayNotHasKey('max_completion_tokens', $params, $model);
            $this->assertArrayNotHasKey('reasoning_effort', $params, $model);
        }
    }

    public function test_family_detection_matches_import_rule(): void
    {
        $this->assertTrue(OpenAiChatParams::isReasoningModel('gpt-5.4-mini'));
        $this->assertTrue(OpenAiChatParams::isReasoningModel('o4-mini'));
        $this->assertFalse(OpenAiChatParams::isReasoningModel('gpt-4o'));
        $this->assertFalse(OpenAiChatParams::isReasoningModel('gpt-4o-mini'));
    }
}
