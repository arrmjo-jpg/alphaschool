<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountTypeResolver;

it('derives no account type for a user whose person holds no context rows', function () {
    $user = User::factory()->create();
    $resolver = new AccountTypeResolver;

    expect($resolver->resolve($user))->toBe([])
        ->and($resolver->hasAnyAccountType($user))->toBeFalse();
});
