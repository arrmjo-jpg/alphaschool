<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Deliberately does NOT use WithoutModelEvents -- it suppresses
     * every Eloquent model event for the whole seeding run, including
     * creating() hooks several models now depend on for real business
     * logic (HasPublicId's ULID generation, Person's search_key
     * computation, Branch's code validation). Re-adding it as a speed
     * optimization would silently produce rows with missing public_ids.
     */
    public function run(): void
    {
        $this->call(ReasonCodeSeeder::class);
        $this->call(OrganizationSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(RelationshipTypeSeeder::class);

        User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'is_super_admin' => true,
        ]);
    }
}
