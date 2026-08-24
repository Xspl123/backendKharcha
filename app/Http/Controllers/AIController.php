<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function askAI(Request $request)
    {
        try {

            // Validation
            $request->validate([
                'message' => 'required|string|max:500'
            ]);

            // OpenAI API call
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [

                    'model' => 'gpt-4.1-mini',

                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $request->message
                        ]
                    ],

                    'temperature' => 0.7,
                    'max_tokens' => 200
                ]);

            if ($response->failed()) {

                Log::error('OpenAI Error', [
                    'body' => $response->body()
                ]);

                return response()->json([
                    'error' => 'AI service failed'
                ], 500);
            }

            $reply = $response['choices'][0]['message']['content'];

            return response()->json([
                'reply' => $reply
            ]);

        } catch (\Exception $e) {

            Log::error('AI Exception', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Something went wrong'
            ], 500);
        }
    }
}