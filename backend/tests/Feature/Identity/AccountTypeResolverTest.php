<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountTypeResolver;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Guardian;
use App\Modules\People\Models\Student;

it('derives no account type for a user whose person holds no context rows', function () {
    $user = User::factory()->create();
    $resolver = new AccountTypeResolver;

    expect($resolver->resolve($user))->toBe([])
        ->and($resolver->hasAnyAccountType($user))->toBeFalse();
});

it('derives employee when the user\'s person holds an Employee row', function () {
    $user = User::factory()->create();
    Employee::factory()->create(['person_id' => $user->person_id]);
    $resolver = new AccountTypeResolver;

    expect($resolver->resolve($user))->toBe([AccountTypeResolver::TYPE_EMPLOYEE])
        ->and($resolver->hasAnyAccountType($user))->toBeTrue();
});

it('derives student when the user\'s person holds a Student row', function () {
    $user = User::factory()->create();
    Student::factory()->create(['person_id' => $user->person_id]);
    $resolver = new AccountTypeResolver;

    expect($resolver->resolve($user))->toBe([AccountTypeResolver::TYPE_STUDENT]);
});

it('derives guardian when the user\'s person holds a Guardian row', function () {
    $user = User::factory()->create();
    Guardian::factory()->create(['person_id' => $user->person_id]);
    $resolver = new AccountTypeResolver;

    expect($resolver->resolve($user))->toBe([AccountTypeResolver::TYPE_GUARDIAN]);
});

it('derives every applicable type at once for a person holding multiple contexts', function () {
    $user = User::factory()->create();
    Employee::factory()->create(['person_id' => $user->person_id]);
    Guardian::factory()->create(['person_id' => $user->person_id]);
    $resolver = new AccountTypeResolver;

    expect($resolver->resolve($user))->toBe([
        AccountTypeResolver::TYPE_EMPLOYEE,
        AccountTypeResolver::TYPE_GUARDIAN,
    ]);
});
