"""تسجيل الدخول في تليجرام عبر QR Code مع عرض الصفحة على http://127.0.0.1:8765"""

import asyncio
import sys
import threading
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path

import qrcode
import qrcode.image.svg
from telethon import TelegramClient
from telethon.errors import SessionPasswordNeededError

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SCRIPTS_DIR = Path(__file__).resolve().parent
ENV_FILE = SCRIPTS_DIR / ".env.sync"
SESSION_PATH = str(SCRIPTS_DIR / "tg_session")
PORT = 8765

CURRENT_HTML = "<html><body>Loading QR...</body></html>"


def load_env(path: Path) -> dict[str, str]:
    env: dict[str, str] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def set_qr_html(url: str, status: str = "") -> None:
    global CURRENT_HTML
    img = qrcode.make(url, image_factory=qrcode.image.svg.SvgPathImage, box_size=12, border=2)
    svg = img.to_string(encoding="unicode")
    CURRENT_HTML = f"""<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="refresh" content="20" />
  <title>تسجيل الدخول في تليجرام (QR)</title>
  <style>
    body {{ font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; background: #0f172a; color: #f8fafc; }}
    .card {{ background: #1e293b; padding: 32px 40px; border-radius: 20px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,.4); max-width: 440px; }}
    .qr {{ background: #fff; padding: 18px; border-radius: 16px; display: inline-block; margin: 16px 0; }}
    .qr svg {{ width: 280px; height: 280px; display: block; }}
    p {{ color: #cbd5e1; line-height: 1.7; font-size: 16px; margin: 8px 0 0; }}
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin:0 0 8px">امسح الكود من تليجرام موبايل</h2>
    <div class="qr">{svg}</div>
    <p>افتح تليجرام على موبايلك ← <b>الإعدادات (Settings)</b> ← <b>الأجهزة (Devices)</b> ← <b>ربط جهاز كمبيوتر</b> وامسح الكود.</p>
    <p style="font-size:13px;color:#94a3b8">يتم تحديث الكود تلقائيًا كل 20 ثانية {status}</p>
  </div>
</body>
</html>"""


class QRHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        body = CURRENT_HTML.encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        pass


async def main() -> None:
    global CURRENT_HTML
    cfg = load_env(ENV_FILE)
    client = TelegramClient(SESSION_PATH, int(cfg["TG_API_ID"]), cfg["TG_API_HASH"])
    await client.connect()

    if await client.is_user_authorized():
        me = await client.get_me()
        print(f"✅ مسجّل دخول بالفعل باسم: {me.first_name} (@{me.username})")
        await client.disconnect()
        return

    qr = await client.qr_login()
    set_qr_html(qr.url)

    server = HTTPServer(("127.0.0.1", PORT), QRHandler)
    t = threading.Thread(target=server.serve_forever, daemon=True)
    t.start()
    print(f"📱 صفحة الـ QR جاهزة على http://127.0.0.1:{PORT} — امسحه من تليجرام موبايل...")

    for attempt in range(1, 15):
        try:
            await qr.wait(timeout=24)
            break
        except asyncio.TimeoutError:
            print(f"🔄 تحديث الكود ({attempt})...")
            await qr.recreate()
            set_qr_html(qr.url, f"(تحديث #{attempt})")
        except SessionPasswordNeededError:
            print("🔐 PASSWORD_REQUIRED")
            pw = input("🔐 الحساب عليه Two-Step Verification — اكتب كلمة السر: ")
            await client.sign_in(password=pw)
            break

    me = await client.get_me()
    if me:
        CURRENT_HTML = f"<html lang='ar' dir='rtl'><body style='font-family:system-ui;display:grid;place-items:center;min-height:100vh;background:#0f172a;color:#4ade80'><h1>✅ تم تسجيل الدخول بنجاح باسم {me.first_name}! يمكنك إغلاق هذه الصفحة.</h1></body></html>"
        print(f"✅ تم تسجيل الدخول بنجاح باسم: {me.first_name} (@{me.username})")
        await asyncio.sleep(2)
    else:
        print("⏰ انتهت مهلة مسح الـ QR Code بدون تسجيل دخول.")

    server.shutdown()
    await client.disconnect()


if __name__ == "__main__":
    asyncio.run(main())
