<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    private const MODEL_ACTIONS = [
        'App\\Models\\User' => 'user',
        'App\\Models\\Member' => 'member',
        'App\\Models\\Book' => 'book',
        'App\\Models\\BookCopy' => 'book_copy',
        'App\\Models\\Borrowing' => 'borrowing',
        'App\\Models\\Renewal' => 'renewal',
        'App\\Models\\Payment' => 'payment',
        'App\\Models\\MembershipPayment' => 'membership_payment',
        'App\\Models\\Fine' => 'fine',
        'App\\Models\\LostBook' => 'lost_book',
        'App\\Models\\DamageRecord' => 'damage_record',
    ];

    public function created(Model $model): void
    {
        app(AuditLogService::class)->record(
            $this->action($model, 'created'),
            auditable: $model,
            description: class_basename($model).' created',
            newValues: $this->withoutMetadata($model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->withoutMetadata($model->getChanges());
        $original = $model->getRawOriginal();
        $oldValues = array_intersect_key($original, $changes);

        app(AuditLogService::class)->record(
            $this->action($model, 'updated'),
            auditable: $model,
            description: class_basename($model).' updated',
            oldValues: $oldValues,
            newValues: $changes,
        );
    }

    public function deleted(Model $model): void
    {
        app(AuditLogService::class)->record(
            $this->action($model, 'deleted'),
            auditable: $model,
            description: class_basename($model).' deleted',
            oldValues: $this->withoutMetadata($model->getAttributes()),
        );
    }

    private function action(Model $model, string $event): string
    {
        $type = self::MODEL_ACTIONS[$model::class] ?? 'record';

        return $type.'.'.$event;
    }

    private function withoutMetadata(array $values): array
    {
        unset($values['id'], $values['created_at'], $values['updated_at']);

        return $values;
    }
}