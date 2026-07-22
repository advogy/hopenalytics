<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records administrative actions only — role promote/revoke, user activate/deactivate/delete/
 * restore, and org-unit (Uni/Daerah/Gereja/Institusi/Personal) create/update/activate/
 * deactivate/delete — per the user's explicit call. Deliberately does NOT cover social account
 * edits (handle/platform/URL/auto-fetch): those happen far too often to be useful signal for a
 * superadmin reviewing the log, and aren't the kind of action that needs oversight the way
 * granting/revoking admin power or changing the org structure does.
 */
class AuditLogger
{
    public static function log(string $action, ?Model $subject, string $description): void
    {
        $actor = auth()->user();

        AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->id,
            'subject_label' => $subject?->name,
            'description' => $description,
        ]);
    }
}
