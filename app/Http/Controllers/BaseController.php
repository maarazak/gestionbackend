<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Retourne une réponse de succès avec données
     */
    protected function success($data = null, string $message = 'Succès', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Retourne une réponse de succès pour création
     */
    protected function created($data = null, string $message = 'Créé avec succès'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Retourne une réponse de succès sans contenu
     */
    protected function noContent(string $message = 'Supprimé avec succès'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message
        ], 204);
    }

    /**
     * Retourne une réponse d'erreur
     */
    protected function error(string $message = 'Erreur', int $status = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Retourne une réponse d'erreur de validation
     */
    protected function validationError($errors, string $message = 'Erreurs de validation'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Retourne une réponse d'erreur non autorisée
     */
    protected function unauthorized(string $message = 'Non autorisé'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Retourne une réponse d'erreur non trouvé
     */
    protected function notFound(string $message = 'Ressource non trouvée'): JsonResponse
    {
        return $this->error($message, 404);
    }
}
