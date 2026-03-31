<?php

declare(strict_types=1);

return [
    'models' => [
        'status' => IvanBaric\Status\Models\Status::class,
        'status_history' => IvanBaric\Status\Models\StatusHistory::class,
        'status_transition' => IvanBaric\Status\Models\StatusTransition::class,
    ],
    'cache_ttl' => 3600,
    'history_enabled' => true,
    'transitions_enabled' => true,
    'events_enabled' => true,
    'strict_mode' => true,
    'actor_resolver' => IvanBaric\Status\Support\Actors\AuthStatusActorResolver::class,
    'result_messages' => [
        'same_status' => 'Status is already assigned.',
        'final_status' => 'Final status cannot be changed.',
        'clear_final_status' => 'Final status cannot be cleared.',
        'inactive_status' => 'Selected status is inactive.',
        'invalid_status_type' => 'Selected status does not belong to this model type.',
        'invalid_transition' => 'Transition to the selected status is not allowed.',
        'guard_failed' => 'Transition guard denied this status change.',
        'status_cleared' => 'Status cleared successfully.',
        'status_updated' => 'Status updated successfully.',
        'status_missing' => 'Status could not be resolved.',
        'persisted_model_required' => 'Status operations require a persisted model instance.',
    ],
];
