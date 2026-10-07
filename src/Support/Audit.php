<?php

namespace Cultpantry\Elections\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Writes this module's admin actions (deletions, imports, update digests)
 * into the host app's Event audit log, via App\Actions\RecordEvent. A no-op
 * on a host without that log.
 */
class Audit
{
    public static function record(
        string $type,
        string $description,
        ?Model $subject = null,
        array $metadata = [],
        string $severity = 'info',
    ): void {
        if (! class_exists(\App\Actions\RecordEvent::class)) {
            return;
        }

        $actor = auth()->user();

        app(\App\Actions\RecordEvent::class)->handle(
            type: $type,
            description: $description,
            subject: $subject?->exists ? $subject : null,
            actor: $actor instanceof Model ? $actor : null,
            metadata: array_filter([...$metadata, 'via' => self::via()], fn ($v) => $v !== null && $v !== []),
            severity: $severity,
            subjectType: $subject && ! $subject->exists ? $subject::class : null,
            subjectId: $subject && ! $subject->exists ? $subject->getKey() : null,
        );
    }

    private static function via(): string
    {
        if (class_exists(\App\Support\AccountAudit::class)) {
            return \App\Support\AccountAudit::via();
        }

        return request()->route()?->getName() ?? (app()->runningInConsole() ? 'console' : 'system');
    }
}
