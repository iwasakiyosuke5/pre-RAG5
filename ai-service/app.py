
from flask import Flask, request, jsonify # Flaskをインポート
from sentence_transformers import SentenceTransformer # SentenceTransformerをインポート,ベクトル化モデル 
import fitz # PyMuPDFをインポート
import base64 # base64をインポート
from transformers import pipeline # transformersをインポート

app = Flask(__name__)

# SentenceTransformerモデルの読み込み
model = SentenceTransformer('all-MiniLM-L6-v2') # モデルを指定
qa_pipeline = pipeline("question-answering", model="deepset/roberta-base-squad2") # モデルを指定

@app.route('/api/analyze', methods=['POST'])
def analyze():  
    data = request.get_json()  # リクエストデータを取得
    file_content = data.get('file_content', '')  # リクエストデータからfile_contentを取得
    file_bytes = base64.b64decode(file_content)  # base64エンコードされたファイルをデコード

    # PDFをテキストに変換
    text = extract_text_from_pdf(file_bytes)  # PDFファイルをテキストに変換
    
    fragment = {'content': text, 'id': 0}  # テキストフラグメントを作成
    
    return jsonify({'fragments': [fragment]})  # テキストフラグメントを返す



def extract_text_from_pdf(pdf_bytes): # PDFファイルをテキストに変換
    doc = fitz.open(stream=pdf_bytes, filetype="pdf") # PyMuPDFを使用してPDFファイルを開く
    text = "" # テキストを初期化
    for page in doc: # ページごとにテキストを取得
        text += page.get_text() # ページのテキストを取得してtextに追加
    return text # テキストを返す


if __name__ == '__main__': # メイン関数
    app.run(debug=True, host='0.0.0.0', port=5000) # ローカルサーバーを起動
