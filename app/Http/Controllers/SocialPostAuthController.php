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
        // if (!Auth::check()) {
        //     return response()->json(['error' => 'User not authenticated'], 401);
        // }

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
                'prompt'        => 'consent', 
            ]),
        ];

        if (!isset($authUrls[$provider])) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        return redirect()->away($authUrls[$provider]);
    }

        // if (!Auth::check()) {
        //     return response()->json(['error' => 'User not authenticated'], 401);
        // }
    public function callback(Request $request, $provider)
    {
        $code = $request->get('code');
        if (!$code) {
            return response()->json(['error' => 'Authorization code missing'], 400);
        }
        $tokenUrls = [
            'youtube'   => 'https://oauth2.googleapis.com/token',
            'facebook'  => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'twitter'   => 'https://api.twitter.com/2/oauth2/token',
        ];

        if (!isset($tokenUrls[$provider])) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        $response = Http::asForm()->post($tokenUrls[$provider], [
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
        print_r($data);
        $accessToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;
    
        if (!$accessToken) {
            return response()->json(['error' => 'Access token not received'], 400);
        }
        $existingAccount = SocialAccount::where('user_id', '1')
                                        ->where('provider', $provider)
                                        ->first();
    
        if (empty($refreshToken) && $existingAccount) {
            $refreshToken = $existingAccount->refresh_token;
        }

        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;
        echo '################';
        print_r($data['refresh_token']);
        $socialAccount = SocialAccount::updateOrCreate(
            ['user_id' => '1', 'provider' => $provider],
            [
                'provider_user_id' => $data['user_id'] ?? '',
                'access_token'     => $accessToken,
                'refresh_token'    => $data['refresh_token'], 
                'expires_at'       => $expiresAt,
            ]
        );

        return response()->json([
            'message'         => ucfirst($provider) . ' account connected successfully!',
            'social_account'  => $socialAccount
        ], 200);
    }        
}
