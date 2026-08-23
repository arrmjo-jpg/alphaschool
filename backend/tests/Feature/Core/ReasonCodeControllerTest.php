<?php

use App\Modules\Identity\Models\User;
use Database\Seeders\ReasonCodeSeeder;

/**
 * UI Sprint 2's minimal ReasonCode lookup (docs/ADMIN_DESIGN_SYSTEM.md
 * §30.5) -- deliberately auth-only, no extra permission check, since
 * this endpoint is genuinely cross-module by design (see the
 * controller's own docblock).
 */
it('lists reason codes for a real seeded context', function () {
    $this->seed(ReasonCodeSeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('reason-codes.index', ['context' => 'homeroom_teacher_assignment']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2)
        ->and(collect($data)->pluck('code')->all())->toContain('teacher_reassigned', 'entered_in_error');

    // Both languages must be present regardless of the backend's own
    // app locale -- the real bug this guards against (found live).
    $transferred = collect($data)->firstWhere('code', 'teacher_reassigned');
    expect($transferred['label_en'])->toBe('Teacher reassigned to a different section')
        ->and($transferred['label_ar'])->toBe('تم نقل المعلم إلى شعبة أخرى');
});

it('returns an empty list for an unknown context, not an error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('reason-codes.index', ['context' => 'not_a_real_context']));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

it('requires a context query param', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('reason-codes.index'))->assertUnprocessable();
});

it('denies an unauthenticated request', function () {
    $this->getJson(route('reason-codes.index', ['context' => 'homeroom_teacher_assignment']))->assertUnauthorized();
});
