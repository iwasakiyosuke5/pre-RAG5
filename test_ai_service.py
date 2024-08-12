import requests
import base64

# PDFファイルのパスを指定
file_path = '/Users/tcigzacademy/Desktop/pdf-analysis2/AItext1.pdf'

with open(file_path, 'rb') as f:
    file_content = base64.b64encode(f.read()).decode('utf-8')

# AIサービスへのリクエストを送信
response = requests.post('http://localhost:5000/api/analyze', json={'file_content': file_content})

# ステータスコードとレスポンス内容を表示
print(f"Status Code: {response.status_code}")
print(f"Response Text: {response.text}")

try:
    response_json = response.json()
    print(response_json)
except requests.exceptions.JSONDecodeError as e:
    print("JSON decode error:", e)