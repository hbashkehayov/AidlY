<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class FeatureFlagService
{
    private const CACHE_PREFIX = 'ai_feature_flag:';
    private const CACHE_TTL = 300; // 5 minutes

    private static array $defaultFlags = [
        'ai_categorization_enabled' => false,
        'ai_prioritization_enabled' => false,
        'ai_suggestions_enabled' => false,
        'ai_sentiment_analysis_enabled' => false,
        'ai_auto_assignment_enabled' => false,
        'ai_language_detection_enabled' => true,
        'ai_response_templates_enabled' => false,
        'ai_batch_processing_enabled' => false,
        'ai_real_time_processing_enabled' => false,
        'ai_learning_mode_enabled' => false,
    ];

    /**
     * Check if a feature flag is enabled
     *
     * @param string $flag
     * @param mixed $context Additional context (user_id, ticket_id, etc.)
     * @return bool
     */
    public function isEnabled(string $flag, array $context = []): bool
    {
        // Check cache first
        $cacheKey = self::CACHE_PREFIX . $flag;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $this->evaluateFlag($cached, $context);
        }

        // Fallback to environment variable
        $envValue = env('AI_FEATURE_' . strtoupper($flag), self::$defaultFlags[$flag] ?? false);

        // Cache the result
        Cache::put($cacheKey, $envValue, self::CACHE_TTL);

        return $this->evaluateFlag($envValue, $context);
    }

    /**
     * Get all feature flags
     *
     * @param array $context
     * @return array
     */
    public function getAllFlags(array $context = []): array
    {
        $flags = [];

        foreach (self::$defaultFlags as $flag => $default) {
            $flags[$flag] = $this->isEnabled($flag, $context);
        }

        return $flags;
    }

    /**
     * Enable a feature flag
     *
     * @param string $flag
     * @param array $options
     * @return bool
     */
    public function enable(string $flag, array $options = []): bool
    {
        return $this->setFlag($flag, true, $options);
    }

    /**
     * Disable a feature flag
     *
     * @param string $flag
     * @param array $options
     * @return bool
     */
    public function disable(string $flag, array $options = []): bool
    {
        return $this->setFlag($flag, false, $options);
    }

    /**
     * Set a feature flag value
     *
     * @param string $flag
     * @param mixed $value
     * @param array $options
     * @return bool
     */
    public function setFlag(string $flag, $value, array $options = []): bool
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $flag;

            // Store in cache
            $ttl = $options['ttl'] ?? self::CACHE_TTL;
            Cache::put($cacheKey, $value, $ttl);

            // Store in Redis for persistence (if available)
            if ($this->isRedisAvailable()) {
                Redis::setex("feature_flag:{$flag}", $ttl, json_encode([
                    'value' => $value,
                    'updated_at' => now()->toISOString(),
                    'options' => $options
                ]));
            }

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to set feature flag {$flag}", [
                'error' => $e->getMessage(),
                'value' => $value,
                'options' => $options
            ]);
            return false;
        }
    }

    /**
     * Get feature flag configuration with metadata
     *
     * @param string $flag
     * @return array
     */
    public function getFlagConfig(string $flag): array
    {
        $isEnabled = $this->isEnabled($flag);

        $config = [
            'flag' => $flag,
            'enabled' => $isEnabled,
            'default' => self::$defaultFlags[$flag] ?? false,
            'source' => $this->getFlagSource($flag),
        ];

        // Add Redis metadata if available
        if ($this->isRedisAvailable()) {
            $redisData = Redis::get("feature_flag:{$flag}");
            if ($redisData) {
                $data = json_decode($redisData, true);
                $config['updated_at'] = $data['updated_at'] ?? null;
                $config['options'] = $data['options'] ?? [];
            }
        }

        return $config;
    }

    /**
     * Clear all feature flag cache
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        try {
            foreach (array_keys(self::$defaultFlags) as $flag) {
                Cache::forget(self::CACHE_PREFIX . $flag);
            }
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to clear feature flag cache', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Evaluate flag with context (for advanced scenarios like percentage rollouts)
     *
     * @param mixed $flagValue
     * @param array $context
     * @return bool
     */
    private function evaluateFlag($flagValue, array $context): bool
    {
        // Simple boolean evaluation
        if (is_bool($flagValue)) {
            return $flagValue;
        }

        // Percentage rollout (if flagValue is array with percentage)
        if (is_array($flagValue) && isset($flagValue['percentage'])) {
            $percentage = (int) $flagValue['percentage'];

            // Use user_id or ticket_id for consistent rollout
            $identifier = $context['user_id'] ?? $context['ticket_id'] ?? 'default';
            $hash = crc32($identifier) % 100;

            return $hash < $percentage;
        }

        // Conditional evaluation based on context
        if (is_array($flagValue) && isset($flagValue['conditions'])) {
            return $this->evaluateConditions($flagValue['conditions'], $context);
        }

        // Fallback to boolean conversion
        return (bool) $flagValue;
    }

    /**
     * Evaluate conditional flags
     *
     * @param array $conditions
     * @param array $context
     * @return bool
     */
    private function evaluateConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;

            if (!$field || !isset($context[$field])) {
                continue;
            }

            $contextValue = $context[$field];

            switch ($operator) {
                case 'equals':
                    if ($contextValue === $value) return true;
                    break;
                case 'in':
                    if (is_array($value) && in_array($contextValue, $value)) return true;
                    break;
                case 'contains':
                    if (is_string($contextValue) && str_contains($contextValue, $value)) return true;
                    break;
            }
        }

        return false;
    }

    /**
     * Get the source of a feature flag value
     *
     * @param string $flag
     * @return string
     */
    private function getFlagSource(string $flag): string
    {
        $cacheKey = self::CACHE_PREFIX . $flag;

        if (Cache::has($cacheKey)) {
            return 'cache';
        }

        if ($this->isRedisAvailable() && Redis::exists("feature_flag:{$flag}")) {
            return 'redis';
        }

        if (env('AI_FEATURE_' . strtoupper($flag)) !== null) {
            return 'environment';
        }

        return 'default';
    }

    /**
     * Check if Redis is available
     *
     * @return bool
     */
    private function isRedisAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get feature flag statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $stats = [
            'total_flags' => count(self::$defaultFlags),
            'enabled_flags' => 0,
            'disabled_flags' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
        ];

        foreach (self::$defaultFlags as $flag => $default) {
            if ($this->isEnabled($flag)) {
                $stats['enabled_flags']++;
            } else {
                $stats['disabled_flags']++;
            }

            // Check cache hit/miss
            $cacheKey = self::CACHE_PREFIX . $flag;
            if (Cache::has($cacheKey)) {
                $stats['cache_hits']++;
            } else {
                $stats['cache_misses']++;
            }
        }

        return $stats;
    }

    /**
     * Validate feature flag name
     *
     * @param string $flag
     * @return bool
     */
    public function isValidFlag(string $flag): bool
    {
        return array_key_exists($flag, self::$defaultFlags);
    }

    /**
     * Get available feature flags
     *
     * @return array
     */
    public function getAvailableFlags(): array
    {
        return array_keys(self::$defaultFlags);
    }
}