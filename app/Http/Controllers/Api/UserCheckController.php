<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserCheckController extends Controller
{
    public function check(Request $request)
    {
        $phone = preg_replace('/\D+/', '', $request->input('phone')); // remove símbolos e letras

        if (!$phone) {
            return response()->json([
                'error' => 'Phone is required'
            ], 400);
        }

        // tenta encontrar pelo campo whatsapp
        $user = User::where('whatsapp', $phone)->first();

        if ($user) {
            return response()->json([
                'exists' => true,
                'name' => $user->name,
                'id' => $user->id
            ]);
        }

        // não encontrado
        return response()->json(['exists' => false]);
    }
}
