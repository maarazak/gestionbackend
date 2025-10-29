<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $projects = Project::with(['tasks' => function($query) {
                $query->with('assignedUser:id,name,email');
            }])->get();
        } else {
            $projects = Project::whereHas('tasks', function($query) use ($user) {
                $query->where('assigned_to', $user->id);
            })->with(['tasks' => function($query) use ($user) {
                $query->where('assigned_to', $user->id)
                      ->with('assignedUser:id,name,email');
            }])->get();
        }

        return $this->success($projects, 'Projets récupérés');
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->forbidden('Seuls les administrateurs peuvent créer des projets');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,completed,archived'
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        $project = Project::create($validated);
        
        return $this->created($project->load('tasks'), 'Projet créé avec succès');
    }

    public function show(Request $request, Project $project)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $project->load(['tasks' => function($query) {
                $query->with('assignedUser:id,name,email');
            }]);
        } else {
           
            $project->load(['tasks' => function($query) use ($user) {
                $query->where('assigned_to', $user->id)
                      ->with('assignedUser:id,name,email');
            }]);
        }

        return $this->success($project, 'Projet récupéré');
    }

    public function update(Request $request, Project $project)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->forbidden('Seuls les administrateurs peuvent modifier des projets');
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:active,completed,archived'
        ]);

        $project->update($validated);
        
        return $this->success($project, 'Projet mis à jour');
    }

    public function destroy(Request $request, Project $project)
    {
        if (!$request->user()->hasRole('admin')) {
            return $this->forbidden('Seuls les administrateurs peuvent supprimer des projets');
        }

        $project->delete();
        
        return $this->success(null, 'Projet supprimé');
    }
}
