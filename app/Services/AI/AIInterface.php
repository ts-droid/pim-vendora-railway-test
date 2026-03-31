<?php

namespace App\Services\AI;

interface AIInterface
{
    public function chatCompletion(string $system, string $message, ?float $temperature = null): string;

    public function streamChatCompletion(string $system, string $message): array;

    public function createMessageBatch(array $items): array;

    public function getMessageBatch(string $batchId): array;

    public function getBatchTexts(string $batchId): array;
}
