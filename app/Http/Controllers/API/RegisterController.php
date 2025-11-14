<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\RegisterRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {
    }

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $this->userService->createUser(
            name: $request->name,
            email: $request->email,
            plainTextPassword: $request->password,
            isAdmin: false
        );

        return response()->json([
            'message' => 'User registered successfully',
        ], 201);
    }
}

