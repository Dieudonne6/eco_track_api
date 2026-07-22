<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrackingService;
use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

#[OA\Tag(name: "Traçabilité", description: "Endpoints pour les scans mobiles et machines IoT")]
class TrackingController extends Controller
{
    public function __construct(protected TrackingService $trackingService) {}

    #[OA\Post(
        path: "/api/products/{uuid}/scan",
        tags: ["Traçabilité"],
        summary: "Enregistrer un scan officiel (Mobile ou IoT)",
        description: "Cette route est utilisée par les terminaux de terrain pour mettre à jour le statut et la position GPS.",
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: "uuid", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status", "latitude", "longitude"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["processed", "in_transit", "delivered"]),
                    new OA\Property(property: "latitude", type: "number", format: "float", example: 6.36),
                    new OA\Property(property: "longitude", type: "number", format: "float", example: 2.43),
                    new OA\Property(property: "location_name", type: "string", example: "Entrepôt Cotonou"),
                    new OA\Property(property: "metadata", type: "object", example: ["temp" => 4.5, "humidity" => 20])
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: "Scan enregistré avec succès")]
    public function scan(Request $request, string $uuid)
    {
        // Validation payload
        $validated = $request->validate([
            'status'        => 'required|in:processed,in_transit,delivered',
            'latitude'      => 'required|numeric',
            'longitude'     => 'required|numeric',
            'location_name' => 'nullable|string',
            'metadata'      => 'nullable|array'
        ]);

        // Valider le format UUID (RFC 4122) avant d'interroger la BDD
        if (! preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $uuid)) {
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }

        try {
            // Appel au service — idéalement le service renvoie null ou throw ModelNotFoundException si pas trouvé
            $result = $this->trackingService->recordScan($uuid, $validated);

            // Si le service retourne null/false quand le produit n'existe pas
            if (! $result) {
                return response()->json(['message' => 'Produit non trouvé'], 404);
            }

            return response()->json($result, 201);

        } catch (ModelNotFoundException $e) {
            // Si le service utilise firstOrFail() ou lance ModelNotFoundException
            return response()->json(['message' => 'Produit non trouvé'], 404);

        } catch (QueryException $e) {
            // Par sécurité : si Postgres rejette la comparaison (uuid invalide non détecté plus haut)
            return response()->json(['message' => 'Produit non trouvé'], 404);

        } catch (InvalidArgumentException $e) {
            // Transition interdite par la logique métier
            return response()->json([
                'message' => 'Transition non autorisée, respecté le flux normal qui est created -> processed -> in_transit -> delivered',
                'detail'  => $e->getMessage()
            ], 422);

        }catch (\Throwable $e) {
            // Log et renvoyer une erreur interne (500) pour les autres erreurs inattendues
            Log::error('Erreur scan produit: '.$e->getMessage(), [
                'uuid' => $uuid,
                'payload' => $validated,
            ]);

            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    
    #[OA\Get(
        path: "/api/products/{uuid}/history",
        tags: ["Consultation Publique"],
        summary: "Afficher la timeline du produit (Scan QR Code)",
        description: "Route publique accessible par le consommateur final pour vérifier la provenance et l'historique du produit.",
        parameters: [new OA\Parameter(name: "uuid", in: "path", required: true, schema: new OA\Schema(type: "string"))]
    )]
    #[OA\Response(response: 200, description: "Historique récupéré")]
    public function history(string $uuid)
    {
        try {
        $product = Product::where('uuid', $uuid)
            // ->with(['checkpoints.user', 'company'])
            ->with([
                'checkpoints' => function ($query) {
                    $query->orderBy('created_at', 'asc'); // TRI CHRONOLOGIQUE
                },
                'checkpoints.user',
                'company'
            ])
            ->first();

            if (! $product) {
                return response()->json(['message' => 'Produit non trouvé'], 404);
            }

            return response()->json($product);

        } catch (QueryException $e) {
            // Si l'erreur vient d'un UUID invalide, retourner 404 proprement
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }
    }
}

