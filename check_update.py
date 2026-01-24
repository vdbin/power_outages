import requests
import os

# Налаштування
URL = "https://api.github.com/repos/yaroslav2901/OE_OUTAGE_DATA/commits?path=data/Ternopiloblenerho.json&page=1&per_page=1"
TOKEN = os.getenv("TELEGRAM_TOKEN")
CHAT_ID = os.getenv("TELEGRAM_CHAT_ID")

def send_tg_message(text):
    tg_url = f"https://api.telegram.org/bot{TOKEN}/sendMessage"
    requests.post(tg_url, data={"chat_id": CHAT_ID, "text": text})

def check():
    response = requests.get(URL)
    if response.status_code == 200:
        latest_commit_sha = response.json()[0]['sha']

        # Читаємо останній збережений хеш
        if os.path.exists("last_sha.txt"):
            with open("last_sha.txt", "r") as f:
                last_sha = f.read().strip()
        else:
            last_sha = ""

        if latest_commit_sha != last_sha:
            send_tg_message("https://raw.githubusercontent.com/yaroslav2901/OE_OUTAGE_DATA/refs/heads/main/images/Ternopiloblenerho/gpv-1-2-emergency.png")
            # Зберігаємо новий хеш
            with open("last_sha.txt", "w") as f:
                f.write(latest_commit_sha)
            return True
    return False

if __name__ == "__main__":
    check()