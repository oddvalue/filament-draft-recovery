<?php

use Oddvalue\FilamentDraftRecovery\Tests\Fixtures\Models\User;
use Oddvalue\FilamentDraftRecovery\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function actingAsTestUser(): User
{
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    test()->actingAs($user);

    return $user;
}
