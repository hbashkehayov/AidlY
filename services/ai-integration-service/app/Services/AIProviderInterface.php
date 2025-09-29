<?php

namespace App\Services;

interface AIProviderInterface
{
    /**
     * Categorize a support ticket
     *
     * @param array $data Ticket data including subject and description
     * @return array Category suggestion with confidence score
     */
    public function categorizeTicket(array $data): array;

    /**
     * Prioritize a support ticket
     *
     * @param array $data Ticket data
     * @return array Priority level with confidence score
     */
    public function prioritizeTicket(array $data): array;

    /**
     * Suggest a response for a ticket
     *
     * @param array $data Ticket data and context
     * @return array Response suggestion
     */
    public function suggestResponse(array $data): array;

    /**
     * Analyze sentiment of customer message
     *
     * @param array $data Message data
     * @return array Sentiment analysis result
     */
    public function analyzeSentiment(array $data): array;

    /**
     * Extract entities from text
     *
     * @param array $data Text data
     * @return array Extracted entities
     */
    public function extractEntities(array $data): array;

    /**
     * Summarize ticket or conversation
     *
     * @param array $data Ticket/conversation data
     * @return array Summary
     */
    public function summarize(array $data): array;

    /**
     * Generate knowledge base article
     *
     * @param array $data Article requirements
     * @return array Generated article
     */
    public function generateKBArticle(array $data): array;

    /**
     * Check provider health status
     *
     * @return array Health status
     */
    public function healthCheck(): array;

    /**
     * Get provider capabilities
     *
     * @return array List of supported features
     */
    public function getCapabilities(): array;

    /**
     * Get provider usage statistics
     *
     * @return array Usage stats
     */
    public function getUsage(): array;
}