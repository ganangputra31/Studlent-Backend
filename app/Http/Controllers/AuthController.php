<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nama'             => 'required|string|max:255',
            'no_hp'            => 'required|string|unique:users,no_hp',
            'password'         => 'required|string|min:6',
            'product_interest' => 'required|string',
        ]);

        // Auto-generate email dari no_hp
        $noHpClean = preg_replace('/[^0-9]/', '', $request->no_hp);
        $email     = $noHpClean . '@studlent.com';

        if (User::where('email', $email)->exists()) {
            $email = $noHpClean . '_' . time() . '@studlent.com';
        }

        $user = User::create([
            'nama'             => $request->nama,
            'email'            => $email,
            'no_hp'            => $request->no_hp,
            'password'         => Hash::make($request->password),
            'product_interest' => $request->product_interest,
            'role'             => 'client',
            'joined_at'        => now(),
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'no_hp'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('no_hp', $request->no_hp)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Nomor HP atau password salah'], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}