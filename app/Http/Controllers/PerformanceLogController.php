<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PerformanceLogController extends Controller
{
    const LOG_FILE = 'performance_log.txt';

    private array $log = [];

    function __destruct()
    {
        if ($this->log && count($this->log) > 0) {
            Storage::disk('local')->delete(self::LOG_FILE);
            Storage::disk('local')->put(self::LOG_FILE, json_encode($this->log));
        }
    }

    public function start(string $key)
    {
        $this->log[$key] = [
            'start' => microtime(true),
            'end' => null,
            'duration' => null,
        ];
    }

    public function end(string $key)
    {
        if (isset($this->log[$key])) {
            $this->log[$key]['end'] = microtime(true);
            $this->log[$key]['duration'] = round($this->log[$key]['end'] - $this->log[$key]['start'], 8);
        }
    }

    public function view()
    {
        $content = Storage::disk('local')->get(self::LOG_FILE);

        if (!$content) {
            die('No log file found.');
        }

        echo '<pre>';
        echo json_encode(json_decode($content), JSON_PRETTY_PRINT);
        echo '</pre>';
        exit;
    }
}
