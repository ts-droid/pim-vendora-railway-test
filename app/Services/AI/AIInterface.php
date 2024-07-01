<?php

namespace App\Services\AI;

interface AIInterface
{
    public function chatCompletion(string $system, string $message): string;

    public function streamChatCompletion(string $system, string $message): array;
}
