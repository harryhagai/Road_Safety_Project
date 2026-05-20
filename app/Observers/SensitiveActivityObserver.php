<?php

namespace App\Observers;

use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer that reacts to model events handled by SensitiveActivityObserver.
 */
class SensitiveActivityObserver
{
    /**
     * Handle the __construct workflow for this class.
     */
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    /**
     * Handle the created workflow for this class.
     */

    public function created(Model $model): void
    {
        $this->auditTrailService->logModelEvent(
            'created',
            $model,
            null,
            $model->getAttributes(),
        );
    }

    /**
     * Handle the updated workflow for this class.
     */

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        if ($changes === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changes) as $attribute) {
            $oldValues[$attribute] = $model->getOriginal($attribute);
        }

        $this->auditTrailService->logModelEvent(
            'updated',
            $model,
            $oldValues,
            $changes,
        );
    }

    /**
     * Handle the deleted workflow for this class.
     */

    public function deleted(Model $model): void
    {
        $this->auditTrailService->logModelEvent(
            'deleted',
            $model,
            $model->getOriginal(),
            null,
        );
    }
}
