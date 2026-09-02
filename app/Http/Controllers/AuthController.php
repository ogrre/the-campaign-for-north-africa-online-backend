<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        auth()->login($user);

        return response()->json(['user' => $user], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        auth()->login($user);

        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== null) {
            $request->user()->currentAccessToken()->delete();
        }
        auth()->logout();

        return response()->json(['message' => 'Logged out.']);
    }
}
