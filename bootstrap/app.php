<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    // ->withMiddleware(function (Middleware $middleware): void {
    //     // Empêche Laravel de rediriger vers une page HTML
    //     $middleware->redirectGuestsTo(fn () => null);
    // })

    ->withMiddleware(function (Middleware $middleware): void {

        // Empêche redirection HTML
        $middleware->redirectGuestsTo(fn () => null);

        
        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
        ]);


        // FORCE JSON POUR API
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

    })

    ->withExceptions(function (Exceptions $exceptions) {

        // Retour JSON si non authentifié
        $exceptions->render(function (AuthenticationException $e, Request $request) {

            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {

            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Produit non trouvé'
                ], 404);
            }

        });

    })

    ->create();