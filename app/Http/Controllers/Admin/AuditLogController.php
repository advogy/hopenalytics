<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab') === 'login' ? 'login' : 'aksi';

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

        $loginSearch = trim((string) $request->query('login_search'));

        // A distinct page-name ('login_page' instead of the default 'page') keeps this
        // paginator's links independent of the audit-log table's own — both tabs render in the
        // same response, so sharing the default name would make paging one table silently jump
        // the other's page too.
        $loginLogs = LoginLog::query()
            ->with('user')
            ->when($loginSearch, fn ($q) => $q->whereHas('user', fn ($q2) => $q2
                ->where('name', 'like', "%{$loginSearch}%")
                ->orWhere('email', 'like', "%{$loginSearch}%"),
            ))
            ->orderByDesc('created_at')
            ->paginate(30, ['*'], 'login_page')
            ->withQueryString();

        return view('admin.audit-log.index', [
            'activeTab' => $activeTab,
            'logs' => $logs,
            'search' => $search,
            'subjectType' => $subjectType,
            'loginLogs' => $loginLogs,
            'loginSearch' => $loginSearch,
        ]);
    }
}
