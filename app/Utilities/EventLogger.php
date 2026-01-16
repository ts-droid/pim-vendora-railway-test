<?php

namespace App\Utilities;

use App\Models\EventLog;

class EventLogger
{
    private const EVENT_TYPE_ACTION = 'action';
    private const EVENT_TYPE_CHANGE = 'change';

    public static function logAction(string $log, string $displayName, array $metaData = []): void
    {
        EventLog::create([
            'event_type' => self::EVENT_TYPE_ACTION,
            'display_name' => $displayName,
            'log' => $log,
            'metadata' => $metaData,
        ]);
    }

    public static function logChange(string $key, string $from, string $to, string $displayName, array $metaData  = []): void
    {
        EventLog::create([
            'event_type' => self::EVENT_TYPE_CHANGE,
            'display_name' => $displayName,
            'change_key' => $key,
            'change_from' => $from,
            'change_to' => $to,
            'metadata' => $metaData
        ]);
    }
}
