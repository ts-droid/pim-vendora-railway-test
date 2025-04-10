<?php

namespace App\Services;

use App\Models\StockKeepTodo;
use App\Models\StockKeepTransaction;

class StockKeepService
{
    const TODO_TYPE_ARTICLE = 'article';
    const TODO_TYPE_COMPARTMENT = 'compartment';

    public static function makeTodo(string $reference, string $type): StockKeepTodo
    {
        // Remove existing non-archived transactions under investigations
        if ($type === self::TODO_TYPE_ARTICLE) {
            StockKeepTransaction::where('article_number', '=', $reference)
                ->where('status', '=', 'investigation')
                ->where('is_archived', '=', 0)
                ->delete();
        }

        // Check if it already exists
        $existingTodo = StockKeepTodo::where('reference', '=', $reference)
            ->where('type', '=', $type)
            ->first();

        if ($existingTodo) {
            return $existingTodo;
        }

        return StockKeepTodo::create([
            'reference' => $reference,
            'type' => $type
        ]);
    }

    public static function makeTransaction(string $articleNumber, string $identifier, int $value, int $diff, bool $investigate): StockKeepTransaction
    {
        // Remove existing non-archived investigations
        StockKeepTransaction::where('article_number', '=', $articleNumber)
            ->where('status', '=', 'investigation')
            ->where('is_archived', '=', 0)
            ->delete();

        return StockKeepTransaction::create([
            'article_number' => $articleNumber,
            'identifiers' => $identifier,
            'values' => $value,
            'diffs' => $diff,
            'type' => 'manual',
            'status' => $investigate ? 'investigation' : 'completed'
        ]);
    }
}
