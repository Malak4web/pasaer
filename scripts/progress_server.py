"""لوحة متابعة حيّة لتقدم رفع صوتيات تليجرام على Archive.org على http://127.0.0.1:8765"""

import json
import re
from collections import Counter
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path

SCRIPTS_DIR = Path(__file__).resolve().parent
SYNCED_JSON = SCRIPTS_DIR / "synced_audios.json"
LOG_FILE = SCRIPTS_DIR / "sync.log"
PORT = 8765
TOTAL_EXPECTED = 551

HTML = """<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>متابعة رفع الصوتيات — Archive.org</title>
  <style>
    :root { --bg:#0b1220; --card:#131d31; --border:#23314d; --text:#f1f5f9; --muted:#94a3b8; --green:#22c55e; --teal:#14b8a6; --amber:#f59e0b; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, "Segoe UI", Tahoma, sans-serif; background:var(--bg); color:var(--text); padding:24px; }
    .wrap { max-width:1080px; margin:0 auto; display:grid; gap:18px; }
    .top { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    h1 { margin:0; font-size:22px; display:flex; align-items:center; gap:10px; }
    .dot { width:10px; height:10px; border-radius:50%; background:var(--green); box-shadow:0 0 12px var(--green); animation:pulse 1.6s infinite; }
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.85)} }
    .badge { font-size:13px; color:var(--muted); background:var(--card); border:1px solid var(--border); padding:6px 12px; border-radius:999px; }
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; }
    .lbl { font-size:13px; color:var(--muted); margin-bottom:6px; }
    .val { font-size:28px; font-weight:700; font-variant-numeric:tabular-nums; }
    .bar-wrap { background:#0f172a; border:1px solid var(--border); border-radius:999px; height:18px; overflow:hidden; margin-top:12px; }
    .bar { height:100%; background:linear-gradient(90deg, var(--teal), var(--green)); transition:width .4s ease; width:0%; }
    .years { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .chip { background:#1e293b; border:1px solid var(--border); padding:4px 10px; border-radius:999px; font-size:13px; color:#cbd5e1; }
    .log { background:#060a14; border:1px solid var(--border); border-radius:12px; padding:14px; font-family:Consolas,monospace; font-size:12.5px; line-height:1.7; max-height:230px; overflow:auto; direction:rtl; white-space:pre-wrap; color:#cbd5e1; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th, td { text-align:right; padding:10px 12px; border-bottom:1px solid var(--border); }
    th { color:var(--muted); font-weight:600; font-size:13px; }
    a { color:#38bdf8; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .muted { color:var(--muted); font-size:12.5px; }
    .search { width:100%; padding:10px 14px; border-radius:10px; border:1px solid var(--border); background:#0f172a; color:var(--text); font-size:14px; margin-bottom:12px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1><span class="dot" id="dot"></span> متابعة رفع صوتيات محمود الأزهري على Archive.org</h1>
      <span class="badge" id="updated">جاري التحديث...</span>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
        <div class="val"><span id="done">0</span> <span class="muted" style="font-size:18px">/ <span id="total">551</span> صوتية</span></div>
        <div style="font-size:22px;font-weight:700;color:var(--green)" id="pct">0%</div>
      </div>
      <div class="bar-wrap"><div class="bar" id="bar"></div></div>
      <div class="years" id="years"></div>
    </div>

    <div class="stats">
      <div class="card"><div class="lbl">المتبقي</div><div class="val" id="rem">—</div></div>
      <div class="card"><div class="lbl">أخطاء</div><div class="val" id="errs" style="color:var(--amber)">0</div></div>
      <div class="card"><div class="lbl">ملف الاستيراد</div><div class="val" style="font-size:15px;font-weight:500;margin-top:8px">scripts/synced_audios.json</div></div>
    </div>

    <div class="card">
      <div class="lbl">السجل المباشر (آخر النشاط)</div>
      <div class="log" id="log">جاري التحميل...</div>
    </div>

    <div class="card">
      <div class="lbl" style="margin-bottom:10px">أحدث الصوتيات المرفوعة</div>
      <input class="search" id="q" placeholder="ابحث في الصوتيات المرفوعة..." />
      <div style="overflow:auto">
        <table>
          <thead><tr><th>#</th><th>العنوان</th><th>المدة</th><th>التاريخ</th><th>الرابط المباشر</th></tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
    </div>
  </div>

<script>
let allItems = [];
function fmtDur(s){
  s = Number(s||0);
  const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
  const pad = n => String(n).padStart(2,'0');
  return h ? `${h}:${pad(m)}:${pad(sec)}` : `${m}:${pad(sec)}`;
}
function renderTable(){
  const q = document.getElementById('q').value.trim().toLowerCase();
  const list = (q ? allItems.filter(x => (x.title||'').toLowerCase().includes(q)) : allItems).slice(0, 60);
  document.getElementById('rows').innerHTML = list.map((x,i) => `
    <tr>
      <td class="muted">${i+1}</td>
      <td>${(x.title||'').replace(/</g,'&lt;')}</td>
      <td class="muted">${fmtDur(x.duration)}</td>
      <td class="muted">${(x.published_at||'').slice(0,10)}</td>
      <td><a href="${x.external_url}" target="_blank">تشغيل / تحميل ↗</a></td>
    </tr>
  `).join('') || '<tr><td colspan="5" class="muted">لا توجد نتائج بعد</td></tr>';
}
document.getElementById('q').addEventListener('input', renderTable);

async function refresh(){
  try {
    const res = await fetch('/api/status?_=' + Date.now());
    const d = await res.json();
    document.getElementById('done').textContent = d.uploaded;
    document.getElementById('total').textContent = d.total;
    document.getElementById('rem').textContent = Math.max(0, d.total - d.uploaded);
    document.getElementById('errs').textContent = d.errors;
    const pct = d.total ? ((d.uploaded / d.total) * 100).toFixed(1) : '0.0';
    document.getElementById('pct').textContent = pct + '%';
    document.getElementById('bar').style.width = Math.min(100, pct) + '%';
    document.getElementById('years').innerHTML = Object.entries(d.by_year)
      .sort((a,b) => b[0].localeCompare(a[0]))
      .map(([y,c]) => `<span class="chip">${y}: <b>${c}</b></span>`).join('');
    const logEl = document.getElementById('log');
    logEl.textContent = d.log_tail || 'لا يوجد سجل بعد';
    logEl.scrollTop = logEl.scrollHeight;
    allItems = d.items;
    renderTable();
    document.getElementById('updated').textContent = 'آخر تحديث: ' + new Date().toLocaleTimeString('ar-EG');
  } catch (e) {
    document.getElementById('updated').textContent = 'انقطع الاتصال — جاري إعادة المحاولة...';
  }
}
refresh();
setInterval(refresh, 3000);
</script>
</body>
</html>"""


