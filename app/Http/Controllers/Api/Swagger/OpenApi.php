<?php

namespace App\Http\Controllers\Api\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "EcoTrack API",
    description: "Système de traçabilité intelligente pour la Supply Chain (Mobile & IoT)."
)]
#[OA\Server(url: 'http://127.0.0.1:8003', description: "Serveur Local")]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Entrez votre token reçu lors du login"
)]
// Cette section définit comment Swagger envoie les requêtes par défaut
#[OA\Schema(
    schema: "JsonResponse",
    type: "object",
    properties: [
        new OA\Property(property: "message", type: "string", example: "Unauthenticated.")
    ]
)]
class OpenApi {}
