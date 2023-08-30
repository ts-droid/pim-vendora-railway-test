<?php

namespace App\Http\Controllers;

use App\Models\StatusIndicator;
use Illuminate\Http\Request;

class StatusIndicatorController extends Controller
{
    /**
     * Pings a status indicator.
     *
     * @param string $title
     * @param int $validForSeconds
     * @return void
     */
    public static function ping(string $title, int $validForSeconds)
    {
        $statusIndicator = StatusIndicator::where('title', $title)->first();

        if (!$statusIndicator) {
            $statusIndicator = new StatusIndicator();
        }

        $statusIndicator->title = $title;
        $statusIndicator->ping_time = time();
        $statusIndicator->ping_expires = time() + $validForSeconds;
        $statusIndicator->save();
    }

    public function getAll()
    {
        $statusIndicators = StatusIndicator::orderBy('title', 'ASC')->get();

        return ApiResponseController::success($statusIndicators->toArray());
    }
}
