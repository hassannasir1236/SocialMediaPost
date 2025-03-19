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
        $socialAccount = SocialAccount::where('user_id', Auth::id())->where('provider', $provider)->first();

        if (!$socialAccount) {
            return response()->json(['error' => 'Account not connected'], 401);
        }

        $accessToken = $socialAccount->access_token;

        if ($provider == 'facebook') {
            $url = "https://graph.facebook.com/v18.0/me/feed";
            $response = Http::post($url, [
                'message'      => $request->input('message'),
                'access_token' => $accessToken,
            ]);
        } elseif ($provider == 'twitter') {
            $url = "https://api.twitter.com/2/tweets";
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->post($url, [
                'text' => $request->input('message'),
            ]);
        } elseif ($provider == 'youtube') {
            $videoPath = $request->file('video')->store('videos', 'public');

            $response = Http::withToken($accessToken)->attach(
                'video', file_get_contents(storage_path("app/public/$videoPath")), 'video.mp4'
            )->post("https://www.googleapis.com/upload/youtube/v3/videos", [
                'part'       => 'snippet,status',
                'snippet'    => [
                    'title'       => $request->input('title'),
                    'description' => $request->input('description'),
                    'tags'        => explode(',', $request->input('tags')),
                    'categoryId'  => '22', // Example: People & Blogs
                ],
                'status' => [
                    'privacyStatus' => 'public', // Can be 'private' or 'unlisted'
                ],
            ]);
        } else {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        if ($response->successful()) {
            return response()->json(['success' => 'Post published!']);
        }

        return response()->json(['error' => 'Post failed'], 500);
    }
}

