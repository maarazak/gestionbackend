<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends BaseController
{
    /**
     * Liste des tâches
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Task::with(['project', 'assignedUser']);
        
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if (!$user->hasRole('admin')) {
            $query->where('assigned_to', $user->id);
        }
        
        $tasks = $query->get();
        
        return $this->success($tasks, 'Tâches récupérées');
    }

    /**
     * Créer une tâche (admin uniquement)
     */
    public function store(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->forbidden('Seuls les administrateurs peuvent créer des tâches');
        }

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:todo,in_progress,done',
                'priority' => 'nullable|in:low,medium,high',
                'assigned_to' => 'required|exists:users,id',
                'due_date' => 'nullable|date'
            ]);

            $project = Project::findOrFail($validated['project_id']);
            if ($project->tenant_id !== $request->user()->tenant_id) {
                return $this->forbidden('Ce projet n\'existe pas dans votre organisation');
            }

            $assignedUser = User::findOrFail($validated['assigned_to']);
            if ($assignedUser->tenant_id !== $request->user()->tenant_id) {
                return $this->error('Cet utilisateur n\'appartient pas à votre organisation', 400);
            }

            $validated['status'] = $validated['status'] ?? 'todo';
            $validated['priority'] = $validated['priority'] ?? 'medium';

            DB::beginTransaction();
            
            $task = Task::create($validated);
            
            DB::commit();

            return $this->created(
                $task->load(['project', 'assignedUser']), 
                'Tâche créée avec succès'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Erreur lors de la création de la tâche: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Afficher une tâche
     */
    public function show(Request $request, Task $task)
    {
        $user = $request->user();

        if (!$user->hasRole('admin') && $task->assigned_to !== $user->id) {
            return $this->forbidden('Vous n\'avez pas accès à cette tâche');
        }

        return $this->success(
            $task->load(['project', 'assignedUser']), 
            'Tâche récupérée'
        );
    }

    /**
     * Mettre à jour une tâche
     */
    public function update(Request $request, Task $task)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|in:todo,in_progress,done',
                'priority' => 'sometimes|in:low,medium,high',
                'assigned_to' => 'sometimes|exists:users,id',
                'due_date' => 'nullable|date'
            ]);

            if (isset($validated['assigned_to'])) {
                $assignedUser = User::findOrFail($validated['assigned_to']);
                if ($assignedUser->tenant_id !== $user->tenant_id) {
                    return $this->error('Cet utilisateur n\'appartient pas à votre organisation', 400);
                }
            }

            $task->update($validated);

        } else {
            if ($task->assigned_to !== $user->id) {
                return $this->forbidden('Vous ne pouvez modifier que vos propres tâches');
            }

            $validated = $request->validate([
                'status' => 'required|in:todo,in_progress,done'
            ]);

            $task->update(['status' => $validated['status']]);
        }

        return $this->success(
            $task->load(['project', 'assignedUser']), 
            'Tâche mise à jour'
        );
    }

    /**
     * Supprimer une tâche (admin uniquement)
     */
    public function destroy(Request $request, Task $task)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->forbidden('Seuls les administrateurs peuvent supprimer des tâches');
        }

        $task->delete();
        
        return $this->success(null, 'Tâche supprimée');
    }
}
