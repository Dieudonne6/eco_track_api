<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Checkpoint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class TrackingService
{
    protected $workflow = ['created', 'processed', 'in_transit', 'delivered'];

    /**
     * Enregistre un scan/point de contrôle pour un produit identifié par son UUID.
     *
     * @param string $uuid
     * @param array  $data  // status, latitude, longitude, location_name?, metadata?
     * @return \App\Models\Checkpoint
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */


    public function recordScan(string $uuid, array $data)
{
    try {
        $product = Product::where('uuid', $uuid)->firstOrFail();
    } catch (QueryException $e) {
        throw (new ModelNotFoundException)->setModel(Product::class);
    }

    if (! $product) {
        throw (new ModelNotFoundException)->setModel(Product::class);
    }

    // 🚫 STOP si déjà livré
    if ($product->status === 'delivered') {
        throw new \InvalidArgumentException(
            "Cycle de transition déjà terminé, produit déjà à destination"
        );
    }

    $newStatus = $data['status'];

    // préparer metadata...
    $metadata = $data['metadata'] ?? [];
    if (! is_array($metadata)) {
        $decoded = json_decode((string) $metadata, true);
        $metadata = is_array($decoded) ? $decoded : [];
    }

    return DB::transaction(function () use ($product, $data, $metadata, $newStatus) {

        $currentIndex = array_search($product->status, $this->workflow);
        $newIndex     = array_search($newStatus, $this->workflow);

        $currentIndex = ($currentIndex === false) ? -1 : (int) $currentIndex;
        $newIndex     = ($newIndex === false) ? -1 : (int) $newIndex;

        // ---------- RÈGLE STRICTE ----------
        // autorisé si : same status OR next status
        if (! ($newIndex === $currentIndex || $newIndex === $currentIndex + 1) ) {
            // Option A (bloquer) : lancer une exception
            throw new \InvalidArgumentException("Transition non autorisée: {$product->status} -> {$newStatus}");

            // Option B (accepter mais marquer compromis) :
            // $product->update(['is_compromised' => true]);
        }

        // Si tu veux toujours marquer si on saute (inutile ici car on bloque),
        // tu peux garder la logique précédente :
        if ($newIndex > $currentIndex + 1) {
            $product->update(['is_compromised' => true]);
        }

        // créer checkpoint
        $checkpoint = $product->checkpoints()->create([
            'user_id'       => Auth::id(),
            'status'        => $newStatus,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'location_name' => $data['location_name'] ?? null,
            'metadata'      => $metadata,
        ]);

        // mettre à jour le statut
        $product->update(['status' => $newStatus]);

        return $checkpoint->load('product');
    });
}

    // public function recordScan(string $uuid, array $data)
    // {
    //     // Protéger la requête contre une comparaison UUID invalide en Postgres
    //     try {
    //         $product = Product::where('uuid', $uuid)->firstOrFail();
    //     } catch (QueryException $e) {
    //         // Si Postgres rejette la comparaison (ex: "ghgj"), on renvoie un ModelNotFound
    //         throw (new ModelNotFoundException)->setModel(Product::class);
    //     }

    //     // Si pour une raison quelconque on n'a pas de produit (firstOrFail aurait levé),
    //     // on s'assure de renvoyer ModelNotFoundException
    //     if (! $product) {
    //         throw (new ModelNotFoundException)->setModel(Product::class);
    //     }

    //     // Préparer metadata proprement (garantir un array)
    //     $metadata = $data['metadata'] ?? [];
    //     if (! is_array($metadata)) {
    //         // si on reçoit JSON ou string, tenter de décoder (silencieux)
    //         $decoded = json_decode((string) $metadata, true);
    //         $metadata = is_array($decoded) ? $decoded : [];
    //     }

    //     // Tout en transaction pour éviter état partiel
    //     return DB::transaction(function () use ($product, $data, $metadata) {
    //         $newStatus = $data['status'];

    //         // VÉRIFICATION DE LA COHÉRENCE (Le "Trou" dans le scan)
    //         $currentIndex = array_search($product->status, $this->workflow);
    //         $newIndex = array_search($newStatus, $this->workflow);

    //         // Si le statut courant n'est pas dans le workflow (ex: null ou custom), poser -1
    //         $currentIndex = ($currentIndex === false) ? -1 : (int) $currentIndex;
    //         $newIndex     = ($newIndex === false) ? -1 : (int) $newIndex;

    //         // Si on saute une étape (ex: created -> in_transit sans passer par processed)
    //         if ($newIndex > $currentIndex + 1) {
    //             $product->update(['is_compromised' => true]);
    //         }

    //         // Enregistrement du Checkpoint (IoT ou Mobile)
    //         $checkpoint = $product->checkpoints()->create([
    //             // Auth::id() peut être null si l'appel est public — ok si user_id nullable
    //             'user_id'       => Auth::id(),
    //             'latitude'      => $data['latitude'],
    //             'longitude'     => $data['longitude'],
    //             'location_name' => $data['location_name'] ?? null,
    //             'metadata'      => $metadata,
    //         ]);

    //         // Mise à jour du statut du produit
    //         $product->update(['status' => $newStatus]);

    //         // Retourner le checkpoint enrichi (charge la relation produit si besoin)
    //         return $checkpoint->load('product');
    //     });
    // }
}