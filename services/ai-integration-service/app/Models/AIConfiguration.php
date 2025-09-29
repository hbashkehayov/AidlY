<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AIConfiguration extends Model
{
    protected $table = 'ai_configurations';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'provider',
        'api_endpoint',
        'api_key_encrypted',
        'webhook_secret',
        'model_settings',
        'retry_policy',
        'timeout_seconds',
        'enable_categorization',
        'enable_suggestions',
        'enable_sentiment',
        'enable_auto_assign',
        'is_active'
    ];

    protected $casts = [
        'id' => 'string',
        'model_settings' => 'array',
        'retry_policy' => 'array',
        'enable_categorization' => 'boolean',
        'enable_suggestions' => 'boolean',
        'enable_sentiment' => 'boolean',
        'enable_auto_assign' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key_encrypted',
        'webhook_secret'
    ];

    protected $appends = [
        'has_api_key',
        'provider_config'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Check if configuration has API key
     *
     * @return bool
     */
    public function getHasApiKeyAttribute(): bool
    {
        return !empty($this->api_key_encrypted);
    }

    /**
     * Get decrypted API key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }

        try {
            return decrypt($this->api_key_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get provider-specific configuration
     *
     * @return array
     */
    public function getProviderConfigAttribute(): array
    {
        $baseConfig = [
            'provider' => $this->provider,
            'timeout' => $this->timeout_seconds,
            'retry_policy' => $this->retry_policy
        ];

        switch ($this->provider) {
            case 'openai':
                return array_merge($baseConfig, [
                    'api_endpoint' => $this->api_endpoint ?: 'https://api.openai.com/v1',
                    'model' => $this->model_settings['model'] ?? 'gpt-4',
                    'temperature' => $this->model_settings['temperature'] ?? 0.7,
                    'max_tokens' => $this->model_settings['max_tokens'] ?? 1000
                ]);

            case 'claude':
                return array_merge($baseConfig, [
                    'api_endpoint' => $this->api_endpoint ?: 'https://api.anthropic.com/v1',
                    'model' => $this->model_settings['model'] ?? 'claude-3-sonnet-20240229',
                    'max_tokens' => $this->model_settings['max_tokens'] ?? 1000
                ]);

            case 'gemini':
                return array_merge($baseConfig, [
                    'api_endpoint' => $this->api_endpoint ?: 'https://generativelanguage.googleapis.com/v1',
                    'model' => $this->model_settings['model'] ?? 'gemini-1.5-pro',
                    'temperature' => $this->model_settings['temperature'] ?? 0.7
                ]);

            case 'n8n':
                return array_merge($baseConfig, [
                    'webhook_url' => $this->api_endpoint,
                    'authentication' => $this->model_settings['authentication'] ?? 'none'
                ]);

            default:
                return $baseConfig;
        }
    }

    /**
     * Check if configuration is ready for use
     *
     * @return bool
     */
    public function isReady(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        switch ($this->provider) {
            case 'openai':
            case 'claude':
            case 'gemini':
                return $this->hasApiKey();
            case 'n8n':
                return !empty($this->api_endpoint);
            default:
                return false;
        }
    }

    /**
     * Get enabled features
     *
     * @return array
     */
    public function getEnabledFeatures(): array
    {
        $features = [];

        if ($this->enable_categorization) {
            $features[] = 'categorization';
        }
        if ($this->enable_suggestions) {
            $features[] = 'suggestions';
        }
        if ($this->enable_sentiment) {
            $features[] = 'sentiment';
        }
        if ($this->enable_auto_assign) {
            $features[] = 'auto_assign';
        }

        return $features;
    }

    /**
     * Scope for active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for configurations with specific feature enabled
     */
    public function scopeWithFeature($query, string $feature)
    {
        $column = 'enable_' . $feature;
        return $query->where($column, true);
    }

    /**
     * Get default configuration for a provider
     */
    public static function getDefaultForProvider(string $provider): ?self
    {
        return static::active()
            ->byProvider($provider)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}