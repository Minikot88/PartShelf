<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DemoEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $demo = DemoEnvironment::enabled();
        $usernameLogin = $request->has('username');
        $data = $request->validate([
            'email' => [$usernameLogin ? 'nullable' : 'required', 'string', 'email', 'max:254'],
            'username' => [$usernameLogin ? 'required' : 'nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:1024'],
            'role' => [$usernameLogin ? 'required' : 'nullable', 'in:owner,manager,staff'],
        ]);

        if ($usernameLogin && ! $demo) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        $field = $usernameLogin ? 'username' : 'email';
        $credentials = [$field => Str::lower(trim($data[$field])), 'password' => $data['password']];
        if (isset($data['role'])) {
            // A query constraint, never an assignment: selected role cannot grant rights.
            $credentials['role'] = $data['role'];
        }

        // Laravel's default bcrypt hasher rejects inputs above its 72-byte limit.
        if (strlen($credentials['password']) > 72) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (! Auth::guard('web')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => Auth::guard('web')->user()->only(['id', 'name', 'email', 'role']),
        ]);
    }
}
