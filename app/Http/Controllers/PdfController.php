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
            'file' => 'required|mimes:pdf|max:2048',
        ]);

        $fileName = time().'.'.$request->file->extension();  
        $filePath = public_path('uploads/' . $fileName);
        $request->file->move(public_path('uploads'), $fileName);

        Log::info('Uploaded file path', ['file_path' => $filePath]);

        // AIにPDFを送信して解析
        $fragments = $this->analyzePdfWithAI($filePath);

        if ($fragments === null) {
            return response()->json(['error' => 'Failed to analyze PDF'], 500);
        }

        // フラグメント化されたデータをデータベースに保存
        foreach ($fragments as $fragment) {
            $vector = isset($fragment['vector']) ? json_encode($fragment['vector']) : null;
            Fragment::create([
                'content' => $fragment['content'],
                'fragment_id' => $fragment['id'],
                'vector' => $vector,
                'file_path' => 'uploads/' . $fileName, // ファイルパスを保存
            ]);
        }

        return response()->json(['success' => 'File uploaded and analyzed successfully.']);
    }

    private function analyzePdfWithAI($filePath)
    {
        $fileContent = file_get_contents($filePath);

        // AIサービスへのリクエスト
        $response = Http::post('http://ai-service:5000/api/analyze', [
            'file_content' => base64_encode($fileContent),
        ]);

        Log::info('AI Service Request', [
            'url' => 'http://ai-service:5000/api/analyze',
            'payload' => base64_encode($fileContent),
            'response_status' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json()['fragments'] ?? null;
    }
}
