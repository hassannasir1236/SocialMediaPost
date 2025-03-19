<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Auth;

class SocialPostAuthController extends Controller
{
    public function redirect($provider)
    {
        // Ensure user is logged in
        if (!Auth::check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $redirectUri = route('social.auth.callback', ['provider' => $provider]);

        $authUrls = [
            'facebook' => "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
                'client_id'     => env('FACEBOOK_APP_ID'),
                'redirect_uri'  => $redirectUri,
                'scope'         => 'publish_to_groups,pages_show_list,pages_manage_posts',
                'response_type' => 'code',
            ]),
            'twitter' => "https://api.twitter.com/oauth/authorize?" . http_build_query([
                'client_id'    => env('TWITTER_CLIENT_ID'),
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope'         => 'tweet.read tweet.write users.read offline.access',
            ]),
            'youtube' => "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
                'client_id'     => env('YOUTUBE_CLIENT_ID'),
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => 'https://www.googleapis.com/auth/youtube.upload',
                'access_type'   => 'offline',
            ]),
        ];

        if (!isset($authUrls[$provider])) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        return response()->json(['auth_url' => $authUrls[$provider]]);
    }

    public function callback(Request $request, $provider)
    {
        // Ensure user is logged in
        if (!Auth::check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $code = $request->get('code');
        if (!$code) {
            return response()->json(['error' => 'Authorization code missing'], 400);
        }

        // Define token URL for the provider
        $tokenUrls = [
            'youtube' => 'https://oauth2.googleapis.com/token',
            'facebook' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'twitter' => 'https://api.twitter.com/2/oauth2/token',
        ];

        if (!isset($tokenUrls[$provider])) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        $tokenUrl = $tokenUrls[$provider];

        // Exchange code for access token
        $response = Http::asForm()->post($tokenUrl, [
            'client_id'     => env(strtoupper($provider) . '_CLIENT_ID'),
            'client_secret' => env(strtoupper($provider) . '_CLIENT_SECRET'),
            'redirect_uri'  => route('social.auth.callback', ['provider' => $provider]),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to authenticate with ' . ucfirst($provider)], 400);
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$accessToken) {
            return response()->json(['error' => 'Access token not received'], 400);
        }

        // Store or update social account details linked to the authenticated user
        $socialAccount = SocialAccount::updateOrCreate(
            ['user_id' => Auth::id(), 'provider' => $provider],
            [
                'provider_user_id' => $data['user_id'] ?? '',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
            ]
        );

        return response()->json([
            'message' => ucfirst($provider) . ' account connected successfully!',
            'social_account' => $socialAccount
        ], 200);
    }
}
