<?php

namespace App\Core\Services;

use App\Core\Models\NumberSequence;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Centralized, concurrency-safe number generation
 * (docs/DOMAIN_BLUEPRINT.md §6/§13, Addendum A9).
 *
 * Concurrency safety comes from a row lock (SELECT ... FOR UPDATE) inside
 * a transaction around the read-increment-write cycle -- never a naive
 * "SELECT MAX(value)+1", which looks correct under single-developer
 * testing and silently produces duplicates under real concurrent load.
 * See tests/Feature/Core/NumberGeneratorServiceTest.php for a genuine
 * dual-connection lock-contention proof, not just a sequential-loop test.
 *
 * Gapless sequences: for a legally-gapless number (e.g. an invoice
 * number, where tax law in many jurisdictions requires no gaps), this
 * service does NOT branch its own behavior based on `is_gapless` -- that
 * column is documentation/reporting metadata only. Gaplessness is a
 * property of HOW THE CALLER uses this service: call next() from WITHIN
 * the same outer transaction that creates the numbered record, so that if
 * anything later in that transaction fails and rolls back, Laravel's
 * savepoint-based nested-transaction support rolls back this increment
 * too. A lenient sequence (e.g. a student number) can call next() outside
 * any wrapping transaction and tolerate an occasional gap on failure.
 */
class NumberGeneratorService
{
    /**
     * Returns the next formatted number for $code, optionally scoped
     * (e.g. per branch or per academic year).
     */
    public function next(string $code, ?string $scopeType = null, ?int $scopeId = null): string
    {
        $scopeType ??= '';
        $scopeId ??= 0;

        return DB::transaction(function () use ($code, $scopeType, $scopeId) {
            $sequence = NumberSequence::query()
                ->where('code', $code)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = $this->findOrCreateSequence($code, $scopeType, $scopeId);
            }

            $currentPeriodKey = $this->computePeriodKey($sequence->reset_period);
            if ($sequence->reset_period !== null && $sequence->period_key !== $currentPeriodKey) {
                $sequence->current_value = 0;
                $sequence->period_key = $currentPeriodKey;
            }

            $sequence->current_value += 1;
            $sequence->save();

            return $this->format($sequence, $sequence->current_value);
        });
    }

    /**
     * Handles the race on the very first call for a given code+scope:
     * two concurrent callers could both see "no row exists" and both
     * attempt to create one. The loser of that race hits the unique
     * constraint on (code, scope_type, scope_id) -- caught here, then
     * both callers converge on re-fetching the same row with a lock.
     */
    protected function findOrCreateSequence(string $code, string $scopeType, int $scopeId): NumberSequence
    {
        try {
            NumberSequence::create([
                'code' => $code,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'current_value' => 0,
            ]);
        } catch (UniqueConstraintViolationException|QueryException) {
            // Someone else created it concurrently between our lookup and
            // insert attempt -- fine, the fetch below finds their row.
        }

        return NumberSequence::query()
            ->where('code', $code)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function computePeriodKey(?string $resetPeriod): ?string
    {
        return match ($resetPeriod) {
            'yearly' => now()->format('Y'),
            'monthly' => now()->format('Y-m'),
            default => null,
        };
    }

    protected function format(NumberSequence $sequence, int $value): string
    {
        $padded = $sequence->padding_length > 0
            ? str_pad((string) $value, $sequence->padding_length, '0', STR_PAD_LEFT)
            : (string) $value;

        return $sequence->format_pattern !== null
            ? str_replace('{number}', $padded, $sequence->format_pattern)
            : $padded;
    }
}
