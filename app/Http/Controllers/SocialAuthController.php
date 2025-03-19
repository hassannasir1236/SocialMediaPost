<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class SocialAuthController extends Controller
{
    // public function redirect($provider)
    // {
    //     return Socialite::driver($provider)->redirect();
    // }
    public function redirect($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }
    


    // public function callback($provider)
    // {
    //     $socialUser = Socialite::driver($provider)->user();

    //     $user = User::firstOrCreate(
    //         ['email' => $socialUser->getEmail()],
    //         [
    //             'name' => $socialUser->getName(),
    //             'provider' => $provider,
    //             'provider_id' => $socialUser->getId(),
    //         ]
    //     );

    //     $token = JWTAuth::fromUser($user);

    //     return response()->json(['token' => $token]);
    // }
    public function callback($provider)
{
    try {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        // Ensure the user has an email (some providers may not return it)
        if (!$socialUser->getEmail()) {
            return response()->json(['error' => 'Email is required'], 422);
        }

        // Find or create user
        $user = User::updateOrCreate(
            ['email' => $socialUser->getEmail()], // Ensure uniqueness
            [
                'name' => $socialUser->getName(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]
        );

        // Ensure user implements JWTSubject before generating token
        if (!$user instanceof \Tymon\JWTAuth\Contracts\JWTSubject) {
            throw new \Exception("User model must implement JWTSubject.");
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
    }
}


}

