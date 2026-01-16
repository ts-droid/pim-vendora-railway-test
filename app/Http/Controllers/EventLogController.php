<?php

namespace App\Http\Controllers;

use App\Models\EventLog;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    public function get(Request $request)
    {
        $metaFilter = $request->input('meta_filter', []) ?: [];
        $limit = $request->input('limit', 100);

        $query = EventLog::query();

        if ($metaFilter && count($metaFilter) > 0) {
            foreach ($metaFilter as $key => $value) {
                $query->where('metadata->' . $key, $value);
            }
        }

        $query->orderBy('id', 'DESC')
            ->limit($limit);

        $logs = $query->get();

        return ApiResponseController::success($logs->toArray());
    }
}
