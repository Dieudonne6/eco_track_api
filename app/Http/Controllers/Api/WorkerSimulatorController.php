<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

#[OA\Tag(name: "Simulation Ouvrier", description: "Simule l'interaction d'une application mobile métier")]
class WorkerSimulatorController extends Controller
{
    /**
     * Liste les étapes de workflow disponibles pour aider l'ouvrier à choisir
     */
    #[OA\Get(path: "/api/worker/steps",
    tags: ["Simulation Ouvrier"], 
    summary: "Liste les statuts valides pour le workflow"
    )]
    #[OA\Response(response: 200, description: "Liste des étapes récupérée")]
    public function getAvailableSteps()
    {
        return response()->json([
            'steps' => ['processed', 'in_transit', 'delivered'],
            'instruction' => "Choisissez un statut et envoyez-le avec l'UUID du produit à la route de scan."
        ]);
    }

    /**
     * Route de "Passerelle" pour simuler l'envoi depuis une App Mobile
     */
    #[OA\Post(
        path: "/api/worker/submit-scan",
        tags: ["Simulation Ouvrier"],
        summary: "Simuler la soumission d'un scan par un ouvrier",
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["product_uuid", "status"],
                properties: [
                    new OA\Property(property: "product_uuid", type: "string", example: "550e8400-e29b-41d4-a716-446655440000"),
                    new OA\Property(property: "status", type: "string", enum: ["processed", "in_transit", "delivered"]),
                    new OA\Property(property: "latitude", type: "number", example: 6.36),
                    new OA\Property(property: "longitude", type: "number", example: 2.43),
                    new OA\Property(property: "location_name", type: "string", example: "Entrepôt de Tri A"),
                    new OA\Property(property: "metadata", type: "object", example: ["condition" => "good", "battery" => "85%"])
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: "Scan métier simulé avec succès")]
    #[OA\Response(response: 422, description: "Données invalides ou UUID inexistant")]
    #[OA\Response(response: 401, description: "Non autorisé")]
public function submitManualScan(Request $request)
{
    // Validation minimale du payload (on n'utilise PAS "exists" pour contrôler nous-mêmes la réponse 404)
    $validated = $request->validate([
        'product_uuid'  => 'required|string', // on vérifie l'UUID manuellement ensuite
        'status'        => 'required|in:processed,in_transit,delivered',
        'latitude'      => 'nullable|numeric',
        'longitude'     => 'nullable|numeric',
        'location_name' => 'nullable|string',
        'metadata'      => 'nullable|array'
    ]);

    // Valider le format UUID (RFC 4122) avant d'interroger la BDD
    $uuid = $validated['product_uuid'];
    if (! preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $uuid)) {
        return response()->json(['message' => 'Produit non trouvé'], 404);
    }

    // Valeurs par défaut si aucune coordonnée fournie
    $validated['latitude']  = $validated['latitude'] ?? 6.3673;
    $validated['longitude'] = $validated['longitude'] ?? 2.4252;

    // Appel du service dans un try/catch pour gérer les cas "UUID invalide en PG" ou "produit non trouvé"
    $trackingService = app(\App\Services\TrackingService::class);

    try {
        $result = $trackingService->recordScan($uuid, $validated);

        if (! $result) {
            // Si le service retourne null ou false pour produit introuvable
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }

        return response()->json([
            'message' => 'Scan métier simulé avec succès',
            'data'    => $result
        ], 201);

    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Produit non trouvé'], 404);

    } catch (QueryException $e) {
        // Si Postgres refuse la comparaison uuid = '...' (sécurité supplémentaire)
        return response()->json(['message' => 'Produit non trouvé'], 404);

    } catch (InvalidArgumentException $e) {
        // Transition interdite par la logique métier
        return response()->json([
            'message' => 'Transition non autorisée, respecté le flux normal qui est created -> processed -> in_transit -> delivered',
            'detail'  => $e->getMessage()
        ], 422);

    }catch (\Throwable $e) {
        // Log pour debug et réponse 500 generique
        Log::error('Erreur submitManualScan: '.$e->getMessage(), [
            'uuid' => $uuid,
            'payload' => $validated,
            'exception' => (string) $e
        ]);

        return response()->json(['message' => 'Erreur interne'], 500);
    }
}
}

