<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fragment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        Log::info('Uploaded file path', ['file_path' => $filePath]); // ログにファイルパスを出力

        // AIにPDFを送信して解析
        $fragments = $this->analyzePdfWithAI($filePath); // AIサービスにリクエスト

        if ($fragments === null) {
            return response()->json(['error' => 'Failed to analyze PDF'], 500); // エラー時のレスポンス
        }

        // フラグメント化されたデータをデータベースに保存
        foreach ($fragments as $fragment) { // フラグメントをデータベースに保存
            $vector = isset($fragment['vector']) ? json_encode($fragment['vector']) : null; // ベクトルを保存
            Fragment::create([
                'content' => $fragment['content'], // コンテンツを保存
                'fragment_id' => $fragment['id'], // フラグメントIDを保存
                'vector' => $vector, // ベクトルを保存
                'file_path' => 'uploads/' . $fileName, // ファイルパスを保存
            ]);
        }

        return response()->json(['success' => 'File uploaded and analyzed successfully.']); // 成功時のレスポンス
    }

    private function analyzePdfWithAI($filePath)
    {
        $fileContent = file_get_contents($filePath); // ファイルの内容を取得

        // AIサービスへのリクエスト
        $response = Http::post('http://ai-service:5000/api/analyze', [
            'file_content' => base64_encode($fileContent), // ファイルの内容をBase64エンコードして送信
        ]);

        Log::info('AI Service Request', [
            'url' => 'http://ai-service:5000/api/analyze', // リクエストURL
            'payload' => base64_encode($fileContent), // リクエストボディ
            'response_status' => $response->status(),   // レスポンスステータス
            'response_body' => $response->body(),    // レスポンスボディ
        ]);

        if ($response->failed()) {
            return null; // リクエストが失敗した場合はnullを返す
        }

        return $response->json()['fragments'] ?? null; // レスポンスからフラグメントを取得
    }
}
