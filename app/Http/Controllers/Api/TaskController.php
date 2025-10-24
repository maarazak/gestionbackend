<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;

class TaskController extends BaseController
{
    public function index(Request $request)
    {
        $query = Task::with(['project', 'assignedUser']);
        
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        
        $tasks = $query->get();
        return $this->success($tasks, 'Tâches récupérées');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:todo,in_progress,done',
            'priority' => 'in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date'
        ]);

        $task = Task::create($validated);
        return $this->created($task->load(['project', 'assignedUser']), 'Tâche créée avec succès');
    }

    public function show(Task $task)
    {
        return $this->success($task->load(['project', 'assignedUser']), 'Tâche récupérée');
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:todo,in_progress,done',
            'priority' => 'in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date'
        ]);

        $task->update($validated);
        return $this->success($task->load(['project', 'assignedUser']), 'Tâche mise à jour');
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return $this->noContent('Tâche supprimée');
    }
}
