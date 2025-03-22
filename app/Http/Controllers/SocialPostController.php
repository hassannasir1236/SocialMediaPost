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
        $socialAccount = SocialAccount::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();
    
        if (!$socialAccount) {
            return response()->json(['error' => ucfirst($provider) . ' account not connected'], 400);
        }
    
        $accessToken = $socialAccount->access_token;
    
        // Validate request based on provider
        $validator = $this->validatePostRequest($request, $provider);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
    
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
    
    /**
     * Validate request parameters based on provider
     */
    private function validatePostRequest(Request $request, $provider)
    {
        $rules = [];
    
        switch ($provider) {
            case 'youtube':
                $rules = [
                    'title'       => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'video'       => 'required|file|mimes:mp4,mov,avi,mkv|max:50000', // Limit to 50MB
                ];
                break;
    
            case 'facebook':
                $rules = [
                    'message'   => 'required|string|max:2000',
                    'link'      => 'nullable|url',
                    'image'     => 'nullable|file|mimes:jpg,jpeg,png,gif|max:10000', // Limit to 10MB
                ];
                break;
    
            case 'twitter':
                $rules = [
                    'message'   => 'required|string|max:280',
                    'image'     => 'nullable|file|mimes:jpg,jpeg,png,gif|max:5000', // Limit to 5MB
                ];
                break;
    
            default:
                return validator($request->all(), []);
        }
    
        return validator($request->all(), $rules);
    }
    

    private function postToYouTube($accessToken, Request $request)
    {
        $video = $request->file('video');
        $title = $request->input('title', 'Default Title');
        $description = $request->input('description', 'Default Description');
    
        $metadata = json_encode([
            'snippet' => [
                'title' => $title,
                'description' => $description,
            ],
            'status' => [
                'privacyStatus' => 'public',
            ],
        ]);
    
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'multipart/related; boundary=boundary_string'
        ])->send('POST', 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=multipart&part=snippet,status', [
            'body' => "--boundary_string\r\n"
                . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
                . json_encode([
                    'snippet' => [
                        'title'       => $title,
                        'description' => $description,
                        'categoryId'  => '22'
                    ],
                    'status' => [
                        'privacyStatus' => 'public'
                    ]
                ]) . "\r\n"
                . "--boundary_string\r\n"
                . "Content-Type: video/mp4\r\n"
                . "Content-Transfer-Encoding: binary\r\n\r\n"
                . file_get_contents($video->getRealPath()) . "\r\n"
                . "--boundary_string--",
        ]);
        
        if ($response->failed()) {
            return response()->json([
                'error'   => 'Failed to upload video to YouTube',
                'status'  => $response->status(),
                'details' => $response->body(),
            ], 500);
        }
        
        return response()->json([
            'message' => 'Video uploaded successfully!',
            'data'    => $response->json(),
        ]);
        
    

    }
    
}

