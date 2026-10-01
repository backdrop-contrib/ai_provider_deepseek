<?php

/**
 * @file
 * DeepSeek adapter for AI core.
 */

class AIDeepSeekAdapter extends AIAdapterBase {

  use AICompatibleTrait;

  /** @var string */
  protected $baseUrl = 'https://api.deepseek.com';

  /** @var array|null */
  protected $models = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct($api_key, ?AIApi $api = NULL) {
    parent::__construct($api_key, $api);
    $config_base = config_get('ai_provider_deepseek.settings', 'base_url');
    if (!empty($config_base)) {
      $this->baseUrl = rtrim($config_base, '/');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultHeaders(): array {
    return [
      'Authorization' => 'Bearer ' . $this->apiKey,
      'Content-Type' => 'application/json',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(): array {
    if ($this->models !== NULL) {
      return $this->models;
    }

    $models = [];
    try {
      $result = $this->makeRequest($this->baseUrl . '/models', [], [], 'GET', 10);
      if (!empty($result['data']) && is_array($result['data'])) {
        foreach ($result['data'] as $model) {
          $id = $model['id'] ?? ($model['name'] ?? NULL);
          if (!empty($id)) {
            $models[$id] = $model['name'] ?? $id;
          }
        }
      }
    }
    catch (\Exception $e) {
      watchdog('ai_provider_deepseek', 'Failed to fetch DeepSeek models: @message', ['@message' => $e->getMessage()], WATCHDOG_DEBUG);
    }

    if (empty($models)) {
      $models = [
        'deepseek-chat' => 'DeepSeek Chat (V3)',
        'deepseek-reasoner' => 'DeepSeek Reasoner (R1)',
      ];
    }

    asort($models);
    return $this->models = $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelsByCapability($capability): array {
    $models = $this->getModels();
    $capability = ai_normalize_capability_name($capability);
    $filtered = [];

    foreach ($models as $id => $label) {
      $ok = FALSE;
      switch ($capability) {
        case 'text':
        case 'chat':
          $ok = TRUE;
          break;

        case 'thinking':
          $ok = (bool) preg_match('/reasoner|r1/i', $id);
          break;

        case 'tool_calling':
          // DeepSeek-R1 does not support function calling; V3 (chat) does.
          $ok = !preg_match('/reasoner/i', $id);
          break;

        case 'vision':
        case 'embeddings':
        case 'embedding':
        case 'image':
        case 'moderation':
        case 'stt':
          $ok = FALSE;
          break;
      }

      if ($ok) {
        $filtered[$id] = $label;
      }
    }

    backdrop_alter('ai_model_capabilities', $filtered, $capability, $this);
    return $filtered;
  }

  /**
   * {@inheritdoc}
   */
  public function completions(string $model, string $prompt, $temperature, $max_tokens = 512, bool $stream_response = FALSE) {
    // DeepSeek OpenAI-compatible endpoint uses chat/completions; convert prompt to message.
    $messages = [
      ['role' => 'user', 'content' => $prompt],
    ];
    return $this->chat($model, $messages, $temperature, $max_tokens, $stream_response);
  }

  /**
   * {@inheritdoc}
   */
  public function chat(string $model, array $messages, $temperature, $max_tokens = 1024, bool $stream_response = FALSE, array $context_extra = []) {
    $url = $this->baseUrl . '/chat/completions';

    $payload = [
      'model' => $model,
      'messages' => $messages,
    ];

    // DeepSeek Reasoner does not support temperature overrides.
    if (!str_contains($model, 'reasoner')) {
      $payload['temperature'] = (float) $temperature;
    }

    if ((int) $max_tokens > 0) {
      $payload['max_tokens'] = (int) $max_tokens;
    }

    if (!empty($context_extra['response_format'])) {
      $payload['response_format'] = $context_extra['response_format'];
    }
    elseif (!empty($context_extra['json_schema']) || !empty($context_extra['json_mode'])) {
      // DeepSeek supports only json_object; it has no json_schema response
      // format, so a schema request degrades to plain JSON mode (the schema
      // should also be described in the prompt, which must mention "json").
      $payload['response_format'] = ['type' => 'json_object'];
    }

    try {
      if ($stream_response) {
        $payload['stream'] = TRUE;
        return $this->buildStreamingResponse($url, [
          'method' => 'POST',
          'headers' => array_merge(['Accept' => 'text/event-stream'], $this->getDefaultHeaders()),
          'data' => json_encode($payload),
          'timeout' => 300,
        ], function ($data) {
          return $data['choices'][0]['delta']['content'] ?? '';
        });
      }

      $result = $this->makeRequest($url, $payload, [], 'POST', 300);
      return trim($result['choices'][0]['message']['content'] ?? '');
    }
    catch (\Exception $e) {
      watchdog('ai_provider_deepseek', 'DeepSeek chat error: @message', ['@message' => $e->getMessage()], WATCHDOG_ERROR);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function chatWithTools(string $model, array $messages, array $tools, $temperature, $max_tokens = 1024, string $tool_choice = 'auto', array $context_extra = []): array {
    $url = $this->baseUrl . '/chat/completions';

    $payload = [
      'model' => $model,
      'messages' => $messages,
      'tools' => $tools,
      'tool_choice' => $tool_choice,
    ];

    if (!str_contains($model, 'reasoner')) {
      $payload['temperature'] = (float) $temperature;
    }

    if ((int) $max_tokens > 0) {
      $payload['max_tokens'] = (int) $max_tokens;
    }

    try {
      $result = $this->makeRequest($url, $payload, [], 'POST', 300);
      return $this->normalizeToolResponse($result);
    }
    catch (\Exception $e) {
      watchdog('ai_provider_deepseek', 'DeepSeek chatWithTools error: @message', ['@message' => $e->getMessage()], WATCHDOG_ERROR);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function embedding(string $input, string $model, bool $log = TRUE): array {
    watchdog('ai_provider_deepseek', 'Embeddings are not supported by DeepSeek.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Embeddings are not supported by DeepSeek.');
  }

  /**
   * {@inheritdoc}
   */
  public function images(string $model, string $prompt, string $size, string $response_format, string $quality = 'standard', string $style = 'natural', ?string $output_format = NULL) {
    watchdog('ai_provider_deepseek', 'Image generation is not supported by DeepSeek.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Image generation is not supported by DeepSeek.');
  }

  /**
   * {@inheritdoc}
   */
  public function textToSpeech(string $model, string $input, string $voice, string $response_format) {
    watchdog('ai_provider_deepseek', 'Text-to-speech is not supported by DeepSeek.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Text-to-speech is not supported by DeepSeek.');
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(string $model, string $file, string $task = 'transcribe', $temperature = 0.4, string $response_format = 'verbose_json') {
    watchdog('ai_provider_deepseek', 'Speech-to-text is not supported by DeepSeek.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Speech-to-text is not supported by DeepSeek.');
  }

  /**
   * {@inheritdoc}
   */
  public function moderation(string $input, string $model = 'omni-moderation-latest'): array {
    watchdog('ai_provider_deepseek', 'Moderation is not supported by DeepSeek.', [], WATCHDOG_WARNING);
    throw new \RuntimeException('Moderation is not supported by DeepSeek.');
  }

}