def get_status() -> dict:
    items = []
    if SYNCED_JSON.exists():
        try:
            raw = json.loads(SYNCED_JSON.read_text(encoding="utf-8"))
            items = sorted(raw.values(), key=lambda x: x.get("published_at", ""), reverse=True)
        except Exception:
            items = []

    by_year = Counter((x.get("published_at") or "")[:4] for x in items if x.get("published_at"))
    log_tail = ""
    errors = 0
    total = TOTAL_EXPECTED

    if LOG_FILE.exists():
        try:
            lines = LOG_FILE.read_text(encoding="utf-8", errors="replace").splitlines()
            log_tail = "\n".join(lines[-25:])
            errors = sum(1 for l in lines if "❌" in l)
            for l in lines[:10]:
                m = re.search(r"متبقي للرفع:\s*(\d+)\s*\|\s*متخطى:\s*(\d+)", l)
                if m:
                    total = int(m.group(1)) + int(m.group(2))
                    break
        except Exception:
            pass

    return {
        "uploaded": len(items),
        "total": max(total, len(items)),
        "errors": errors,
        "by_year": dict(by_year),
        "log_tail": log_tail,
        "items": items,
    }


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path.startswith("/api/status"):
            body = json.dumps(get_status(), ensure_ascii=False).encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Cache-Control", "no-store")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
        else:
            body = HTML.encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Cache-Control", "no-store")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

    def log_message(self, format, *args):
        pass


if __name__ == "__main__":
    print(f"Dashboard running on http://127.0.0.1:{PORT}")
    HTTPServer(("127.0.0.1", PORT), Handler).serve_forever()
