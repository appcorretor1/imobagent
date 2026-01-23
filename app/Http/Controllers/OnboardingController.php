<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Mostra a página de onboarding
     */
    public function index()
    {
        $user = auth()->user();
        $role = $user->role ?? null;
        
        return view('onboarding.index', [
            'user' => $user,
            'role' => $role,
        ]);
    }
}
