<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        // Laravel's default bcrypt hasher rejects inputs above its 72-byte limit.
        if (strlen($credentials['password']) > 72) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $credentials['email'] = Str::lower(trim($credentials['email']));

        if (! Auth::guard('web')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => Auth::guard('web')->user()->only(['id', 'name', 'email', 'role']),
        ]);
    }
}
