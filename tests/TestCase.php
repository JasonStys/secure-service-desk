<?php

/**
 * File: Shared Laravel test harness and synthetic actor factory.
 * Symbols: TestCase and createActors().
 * State: per-test tenant and user fixtures; exact locations are in docs/code-index.md.
 */

namespace Tests;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Tests\Support\FakeVite;

abstract class TestCase extends BaseTestCase
{
    /** Bind a named asset fake so HTTP tests remain independent of frontend builds and coverage-safe. */
    protected function setUp(): void
    {
        parent::setUp();
        ViteFacade::clearResolvedInstance();
        $this->swap(Vite::class, new FakeVite);
    }

    /** @return array{tenant: Tenant, agent: User, requester: User, outsider: User} Synthetic actors. */
    protected function createActors(): array
    {
        $tenant = Tenant::query()->create(['name' => 'Alpha Services', 'slug' => 'alpha']);
        $otherTenant = Tenant::query()->create(['name' => 'Beta Services', 'slug' => 'beta']);

        return [
            'tenant' => $tenant,
            'agent' => User::query()->create([
                'tenant_id' => $tenant->id, 'name' => 'Agent', 'email' => 'agent@alpha.example',
                'password' => Hash::make('test-password'), 'role' => Role::Agent,
            ]),
            'requester' => User::query()->create([
                'tenant_id' => $tenant->id, 'name' => 'Requester', 'email' => 'requester@alpha.example',
                'password' => Hash::make('test-password'), 'role' => Role::Requester,
            ]),
            'outsider' => User::query()->create([
                'tenant_id' => $otherTenant->id, 'name' => 'Outsider', 'email' => 'agent@beta.example',
                'password' => Hash::make('test-password'), 'role' => Role::Agent,
            ]),
        ];
    }
}
