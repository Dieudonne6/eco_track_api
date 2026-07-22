<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use OpenApi\Attributes as OA;

class CompanyController extends Controller
{
    #[OA\Get(
        path: "/api/companies",
        tags: ["Companies"],
        summary: "Lister toutes les entreprises disponibles",
        description: "Permet aux workers de récupérer la liste des entreprises pour choisir un company_id lors de l'inscription",
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des entreprises",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "name", type: "string", example: "Mobility Co"),
                            new OA\Property(property: "type", type: "string", example: "logistics"),
                        ]
                    )
                )
            )
        ]
    )]
    public function index()
    {
        return response()->json(
            Company::select('id', 'name', 'type')->get()
        );
    }
}