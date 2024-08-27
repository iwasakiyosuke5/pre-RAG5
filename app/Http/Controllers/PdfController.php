<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fragment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class PdfController extends Controller
{
    public function upload(Request $request)
    {
    
        $request->validate([
            'file' => 'required|mimes:pdf|max:2048', // ファイルのバリデーション
        ]);
    
        $fileName = time().'.'.$request->file->extension();   // ファイル名を生成
        $filePath = public_path('uploads/' . $fileName);     // ファイルパスを生成
        $request->file->move(public_path('uploads'), $fileName); // ファイルを保存
    
        Log::info('Uploaded file path', ['file_path' => $filePath]);
    
        // AIにPDFを送信してテキストを抽出
        $fragments = $this->analyzePdfWithAI($filePath); // AIサービスにリクエスト
    
        if ($fragments === null) {
            Log::error('Failed to analyze PDF');
            return response()->json(['error' => 'Failed to analyze PDF'], 500); // エラー時のレスポンス
        }
    
        Log::info('Text fragments received', ['fragments' => $fragments]);
    
        // 抽出されたテキストをOpenAI APIでベクトル化
        foreach ($fragments as $fragment) {
            $vector = $this->vectorizeWithOpenAI($fragment['content']); // テキストをベクトル化
    
            if ($vector === null) {
                Log::error('Vectorization failed for fragment', ['fragment' => $fragment['content']]);
                continue;  // ベクトル化に失敗した場合はスキップ
            }
    
            Log::info('Vectorization succeeded', ['vector' => $vector]);
    
            Fragment::create([
                'content' => $fragment['content'], // コンテンツを保存
                'fragment_id' => $fragment['id'], // フラグメントIDを保存
                'vector' => json_encode($vector), // ベクトルを保存
                'file_path' => 'uploads/' . $fileName, // ファイルパスを保存
            ]);
        }
    
        return response()->json(['success' => 'File uploaded and analyzed successfully.']); // 成功時のレスポンス
    }



    private function vectorizeWithOpenAI($text)
{
    $apiKey = env('OPENAI_API_KEY'); // OpenAI APIキーを取得

    Log::info('Starting vectorization for text', ['text' => $text]);

    // OpenAI APIにテキストを送信してベクトル化
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $apiKey,
    ])->post('https://api.openai.com/v1/embeddings', [
        'model' => 'text-embedding-3-small',  // 使用するモデル
        'input' => $text,
    ]);

    if ($response->successful()) {
        Log::info('OpenAI API successful', ['response' => $response->json()]);
        return $response->json()['data'][0]['embedding'];  // ベクトルを返す
    } else {
        Log::error('OpenAI vectorization failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        return null;  // エラー時はnullを返す
    }
}

    // AIサービスにPDFを送信してテキスト化する部分
    private function analyzePdfWithAI($filePath)
    {
        $fileContent = file_get_contents($filePath); // ファイルの内容を取得

        // AIサービスへのリクエスト
        $response = Http::post('http://ai-service:5000/api/analyze', [
            'file_content' => base64_encode($fileContent), // ファイルの内容をBase64エンコードして送信
        ]);

        if ($response->failed()) {
            return null; // リクエストが失敗した場合はnullを返す
        }

        return $response->json()['fragments'] ?? null; // レスポンスからフラグメントを取得
    }
}
