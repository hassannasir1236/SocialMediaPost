<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Auth;

class SocialPostController extends Controller
{
    public function post(Request $request, $provider)
    {
        $user = Auth::user();
        $socialAccount = SocialAccount::where('user_id', $user->id)->where('provider', $provider)->first();

        if (!$socialAccount) {
            return response()->json(['error' => ucfirst($provider) . ' account not connected'], 400);
        }

        $accessToken = $socialAccount->access_token;

        if ($provider === 'youtube') {
            return $this->postToYouTube($accessToken, $request);
        } elseif ($provider === 'facebook') {
            return $this->postToFacebook($accessToken, $request);
        } elseif ($provider === 'twitter') {
            return $this->postToTwitter($accessToken, $request);
        } else {
            return response()->json(['error' => 'Unsupported provider'], 400);
        }
    }

    private function postToYouTube($accessToken, Request $request)
    {
        $video = $request->file('video');
        $title = $request->input('title', 'Default Title');
        $description = $request->input('description', 'Default Description');

        $response = Http::withToken($accessToken)->attach(
            'video', file_get_contents($video->getPathname()), $video->getClientOriginalName()
        )->post('https://www.googleapis.com/upload/youtube/v3/videos', [
            'part' => 'snippet,status',
            'snippet' => [
                'title' => $title,
                'description' => $description,
            ],
            'status' => [
                'privacyStatus' => 'public',
            ],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to upload video to YouTube'], 500);
        }

        return response()->json(['message' => 'Video uploaded successfully!', 'data' => $response->json()]);
    }
}

