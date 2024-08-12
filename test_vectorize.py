import requests

# ベクトル化するテキスト
text = "This is a test text for vectorization."

# AIサービスへのリクエストを送信
response = requests.post('http://localhost:5000/api/vectorize', json={'text': text})

# ステータスコードとレスポンス内容を表示
print(f"Status Code: {response.status_code}")
print(f"Response Text: {response.text}")

try:
    response_json = response.json()
    print(response_json)
except requests.exceptions.JSONDecodeError as e:
    print("JSON decode error:", e)
