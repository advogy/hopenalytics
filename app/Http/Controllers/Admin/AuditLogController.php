<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $subjectType = trim((string) $request->query('subject_type'));

        $logs = AuditLog::query()
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('actor_name', 'like', "%{$search}%")
                ->orWhere('subject_label', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"),
            ))
            ->when($subjectType, fn ($q) => $q->where('subject_type', $subjectType))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'search' => $search,
            'subjectType' => $subjectType,
        ]);
    }
}
