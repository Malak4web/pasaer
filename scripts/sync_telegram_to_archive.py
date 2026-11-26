"""
نقل صوتيات قناة تليجرام تلقائيًا إلى Archive.org بالتوازي (Parallel) مع حفظ JSON و Excel للاستيراد في Laravel.
التشغيل:
    python -u scripts/sync_telegram_to_archive.py --workers 4        # كل الصوتيات بالتوازي (يستكمل تلقائيًا)
    python -u scripts/sync_telegram_to_archive.py --limit 10         # حد معين من الملفات
"""

import argparse
import asyncio
import json
import os
import re
import sys
import time
from pathlib import Path

from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from telethon import TelegramClient
from telethon.errors import FloodWaitError
from telethon.tl.types import (
    DocumentAttributeAudio,
    DocumentAttributeFilename,
    InputMessagesFilterMusic,
)
import internetarchive as ia

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SCRIPTS_DIR = Path(__file__).resolve().parent
ENV_FILE = SCRIPTS_DIR / ".env.sync"
SESSION_PATH = str(SCRIPTS_DIR / "tg_session")
CHECKPOINT_FILE = SCRIPTS_DIR / "synced_audios.json"
EXCEL_FILE = SCRIPTS_DIR / "synced_audios.xlsx"
LOG_OUT_FILE = SCRIPTS_DIR / "sync.log"
TMP_DIR = SCRIPTS_DIR / "tmp_audio"
TMP_DIR.mkdir(parents=True, exist_ok=True)


def log(msg: str) -> None:
    print(msg, flush=True)
    try:
        with LOG_OUT_FILE.open("a", encoding="utf-8") as f:
            f.write(msg + "\n")
    except OSError:
        pass


def load_env(path: Path) -> dict[str, str]:
    env: dict[str, str] = {}
    if not path.exists():
        return env
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def extract_audio_meta(msg) -> dict:
    doc = msg.document
    title = None
    performer = None
    filename = None
    duration = 0
    size_mb = round((getattr(doc, "size", 0) or 0) / (1024 * 1024), 1)

    for attr in doc.attributes or []:
        if isinstance(attr, DocumentAttributeAudio):
            duration = int(attr.duration or 0)
            if attr.title:
                title = attr.title.strip()
            if attr.performer:
                performer = attr.performer.strip()
        elif isinstance(attr, DocumentAttributeFilename):
            filename = attr.file_name.strip()

    ext = Path(filename).suffix.lower() if filename and Path(filename).suffix else ""
    if not ext:
        mime = (doc.mime_type or "").lower()
        ext_map = {
            "audio/mp4": ".m4a",
            "audio/x-m4a": ".m4a",
            "audio/m4a": ".m4a",
            "audio/mpeg": ".mp3",
            "audio/mp3": ".mp3",
            "audio/ogg": ".ogg",
            "audio/aac": ".aac",
            "audio/wav": ".wav",
        }
        ext = ext_map.get(mime, ".m4a")

    clean_name = Path(filename).stem if filename else ""
    caption = (msg.message or "").strip()
    display_title = title or clean_name or (caption.splitlines()[0][:200] if caption else f"تسجيل {msg.id}")
    display_title = re.sub(r"_+", " ", display_title).strip()

    return {
        "message_id": msg.id,
        "title": display_title,
        "original_filename": filename or f"msg_{msg.id}{ext}",
        "performer": performer,
        "description": caption,
        "duration": duration,
        "size_mb": size_mb,
        "published_at": msg.date.strftime("%Y-%m-%d %H:%M:%S"),
        "year": msg.date.year,
        "ext": ext,
    }


