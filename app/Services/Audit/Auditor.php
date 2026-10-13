<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Auditor
{
    /**
     * Record an immutable audit trail entry.
     *
     * @param  array<string, mixed>  $details
     */
    public function log(string $action, ?Model $auditable = null, array $details = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'details' => $details,
            'ip' => Request::ip(),
        ]);
    }
}
