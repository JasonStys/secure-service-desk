<?php

/**
 * File: Exercises native-only generated reference credentials without touching another profile.
 * Symbols: NativeSeedTest and its actual database seeding/idempotence regression.
 * State: in-memory synthetic users, generated hashes and immutable duplicate seed intent only.
 */

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NativeSeedTest extends TestCase
{
    use RefreshDatabase;

    /** Native apps must not turn the reference fixture password into a reusable installed credential. */
    public function test_native_seed_credentials_are_generated_and_existing_rows_are_preserved(): void
    {
        $this->app->detectEnvironment(fn (): string => 'native-local');
        $this->seed(DatabaseSeeder::class);
        $before = User::query()->orderBy('id')->pluck('password', 'id')->all();
        $this->assertCount(3, $before);
        $this->assertCount(3, array_unique($before));
        foreach ($before as $password) {
            $this->assertFalse(Hash::check('local-demo-only', $password));
        }
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, User::query()->orderBy('id')->pluck('password', 'id')->all());
    }
}
