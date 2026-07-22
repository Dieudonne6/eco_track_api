<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/register",
        tags: ["Auth"],
        summary: "Inscription d'un nouvel acteur",
        description: "Deux types d'inscription : 
                    - Admin : crée une entreprise
                    - Worker : rejoint une entreprise existante via company_id",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                oneOf: [

                    // ================= ADMIN =================
                    new OA\Schema(
                        required: ["name", "email", "password", "role", "company_name", "company_type"],
                        properties: [
                            new OA\Property(property: "name", type: "string", example: "Franck Admin"),
                            new OA\Property(property: "email", type: "string", format: "email", example: "admin@mail.com"),
                            new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                            new OA\Property(property: "role", type: "string", example: "admin"),
                            new OA\Property(property: "company_name", type: "string", example: "Smart Mobility"),
                            new OA\Property(property: "company_type", type: "string", enum: ["producer", "logistics", "retailer"], example: "logistics"),
                        ]
                    ),

                    // ================= WORKER =================
                    new OA\Schema(
                        required: ["name", "email", "password", "role", "company_id"],
                        properties: [
                            new OA\Property(property: "name", type: "string", example: "Jean Worker"),
                            new OA\Property(property: "email", type: "string", format: "email", example: "worker@mail.com"),
                            new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                            new OA\Property(property: "role", type: "string", example: "worker"),
                            new OA\Property(property: "company_id", type: "integer", example: 1),
                        ]
                    ),

                ],

                examples: [

                    new OA\Examples(
                        example: "admin",
                        summary: "Inscription Admin",
                        value: [
                            "name" => "Franck Admin",
                            "email" => "admin@mail.com",
                            "password" => "password123",
                            "role" => "admin",
                            "company_name" => "Smart Mobility",
                            "company_type" => "logistics"
                        ]
                    ),

                    new OA\Examples(
                        example: "worker",
                        summary: "Inscription Worker",
                        value: [
                            "name" => "Jean Worker",
                            "email" => "worker@mail.com",
                            "password" => "password123",
                            "role" => "worker",
                            "company_id" => 1
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "User created successfully",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "token", type: "string", example: "1|asdasdasdasd"),
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation error"
            )
        ]
    )]


    public function register(RegisterRequest $request)
    {
        return DB::transaction(function () use ($request) {

            $data = $request->validated();

            // dd($data);

            // CAS ADMIN
            if ($data['role'] === 'admin') {
                // dd('admin');
                $company = Company::create([
                    'name' => $data['company_name'],
                    'type' => $data['company_type']
                ]);

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'company_id' => $company->id,
                    'role' => 'admin'
                ]);
            }

            // CAS WORKER
            else {
                // dd('worker');
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'company_id' => $data['company_id'], // ⚠️ important
                    'role' => 'worker'
                ]);
            }

            return response()->json([
                'token' => $user->createToken('api-token')->plainTextToken,
                'user' => $user->load('company')
            ], 201);
        });
    }

    #[OA\Post(
        path: "/api/login",
        tags: ["Auth"],
        summary: "Connexion pour obtenir un Token",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "jean@coffee.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200, 
                description: "Connexion réussie",
            ),
            new OA\Response(
                response: 401, 
                description: "Identifiants invalides"
            )
        ]
    )]

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);
        
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        return response()->json(
            [
                'token' => $user->createToken('auth_token')->plainTextToken,
                'message' => 'Connexion effectuée avec success',
            ]
            );
    }
}









