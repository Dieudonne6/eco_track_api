<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;
// AJOUTE CES DEUX LIGNES :
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

use OpenApi\Attributes as OA;

class ProductController extends Controller
{


    /**
     * Liste paginée de produits.
     *
     * GET /api/products
     */
    #[OA\Get(
        path: "/api/allproducts",
        tags: ["Produits"],
        summary: "Lister les produits (paginé)",
        description: "Retourne la liste paginée des produits avec leur société (company).",
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), description: "Numéro de page"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), description: "Nombre d'éléments par page (défaut 20)")
        ]
    )]
    #[OA\Response(
        response: 200,
        description: "Liste paginée des produits",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                new OA\Property(property: "meta", type: "object"),
                new OA\Property(property: "links", type: "object")
            ]
        )
    )]
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = $perPage > 0 && $perPage <= 200 ? $perPage : 20;

        $products = Product::with('company')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($products, 200);
    }



     /**
     * Détail d'un produit par UUID.
     *
     * GET /api/products/{uuid}
     */
    // #[OA\Get(
    //     path: "/api/products/{uuid}",
    //     tags: ["Produits"],
    //     summary: "Détails d'un produit",
    //     description: "Retourne les informations d'un produit identifié par son UUID.",
    //     security: [['sanctum' => []]],
    //     parameters: [
    //         new OA\Parameter(
    //             name: "uuid",
    //             in: "path",
    //             required: true,
    //             schema: new OA\Schema(type: "string"),
    //             description: "UUID du produit"
    //         )
    //     ]
    // )]
    // #[OA\Response(
    //     response: 200,
    //     description: "Produit trouvé",
    //     content: new OA\JsonContent(type: "object")
    // )]
    // #[OA\Response(response: 404, description: "Produit non trouvé")]
    // public function show(string $uuid)
    // {
    //     // Valider format UUID (RFC 4122) avant d'interroger la BDD (évite erreur PG)
    //     if (! preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $uuid)) {
    //         return response()->json(['message' => 'Produit non trouvé'], 404);
    //     }

    //     try {
    //         $product = Product::where('uuid', $uuid)
    //             ->with(['checkpoints.user', 'company'])
    //             ->first();

    //         if (! $product) {
    //             return response()->json(['message' => 'Produit non trouvé'], 404);
    //         }

    //         return response()->json($product, 200);

    //     } catch (QueryException $e) {
    //         // Si Postgres rejette la comparaison (sécurité), renvoyer 404 proprement
    //         return response()->json(['message' => 'Produit non trouvé'], 404);

    //     } catch (ModelNotFoundException $e) {
    //         return response()->json(['message' => 'Produit non trouvé'], 404);

    //     } catch (\Throwable $e) {
    //         // Log pour débogage et renvoi d'une erreur interne générique
    //         Log::error('Erreur show product: '.$e->getMessage(), ['uuid' => $uuid, 'exception' => (string)$e]);
    //         return response()->json(['message' => 'Erreur interne'], 500);
    //     }
    // }


    #[OA\Post(
        path: "/api/products",
        tags: ["Produits"],
        summary: "Créer un nouveau produit",
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "sku"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Sac de Café Arabica 25kg"),
                    new OA\Property(property: "sku", type: "string", example: "CAF-AR-001")
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: "Produit créé")]

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'sku' => 'required|string|unique:products'
        ]);

        // Création du produit (l'UUID est généré par le modèle automatiquement)
        $product = auth()->user()->company->products()->create($data);

        $product->checkpoints()->create([
            'user_id' => auth()->id(),
            'status' => 'created',
            'latitude' => 0, // ou null si tu autorises
            'longitude' => 0,
            'location_name' => 'Création du produit',
            'metadata' => null,
        ]);

        return response()->json([
            'message' => 'Produit créé',
            'product' => $product,
            'qr_code_url' => url("/api/products/{$product->uuid}/qrcode") // Lien vers l'image du QR
        ], 201);
    }

    #[OA\Get(path: "/api/products/{uuid}/qrcode", tags: ["Produits"], summary: "Générer l'image du QR Code")]
    #[OA\Parameter(name: "uuid", in: "path", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Image du QR Code (SVG)")]
    #[OA\Response(response: 404, description: "Produit non trouvé")]


public function generateQrCode(Request $request, $uuid)
{
    // 1) Valider le format UUID (RFC 4122) pour éviter que Postgres plante
    if (! preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $uuid)) {
        return response()->json(['message' => 'Produit non trouvé'], 404);
    }

    try {
        // 2) Charger le produit en toute sécurité
        $product = Product::where('uuid', $uuid)->first();

        if (! $product) {
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }

        // 3) Construire l'URL du frontend pour le scan
        $urlToScan = env('FRONTEND_URL', 'http://localhost:8000') . "/history/" . $uuid;

        // 4) Options QR et génération
        $options = new QROptions([
            'version'      => 5,
            'outputType'   => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'     => QRCode::ECC_L,
            'addQuietzone' => true,
            'imageBase64'  => false,
        ]);

        $qrcode = new QRCode($options);
        $svgData = $qrcode->render($urlToScan);

        // 5) Retourner le SVG
        return response($svgData, 200)
                ->header('Content-Type', 'image/svg+xml');

    } catch (QueryException $e) {
        // Si une QueryException intervient (sécurité), renvoyer 404 JSON
        return response()->json(['message' => 'Produit non trouvé'], 404);

    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Produit non trouvé'], 404);

    } catch (Throwable $e) {
        // Log pour debug et renvoi d'erreur JSON standard
        Log::error('Erreur generateQrCode: '.$e->getMessage(), [
            'uuid' => $uuid,
            'exception' => (string) $e
        ]);

        return response()->json(['message' => 'Erreur interne'], 500);
    }
}


//     public function generateQrCode($uuid)
//     {
//         $product = Product::where('uuid', $uuid)->firstOrFail();
        
//         // L'URL que le client scannera
//         $urlToScan = env('FRONTEND_URL', 'http://localhost:8007') . "/history/" . $uuid;

//         // Configuration du QR Code
//         $options = new QROptions([
//             'version'    => 5,
//             'outputType' => QRCode::OUTPUT_MARKUP_SVG, // On utilise du SVG (plus léger et net)
//             'eccLevel'   => QRCode::ECC_L,
//         ]);

//         $qrcode = new QRCode($options);
        
//         // Génération de l'image SVG
//         $svgData = $qrcode->render($urlToScan);

//         return response($svgData)
//                 ->header('Content-Type', 'image/svg+xml');
//     }

}

