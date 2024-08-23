<?php
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

        $query = $request->input('query'); // ユーザーのクエリを取得
        $vector = $this->vectorize($query); // クエリをベクトル化
    
        $fragments = Fragment::all();   // データベースからすべてのフラグメントを取得
        $results = []; // 類似度の結果を格納する配列
    
        foreach ($fragments as $fragment) { // すべてのフラグメントに対して類似度を計算
            $storedVector = json_decode($fragment->vector, true); // データベースに保存されたベクトルを取得
            if (is_null($storedVector)) {   // ベクトルがnullの場合はスキップ
                Log::error('Stored vector is null', ['fragment_id' => $fragment->id]);
                continue;
            }
            $similarity = $this->cosineSimilarity($vector, $storedVector);  // 類似度を計算
            $results[] = ['fragment' => $fragment, 'similarity' => $similarity];        // 結果を配列に追加
        }
    
        usort($results, function ($a, $b) { // 類似度で結果をソート
            return $b['similarity'] <=> $a['similarity'];     // 降順にソート
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
        $aiServiceHost = env('AI_SERVICE_HOST', 'http://localhost:5000'); // AIサービスのホストを取得
        
        // AIサービスにテキストを送信してベクトル化
        $response = Http::post("$aiServiceHost/api/vectorize", [    // ベクトル化APIにリクエスト
            'text' => $text,        // テキストを送信
        ]);

        return $response->json()['vector']; // レスポンスからベクトルを取得
    }

    private function cosineSimilarity($vec1, $vec2) // コサイン類似度を計算
    {
        if (!is_array($vec1) || !is_array($vec2)) { // ベクトルが配列でない場合はエラー
            Log::error('Vectors must be arrays', ['vec1' => $vec1, 'vec2' => $vec2]);   // エラーログを出力
            return 0;   // 類似度を0として返す
        }

        $dotProduct = array_sum(array_map(function($a, $b) { return $a * $b; }, $vec1, $vec2)); // 内積を計算
        $magnitude1 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec1)));  // ベクトル1の大きさを計算
        $magnitude2 = sqrt(array_sum(array_map(function($a) { return $a * $a; }, $vec2)));  // ベクトル2の大きさを計算

        if ($magnitude1 == 0 || $magnitude2 == 0) { // どちらかのベクトルの大きさが0の場合はエラー
            return 0;   // 類似度を0として返す
        }

        return $dotProduct / ($magnitude1 * $magnitude2);   // コサイン類似度を計算して返す
    }


    private function askAI($query, $context)
    {
        $apiKey = env('OPENAI_API_KEY'); // OpenAI APIキーを取得
        $response = Http::withHeaders([ // OpenAI APIにリクエスト
            'Authorization' => 'Bearer ' . $apiKey, // APIキーをヘッダーに追加
        ])->post('https://api.openai.com/v1/chat/completions', [ // チャット補完APIにリクエスト
            'model' => 'gpt-3.5-turbo',  // または 'gpt-4' など、使用するモデルを指定
            'messages' => [
                [
                    'role' => 'system', // システムメッセージ
                    'content' => 'You are a helpful assistant.' // メッセージの内容
                ],
                [
                    'role' => 'user',   // ユーザーメッセージ
                    'content' => "Question: $query\nContext: $context\nAnswer:" // クエリとコンテキストを含むメッセージ
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
