<?php

namespace App\Observers;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at'], $changes['last_sync']);
        if ($changes === []) return;

        $before = [];
        foreach (array_keys($changes) as $key) {
            $before[$key] = $model->getOriginal($key);
        }
        $this->record('updated', $model, $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $model->getOriginal(), null);
    }

    private function record(string $action, Model $model, ?array $before, ?array $after): void
    {
        $request = app()->bound('request') ? request() : null;
        $correlationId = $request?->headers->get('X-Correlation-ID');
        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        AuditEvent::withoutEvents(fn () => AuditEvent::create([
            'user_id' => auth()->id(),
            'correlation_id' => $correlationId,
            'action' => $action,
            'resource_type' => class_basename($model),
            'resource_id' => (string) $model->getKey(),
            'before_data' => $this->sanitize($before),
            'after_data' => $this->sanitize($after),
            'metadata' => ['route' => $request?->route()?->uri()],
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]));
    }

    private function sanitize(?array $data): ?array
    {
        return $data === null ? null : Arr::except($data, [
            'password', 'remember_token', 'token', 'transaction_reference', 'refund_reference',
            'tracking_token', 'idempotency_key', 'request_fingerprint',
            'collection_idempotency_key', 'collection_request_fingerprint',
        ]);
    }
}
