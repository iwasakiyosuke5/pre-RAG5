
# from flask import Flask, request, jsonify
# from sentence_transformers import SentenceTransformer
# import fitz
# import base64
# from transformers import pipeline

# app = Flask(__name__)

# # SentenceTransformerモデルの読み込み
# model = SentenceTransformer('all-MiniLM-L6-v2')
# qa_pipeline = pipeline("question-answering")

# @app.route('/api/analyze', methods=['POST'])
# def analyze():
#     data = request.get_json()
#     file_content = data.get('file_content', '')
#     file_bytes = base64.b64decode(file_content)

#     # PDFをテキストに変換
#     text = extract_text_from_pdf(file_bytes)
    
#     # テキストをベクトル化する
#     vector = model.encode(text)
    
#     fragment = {'content': text, 'id': 0, 'vector': vector.tolist()}
    
#     return jsonify({'fragments': [fragment]})

# @app.route('/api/vectorize', methods=['POST'])
# def vectorize():
#     data = request.get_json()
#     text = data.get('text', '')
    
#     if not text:
#         return jsonify({'error': 'No text provided'}), 400

#     vector = model.encode(text)
#     return jsonify({'vector': vector.tolist()})

# def extract_text_from_pdf(pdf_bytes):
#     doc = fitz.open(stream=pdf_bytes, filetype="pdf")
#     text = ""
#     for page in doc:
#         text += page.get_text()
#     return text

# @app.route('/api/generate', methods=['POST'])
# def generate():
#     data = request.json
#     query = data.get('query')
#     fragments = data.get('fragments')

#     if not query or not fragments:
#         return jsonify({'error': 'Invalid input'}), 400

#     # フラグメントとクエリを組み合わせて回答を生成
#     context = " ".join(fragments)
#     prompt = f"Question: {query}\nContext: {context}\nAnswer:"

#     try:
#         answer = qa_pipeline(question=query, context=context)
#     except Exception as e:
#         return jsonify({'error': str(e)}), 500

#     return jsonify({'answer': answer[0]['answer']})

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

# if __name__ == '__main__':
#     app.run(debug=True, host='0.0.0.0', port=5000)

from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import fitz
import base64
from transformers import pipeline

app = Flask(__name__)

# SentenceTransformerモデルの読み込み
model = SentenceTransformer('all-MiniLM-L6-v2')
qa_pipeline = pipeline("question-answering", model="deepset/roberta-base-squad2")

@app.route('/api/analyze', methods=['POST'])
def analyze():
    data = request.get_json()
    file_content = data.get('file_content', '')
    file_bytes = base64.b64decode(file_content)

    # PDFをテキストに変換
    text = extract_text_from_pdf(file_bytes)
    
    # テキストをベクトル化する
    vector = model.encode(text)
    
    fragment = {'content': text, 'id': 0, 'vector': vector.tolist()}
    
    return jsonify({'fragments': [fragment]})

@app.route('/api/vectorize', methods=['POST'])
def vectorize():
    data = request.get_json()
    text = data.get('text', '')
    
    if not text:
        return jsonify({'error': 'No text provided'}), 400

    vector = model.encode(text)
    return jsonify({'vector': vector.tolist()})

def extract_text_from_pdf(pdf_bytes):
    doc = fitz.open(stream=pdf_bytes, filetype="pdf")
    text = ""
    for page in doc:
        text += page.get_text()
    return text

@app.route('/api/generate', methods=['POST'])
def generate():
    data = request.json
    query = data.get('query')
    fragments = data.get('fragments')

    if not query or not fragments:
        return jsonify({'error': 'Invalid input'}), 400

    # フラグメントとクエリを組み合わせて回答を生成
    context = " ".join(fragments)
    prompt = f"Question: {query}\nContext: {context}\nAnswer:"

    try:
        answer = qa_pipeline(question=query, context=context)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

    return jsonify({'answer': answer['answer']})

@app.route('/api/ask', methods=['POST'])
def ask():
    data = request.json
    question = data.get('question')
    context = data.get('context')

    if not question or not context:
        return jsonify({'error': 'Invalid input'}), 400

    # AIモデルに質問とコンテキストを渡して回答を生成
    answer = generate_answer(question, context)

    return jsonify({'answer': answer})

def generate_answer(question, context):
    # 質問とコンテキストを使用してAIモデルから回答を生成するロジックを実装
    result = qa_pipeline(question=question, context=context)
    return result['answer']

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
