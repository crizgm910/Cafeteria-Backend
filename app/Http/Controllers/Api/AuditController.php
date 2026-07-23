<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|string|max:30',
            'resource_type' => 'nullable|string|max:120',
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditEvent::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        foreach (['action', 'resource_type', 'user_id'] as $filter) {
            if (isset($validated[$filter])) $query->where($filter, $validated[$filter]);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 25));
    }
}