def save_json(synced: dict) -> None:
    CHECKPOINT_FILE.write_text(
        json.dumps(synced, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )


def save_excel(synced: dict) -> None:
    wb = Workbook()
    ws = wb.active
    ws.title = "صوتيات مرفوعة"
    ws.views.sheetView[0].rightToLeft = True
    headers = ["message_id", "العنوان", "المدة (ثانية)", "التاريخ", "رابط التشغيل المباشر", "الوصف"]
    ws.append(headers)
    fill = PatternFill(start_color="1B4332", end_color="1B4332", fill_type="solid")
    for c in range(1, len(headers) + 1):
        cell = ws.cell(row=1, column=c)
        cell.fill = fill
        cell.font = Font(bold=True, color="FFFFFF")
        cell.alignment = Alignment(horizontal="center")

    rows = sorted(synced.values(), key=lambda x: x["published_at"], reverse=True)
    for r in rows:
        ws.append([
            r["message_id"],
            r["title"],
            r["duration"],
            r["published_at"],
            r["external_url"],
            r.get("description", ""),
        ])

    ws.column_dimensions["A"].width = 12
    ws.column_dimensions["B"].width = 48
    ws.column_dimensions["C"].width = 14
    ws.column_dimensions["D"].width = 22
    ws.column_dimensions["E"].width = 65
    ws.column_dimensions["F"].width = 40
    wb.save(EXCEL_FILE)


def upload_to_archive(item_id: str, remote_name: str, local_path: str, channel_title: str, year: int, access_key: str, secret_key: str):
    ia.upload(
        item_id,
        files={remote_name: local_path},
        metadata={
            "title": f"{channel_title} - {year}",
            "mediatype": "audio",
            "creator": channel_title,
            "language": "ara",
        },
        access_key=access_key,
        secret_key=secret_key,
        retries=5,
        retries_sleep=10,
        queue_derive=False,
        verbose=False,
    )


async def main() -> None:
    parser = argparse.ArgumentParser(description="Parallel Telegram-to-Archive.org audio sync")
    parser.add_argument("--limit", type=int, default=0, help="أقصى عدد ملفات للرفع (0 = الكل)")
    parser.add_argument("--workers", type=int, default=4, help="عدد الملفات المتزامنة (الافتراضي 4)")
    args = parser.parse_args()

    cfg = load_env(ENV_FILE)
    api_id = int(cfg.get("TG_API_ID") or os.getenv("TG_API_ID") or 0)
    api_hash = (cfg.get("TG_API_HASH") or os.getenv("TG_API_HASH") or "").strip()
    ia_access = (cfg.get("IA_ACCESS_KEY") or os.getenv("IA_ACCESS_KEY") or "").strip()
    ia_secret = (cfg.get("IA_SECRET_KEY") or os.getenv("IA_SECRET_KEY") or "").strip()
    channel = (cfg.get("CHANNEL") or "-1001171346631").strip()
    ia_prefix = (cfg.get("IA_ITEM_PREFIX") or "mahmoud-azhary-audios").strip().lower()

    client = TelegramClient(
        SESSION_PATH,
        api_id,
        api_hash,
        connection_retries=None,
        retry_delay=5,
        auto_reconnect=True,
    )
    await client.start()

    me = await client.get_me()
    LOG_OUT_FILE.write_text("", encoding="utf-8")
    log(f"✅ مسجّل دخول باسم: {me.first_name} (@{me.username}) | Workers: {args.workers}")

    synced = json.loads(CHECKPOINT_FILE.read_text(encoding="utf-8")) if CHECKPOINT_FILE.exists() else {}
    log(f"📦 مرفوع سابقًا: {len(synced)}")

    await client.get_dialogs()
    channel_ref = int(channel) if re.fullmatch(r"-?\d+", channel) else channel
    entity = await client.get_entity(channel_ref)
    channel_title = getattr(entity, "title", str(channel_ref))

    # جمع الرسائل المتبقية أولاً
    pending_msgs = []
    skipped = 0
    async for msg in client.iter_messages(entity, filter=InputMessagesFilterMusic):
        if not msg.document:
            continue
        if str(msg.id) in synced:
            skipped += 1
            continue
        pending_msgs.append(msg)
        if args.limit > 0 and len(pending_msgs) >= args.limit:
            break

    total_pending = len(pending_msgs)
    log(f"📡 القناة: {channel_title} | متبقي للرفع: {total_pending} | متخطى: {skipped}")

    if total_pending == 0:
        save_excel(synced)
        await client.disconnect()
        log("🎉 كل الصوتيات مرفوعة بالفعل!")
        return

    dl_sem = asyncio.Semaphore(args.workers)
    ul_sem = asyncio.Semaphore(min(2, args.workers))
    save_lock = asyncio.Lock()
    reconnect_lock = asyncio.Lock()
    stats = {"done": 0, "errors": 0}
    t0 = time.time()

    async def ensure_connected():
        if not client.is_connected():
            async with reconnect_lock:
                if not client.is_connected():
                    log("🔄 انقطع الاتصال بتليجرام — جاري إعادة الاتصال...")
                    await client.connect()
                    log("✅ تمت إعادة الاتصال بتليجرام")

    async def process_one(idx: int, msg):
        meta = extract_audio_meta(msg)
        remote_name = f"msg_{msg.id}{meta['ext']}"
        item_id = f"{ia_prefix}-{meta['year']}"
        local_file = TMP_DIR / remote_name

        try:
            async with dl_sem:
                log(f"⬇️ [{idx}/{total_pending}] تحميل ({meta['size_mb']} MB): {meta['title']}")
                for attempt in range(1, 6):
                    try:
                        await ensure_connected()
                        await client.download_media(msg, file=str(local_file))
                        break
                    except FloodWaitError as e:
                        log(f"⏳ FloodWait {e.seconds + 2}s...")
                        await asyncio.sleep(e.seconds + 2)
                    except (ConnectionError, OSError, asyncio.TimeoutError) as e:
                        if attempt == 5:
                            raise
                        log(f"⚠️ انقطع التحميل لـ msg_{msg.id} (محاولة {attempt}/5): {e}")
                        await asyncio.sleep(5 * attempt)

            async with ul_sem:
                log(f"⬆️ [{idx}/{total_pending}] رفع -> {item_id}/{remote_name}")
                for u_attempt in range(1, 5):
                    try:
                        await asyncio.to_thread(
                            upload_to_archive,
                            item_id,
                            remote_name,
                            str(local_file),
                            channel_title,
                            meta["year"],
                            ia_access,
                            ia_secret,
                        )
                        break
                    except Exception as ue:
                        if u_attempt == 4:
                            raise
                        wait_s = 15 * u_attempt
                        log(f"⚠️ إعادة محاولة رفع msg_{msg.id} ({u_attempt}/4) بعد {wait_s}s: {ue}")
                        await asyncio.sleep(wait_s)

            direct_url = f"https://archive.org/download/{item_id}/{remote_name}"
            async with save_lock:
                synced[str(msg.id)] = {
                    "message_id": meta["message_id"],
                    "title": meta["title"],
                    "original_filename": meta["original_filename"],
                    "performer": meta["performer"],
                    "description": meta["description"],
                    "duration": meta["duration"],
                    "published_at": meta["published_at"],
                    "archive_item": item_id,
                    "external_url": direct_url,
                }
                save_json(synced)
                stats["done"] += 1
                if stats["done"] % 20 == 0:
                    await asyncio.to_thread(save_excel, synced)
                elapsed = int(time.time() - t0)
                log(f"✅ [{stats['done']}/{total_pending}] إجمالي: {len(synced)} ({elapsed}s) -> {direct_url}")

        except Exception as ex:
            async with save_lock:
                stats["errors"] += 1
            log(f"❌ خطأ في msg_{msg.id} ({meta['title']}): {ex}")
        finally:
            if local_file.exists():
                try:
                    local_file.unlink()
                except OSError:
                    pass

    queue: asyncio.Queue = asyncio.Queue()
    for i, m in enumerate(pending_msgs, start=1):
        queue.put_nowait((i, m))

    async def worker():
        while not queue.empty():
            try:
                i, m = queue.get_nowait()
            except asyncio.QueueEmpty:
                break
            await process_one(i, m)
            queue.task_done()

    workers = [asyncio.create_task(worker()) for _ in range(args.workers)]
    await asyncio.gather(*workers)

    save_json(synced)
    save_excel(synced)
    await client.disconnect()
    print(f"\n🎉 انتهت الجلسة! مرفوع الآن: {stats['done']} | أخطاء: {stats['errors']} | الإجمالي المحفوظ: {len(synced)}")
    print(f"📄 JSON: {CHECKPOINT_FILE}")
    print(f"📊 Excel: {EXCEL_FILE}")


if __name__ == "__main__":
    asyncio.run(main())
