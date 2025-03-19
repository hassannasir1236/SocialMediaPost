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
        if ($provider == 'facebook') {
            $url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
                'client_id'     => env('FACEBOOK_APP_ID'),
                'redirect_uri'  => route('social.auth', ['provider' => 'facebook']),
                'scope'         => 'publish_to_groups,pages_show_list,pages_manage_posts',
                'response_type' => 'code',
            ]);
        } elseif ($provider == 'twitter') {
            $url = "https://api.twitter.com/oauth/authorize?" . http_build_query([
                'client_id'    => env('TWITTER_CLIENT_ID'),
                'redirect_uri' => route('social.auth', ['provider' => 'twitter']),
                'response_type' => 'code',
                'scope'         => 'tweet.read tweet.write users.read offline.access',
            ]);
        } elseif ($provider == 'youtube') {
            $url = "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
                'client_id'     => env('YOUTUBE_CLIENT_ID'),
                'redirect_uri'  => url('/api/auth/' . $provider . '/callback'),
                'response_type' => 'code',
                'scope'         => 'https://www.googleapis.com/auth/youtube.upload',
                'access_type'   => 'offline',
            ]);
        } else {
            return abort(404);
        }

        return redirect($url);
    }

    
    public function callback(Request $request, $provider)
    {
        $code = $request->get('code');

        if (!$code) {
            return redirect('/')->with('error', 'Authentication failed!');
        }

        if ($provider == 'youtube') {
            $tokenUrl = 'https://oauth2.googleapis.com/token';
        } elseif ($provider == 'facebook') {
            $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
        } elseif ($provider == 'twitter') {
            $tokenUrl = 'https://api.twitter.com/2/oauth2/token';
        } else {
            return redirect('/')->with('error', 'Invalid provider');
        }

        $response = Http::asForm()->post($tokenUrl, [
            'client_id'     => env(strtoupper($provider) . '_CLIENT_ID'),
            'client_secret' => env(strtoupper($provider) . '_CLIENT_SECRET'),
            'redirect_uri'  => route('social.auth', ['provider' => $provider]),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            return redirect('/')->with('error', 'Authentication failed!');
        }

        $data = $response->json();
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;

        SocialAccount::updateOrCreate(
            ['user_id' => Auth::id(), 'provider' => $provider],
            ['provider_user_id' => $data['user_id'] ?? '', 'access_token' => $accessToken, 'refresh_token' => $refreshToken]
        );

        return redirect('/')->with('success', ucfirst($provider) . ' account connected!');
    }
}
