<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends BaseController
{
    public function index()
    {
        $projects = Project::with('tasks')->get();
        return $this->success($projects, 'Projets récupérés');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:active,completed,archived'
        ]);

        $project = Project::create($validated);
        return $this->created($project, 'Projet créé avec succès');
    }

    public function show(Project $project)
    {
        // Le middleware vérifie déjà le tenant
        return $this->success($project->load('tasks.assignedUser'), 'Projet récupéré');
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:active,completed,archived'
        ]);

        $project->update($validated);
        return $this->success($project, 'Projet mis à jour');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return $this->noContent('Projet supprimé');
    }
}
