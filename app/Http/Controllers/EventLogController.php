<?php

namespace App\Http\Controllers;

use App\Models\EventLog;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    public function get(Request $request)
    {
        $eventType = $request->input('eventType');
        $metaFilter = $request->input('meta_filter', []) ?: [];
        $limit = (int) $request->input('limit', 100);
        $pageNumber = (int) $request->input('page_number', 0);
        $pageSize = (int) $request->input('page_size', 100);

        $query = EventLog::query();

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        if ($metaFilter && count($metaFilter) > 0) {
            foreach ($metaFilter as $key => $value) {
                $query->where('metadata->' . $key, $value);
            }
        }

        $query->orderBy('id', 'DESC');

        if ($pageNumber > 0) {
            $logs = $query->paginate($pageSize, ['*'], 'page_number', $pageNumber);
        } else {
            $logs = $query->limit($limit)->get();
        }

        return ApiResponseController::success($logs->toArray());
    }
}
