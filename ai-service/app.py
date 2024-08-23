
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
    data = request.get_json() # リクエストデータを取得
    file_content = data.get('file_content', '')  # リクエストデータからfile_contentを取得
    file_bytes = base64.b64decode(file_content) # base64エンコードされたファイルをデコード

    # PDFをテキストに変換
    text = extract_text_from_pdf(file_bytes) # PDFファイルをテキストに変換
    
    # テキストをベクトル化する
    vector = model.encode(text) # テキストをベクトル化
    
    fragment = {'content': text, 'id': 0, 'vector': vector.tolist()} # フラグメントを作成
    
    return jsonify({'fragments': [fragment]}) # レスポンスを返す

@app.route('/api/vectorize', methods=['POST'])
def vectorize():
    data = request.get_json() # リクエストデータを取得
    text = data.get('text', '') # リクエストデータからtextを取得
    
    if not text:
        return jsonify({'error': 'No text provided'}), 400  # テキストがない場合はエラーを返す

    vector = model.encode(text) # テキストをベクトル化
    return jsonify({'vector': vector.tolist()})     # ベクトルを返す

def extract_text_from_pdf(pdf_bytes): # PDFファイルをテキストに変換
    doc = fitz.open(stream=pdf_bytes, filetype="pdf") # PyMuPDFを使用してPDFファイルを開く
    text = "" # テキストを初期化
    for page in doc: # ページごとにテキストを取得
        text += page.get_text() # ページのテキストを取得してtextに追加
    return text # テキストを返す

# @app.route('/api/generate', methods=['POST'])
# def generate(): # 回答を生成
#     data = request.json # リクエストデータを取得
#     query = data.get('query') # リクエストデータからqueryを取得
#     fragments = data.get('fragments') # リクエストデータからfragmentsを取得

#     if not query or not fragments: # queryまたはfragmentsがない場合はエラーを返す
#         return jsonify({'error': 'Invalid input'}), 400   

#     # フラグメントとクエリを組み合わせて回答を生成
#     context = " ".join(fragments) # フラグメントを結合
#     prompt = f"Question: {query}\nContext: {context}\nAnswer:" # プロンプトを作成

#     try:
#         answer = qa_pipeline(question=query, context=context)
#     except Exception as e:
#         return jsonify({'error': str(e)}), 500

#     return jsonify({'answer': answer['answer']}) # 回答を返す

# @app.route('/api/ask', methods=['POST'])
# def ask():
#     data = request.json
#     question = data.get('question')
#     context = data.get('context')

#     if not question or not context:
#         return jsonify({'error': 'Invalid input'}), 400

#     # AIモデルに質問とコンテキストを渡して回答を生成
#     answer = generate_answer(question, context)

#     return jsonify({'answer': answer})

# def generate_answer(question, context):
#     # 質問とコンテキストを使用してAIモデルから回答を生成するロジックを実装
#     result = qa_pipeline(question=question, context=context) 
#     return result['answer']

if __name__ == '__main__': # メイン関数
    app.run(debug=True, host='0.0.0.0', port=5000) # ローカルサーバーを起動
