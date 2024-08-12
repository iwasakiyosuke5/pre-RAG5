<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Models\Fragment;
// use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;

// class SearchController extends Controller
// {

//     public function search(Request $request)
//     {
//         $query = $request->input('query');
//         $vector = $this->vectorize($query);

//         $fragments = Fragment::all();
//         $results = [];

//         foreach ($fragments as $fragment) {
//             $storedVector = json_decode($fragment->vector, true);
//             if (is_null($storedVector)) {
//                 Log::error('Stored vector is null', ['fragment_id' => $fragment->id]);
//                 continue;
//             }
//             $similarity = $this->cosineSimilarity($vector, $storedVector);
//             $results[] = ['fragment' => $fragment, 'similarity' => $similarity];
//         }

//         usort($results, function ($a, $b) {
//             return $b['similarity'] <=> $a['similarity'];
//         });

//         // コンテキストを作成する
//         $context = implode("\n", array_map(function($result) {
//             return $result['fragment']->content;
//         }, array_slice($results, 0, 5))); // 上位5つの結果をコンテキストとして使用

//         $aiResponse = $this->askAI($query, $context);

//         return view('response', [
//             'question' => $query,
//             'aiResponse' => $aiResponse,
//             'results' => $results
//         ]);
//     }


//     // SearchController.php

//     private function vectorize($text)
//     {
//         $aiServiceHost = env('AI_SERVICE_HOST', 'http://localhost:5000');
        
//         // AIサービスにテキストを送信してベクトル化
//         $response = Http::post("$aiServiceHost/api/vectorize", [
//             'text' => $text,
//         ]);

//         return $response->json()['vector'];
//     }


//     private function cosineSimilarity($vec1, $vec2)
//     {
//         if (!is_array($vec1) || !is_array($vec2)) {
//             Log::error('Vectors must be arrays', ['vec1' => $vec1, 'vec2' => $vec2]);
//             return 0;
//         }

//         $dotProduct = array_sum(array_map(function($a, $b) { return $a * $b; }, $vec1, $vec2));
//         $magnitude1 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec1)));
//         $magnitude2 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec2)));

//         if ($magnitude1 == 0 || $magnitude2 == 0) {
//             return 0;
//         }

//         return $dotProduct / ($magnitude1 * $magnitude2);
//     }

//     // private function askAI($query, $context)
//     // {
//     //     $response = Http::post('http://ai-service:5000/api/ask', [
//     //         'query' => $query,
//     //         'context' => $context,
//     //     ]);

//     //     return $response->json()['answer'];
//     // }

//     private function askAI($query, $context)
//     {
//         try {
//             Log::info('Sending request to AI service', ['question' => $query, 'context' => $context]);
    
//             $response = Http::post('http://ai-service:5000/api/ask', [
//                 'question' => $query,
//                 'context' => $context,
//             ]);
    
//             Log::info('Received response from AI service', ['response' => $response->body()]);
    
//             if ($response->successful()) {
//                 $data = $response->json();
//                 if (isset($data['answer'])) {
//                     return $data['answer'];
//                 } else {
//                     Log::error('AI response does not contain answer', ['response' => $data]);
//                     return 'AI response does not contain an answer.';
//                 }
//             } else {
//                 Log::error('AI request failed', ['status' => $response->status(), 'body' => $response->body()]);
//                 return 'Failed to get a response from the AI service.';
//             }
//         } catch (\Exception $e) {
//             Log::error('Exception during AI request', ['message' => $e->getMessage()]);
//             return 'An error occurred while communicating with the AI service.';
//         }
//     }
    
// }



namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fragment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    private function extractKeywords($query)
    {
        // シンプルな例として、スペースで区切ることでキーワードを抽出
        // より高度な方法を使用して、意味のあるキーワードを抽出することも可能
        return explode(' ', $query);
    }
    public function search(Request $request)
    {
        // $query = $request->input('query');
        // $vector = $this->vectorize($query);

        // $fragments = Fragment::all();
        // $results = [];

        // foreach ($fragments as $fragment) {
        //     $storedVector = json_decode($fragment->vector, true);
        //     if (is_null($storedVector)) {
        //         Log::error('Stored vector is null', ['fragment_id' => $fragment->id]);
        //         continue;
        //     }
        //     $similarity = $this->cosineSimilarity($vector, $storedVector);
        //     $results[] = ['fragment' => $fragment, 'similarity' => $similarity];
        // }

        // usort($results, function ($a, $b) {
        //     return $b['similarity'] <=> $a['similarity'];
        // });

        // // コンテキストを作成する
        // $context = implode("\n", array_map(function($result) {
        //     return $result['fragment']->content;
        // }, array_slice($results, 0, 5))); // 上位5つの結果をコンテキストとして使用

        // $aiResponse = $this->askAI($query, $context);

        // return view('response', [
        //     'question' => $query,
        //     'aiResponse' => $aiResponse,
        //     'results' => $results
        // ]);

        $query = $request->input('query');
        $vector = $this->vectorize($query);
    
        $fragments = Fragment::all();
        $results = [];
    
        foreach ($fragments as $fragment) {
            $storedVector = json_decode($fragment->vector, true);
            if (is_null($storedVector)) {
                Log::error('Stored vector is null', ['fragment_id' => $fragment->id]);
                continue;
            }
            $similarity = $this->cosineSimilarity($vector, $storedVector);
            $results[] = ['fragment' => $fragment, 'similarity' => $similarity];
        }
    
        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
    
        // クエリからキーワードを抽出
        $keywords = $this->extractKeywords($query);
    
        // キーワードを含むフラグメントをフィルタリング
        $filteredResults = array_filter($results, function($result) use ($keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($result['fragment']->content, $keyword) !== false) {
                    return true;
                }
            }
            return false;
        });
    
        // もしキーワードに一致するフラグメントがなければ、上位5つの結果を使用
        if (empty($filteredResults)) {
            $filteredResults = array_slice($results, 0, 5);
        }
    
        // フィルタリングされた結果からコンテキストを作成（または上位5つを使用）
        $context = implode("\n", array_map(function($result) {
            return $result['fragment']->content;
        }, array_slice($filteredResults, 0, 5)));
    
        $aiResponse = $this->askAI($query, $context);
    
        return view('response', [
            'question' => $query,
            'aiResponse' => $aiResponse,
            'results' => $results
        ]);
    }

    private function vectorize($text)
    {
        $aiServiceHost = env('AI_SERVICE_HOST', 'http://localhost:5000');
        
        // AIサービスにテキストを送信してベクトル化
        $response = Http::post("$aiServiceHost/api/vectorize", [
            'text' => $text,
        ]);

        return $response->json()['vector'];
    }

    private function cosineSimilarity($vec1, $vec2)
    {
        if (!is_array($vec1) || !is_array($vec2)) {
            Log::error('Vectors must be arrays', ['vec1' => $vec1, 'vec2' => $vec2]);
            return 0;
        }

        $dotProduct = array_sum(array_map(function($a, $b) { return $a * $b; }, $vec1, $vec2));
        $magnitude1 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec1)));
        $magnitude2 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec2)));

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    // private function askAI($query, $context)
    // {
    //     try {
    //         Log::info('Sending request to AI service', ['question' => $query, 'context' => $context]);
    
    //         $response = Http::post('http://ai-service:5000/api/ask', [
    //             'question' => $query,
    //             'context' => $context,
    //         ]);
    
    //         Log::info('Received response from AI service', ['response' => $response->body()]);
    
    //         if ($response->successful()) {
    //             $data = $response->json();
    //             if (isset($data['answer'])) {
    //                 return $data['answer'];
    //             } else {
    //                 Log::error('AI response does not contain answer', ['response' => $data]);
    //                 return 'AI response does not contain an answer.';
    //             }
    //         } else {
    //             Log::error('AI request failed', ['status' => $response->status(), 'body' => $response->body()]);
    //             return 'Failed to get a response from the AI service.';
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Exception during AI request', ['message' => $e->getMessage()]);
    //         return 'An error occurred while communicating with the AI service.';
    //     }
    // }
//     private function askAI($query, $context)
// {
//     try {
//         Log::info('Sending request to OpenAI service', ['question' => $query, 'context' => $context]);

//         // OpenAI APIキーを環境変数から取得
//         $apiKey = env('OPENAI_API_KEY');

//         // OpenAI APIにリクエストを送信
//         $response = Http::withHeaders([
//             'Authorization' => 'Bearer ' . $apiKey,
//         ])->post('https://api.openai.com/v1/chat/completions', [
//             'model' => 'gpt-3.5-turbo',  // 使用するモデルを指定
//             'prompt' => "Question: $query\nContext: $context\nAnswer:",
//             'max_tokens' => 150,
//             'temperature' => 0.7,
//         ]);

//         Log::info('Received response from OpenAI service', ['response' => $response->body()]);

//         if ($response->successful()) {
//             $data = $response->json();
//             if (isset($data['choices'][0]['text'])) {
//                 return $data['choices'][0]['text'];
//             } else {
//                 Log::error('OpenAI response does not contain an answer', ['response' => $data]);
//                 return 'OpenAI response does not contain an answer.';
//             }
//         } else {
//             Log::error('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);
//             return 'Failed to get a response from the OpenAI service.';
//         }
//     } catch (\Exception $e) {
//         Log::error('Exception during OpenAI request', ['message' => $e->getMessage()]);
//         return 'An error occurred while communicating with the OpenAI service.';
//     }
// }
private function askAI($query, $context)
{
    $apiKey = env('OPENAI_API_KEY');
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $apiKey,
    ])->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-3.5-turbo',  // または 'gpt-4' など、使用するモデルを指定
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant.'
            ],
            [
                'role' => 'user',
                'content' => "Question: $query\nContext: $context\nAnswer:"
            ],
        ],
        'max_tokens' => 150,
        'temperature' => 0.7,
    ]);

    if ($response->successful()) {
        return $response->json()['choices'][0]['message']['content'];
    } else {
        Log::error('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);
        return 'Failed to get a response from the OpenAI service.';
    }
}


}
