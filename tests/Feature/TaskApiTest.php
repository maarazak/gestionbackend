<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $user;
    protected $tenant;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create([
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->user = User::factory()->create([
            'current_tenant_id' => $this->tenant->id,
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $memberRole = Role::where('name', 'user')->first();

        $this->tenant->members()->attach($this->admin->id, ['role_id' => $adminRole->id]);
        $this->tenant->members()->attach($this->user->id, ['role_id' => $memberRole->id]);

        $this->admin->assignRole('admin');
        $this->user->assignRole('user');

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_admin_can_create_task()
    {
        Sanctum::actingAs($this->admin);

        $taskData = [
            'project_id' => $this->project->id,
            'title' => 'New Task',
            'description' => 'Task description',
            'status' => 'todo',
            'priority' => 'high',
            'assigned_to' => $this->user->id,
            'due_date' => '2025-12-31',
        ];

        $response = $this->postJson('/api/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tâche créée avec succès',
            ]);
    }

    public function test_member_cannot_create_task()
    {
        Sanctum::actingAs($this->user);

        $taskData = [
            'project_id' => $this->project->id,
            'title' => 'New Task',
            'assigned_to' => $this->user->id,
        ];

        $response = $this->postJson('/api/tasks', $taskData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Seuls les administrateurs peuvent créer des tâches',
            ]);
    }

    public function test_unauthenticated_user_cannot_create_task()
    {
        $taskData = [
            'project_id' => $this->project->id,
            'title' => 'New Task',
            'assigned_to' => $this->user->id,
        ];

        $response = $this->postJson('/api/tasks', $taskData);

        $response->assertStatus(401);
    }
}
