# بصائر

موقع دعوي لعرض المرئيات والصوتيات والدروس والمنشورات.

- **الواجهة**: Vue 3 + Vite + Pinia + Vue Router (في جذر المشروع `src/`)
- **الباك اند ولوحة التحكم**: Laravel 13 + MySQL (في `backend/`) — مكتوب من الصفر بدون باكدجات جاهزة (لا Breeze ولا Filament ولا Spatie)

```
bsear/
├─ src/                 الواجهة (Vue)
├─ backend/             Laravel: لوحة التحكم + الـ API
│  ├─ app/Http/Controllers/Admin   شاشات لوحة التحكم
│  ├─ app/Http/Controllers/Api     الـ API العام للواجهة
│  ├─ app/Models                   Topic, Series, Video, Audio, Lesson, Post, Setting, User
│  ├─ app/Support/Arabic.php       تطبيع البحث العربي، المدة، استخراج معرّف يوتيوب
│  ├─ resources/views/admin        قوالب Blade للوحة التحكم
│  └─ public/assets/admin          CSS و JS للوحة التحكم (مكتوبين يدويًا)
```

## التشغيل محليًا

المتطلبات: PHP 8.3+، Composer، MySQL/MariaDB (XAMPP مناسب)، Node 20+.

### 1) قاعدة البيانات والباك اند

```bash
cd backend
composer install
cp .env.example .env          # لو مش موجود
php artisan key:generate
```

عدّل في `.env`:

```
DB_DATABASE=basaer
DB_USERNAME=root
DB_PASSWORD=
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=كلمة-مرور-قوية
```

أنشئ قاعدة البيانات `basaer` (utf8mb4) وبعدين:

```bash
php artisan migrate --seed     # الجداول + حساب المدير + محتوى تجريبي
php artisan storage:link       # عشان ملفات الصوت المرفوعة تتعرض
composer serve                 # السيرفر على http://127.0.0.1:8000 بحد رفع 256 ميجا
```

لوحة التحكم: http://127.0.0.1:8000/admin

لإنشاء مدير جديد أو إعادة تعيين كلمة المرور:

```bash
php artisan admin:create
```

### 2) الواجهة

```bash
npm install
npm run dev                    # http://localhost:5173
```

أثناء التطوير Vite بيحوّل طلبات `/api` و `/storage` لسيرفر لارافل تلقائيًا.
لو الباك اند على عنوان مختلف: `BACKEND_URL=http://... npm run dev`.

## لوحة التحكم

| الشاشة | اللي بتعمله |
|---|---|
| الرئيسية | إحصائيات، آخر التعديلات، المحتوى المجدول |
| المرئيات | الصق رابط يوتيوب بأي صيغة، والعنوان بيتجاب تلقائي لو سبته فاضي |
| الصوتيات | رفع ملف بالسحب والإفلات أو رابط خارجي، والمدة بتتحسب تلقائي |
| الدروس / المنشورات | نص بفقرات (سطر فاضي بين الفقرات)، وزمن القراءة بيتحسب تلقائي |
| السلاسل | تجميع مرئيات وصوتيات ودروس بترتيب، وإظهارها في الرئيسية |
| المواضيع | التصنيفات اللي في القائمة الجانبية، بأيقونة من tabler.io/icons |
| المستخدمون | مدير (كل حاجة) أو محرر (المحتوى فقط) — للمدير فقط |
| إعدادات الموقع | الاسم، الوصف، اسم الشيخ، رابط القناة، الآية، المقطع المميز |

- كل محتوى له حالة **مسودة / منشور**، ولو حددت تاريخ نشر في المستقبل بيتنشر تلقائي في ميعاده (مجدول).
- الضغط على حالة العنصر في الجدول بيبدّل بين النشر والمسودة.
- `Ctrl + S` بيحفظ أي نموذج، وفيه تنبيه لو قفلت الصفحة من غير حفظ.
- تسجيل الدخول محمي من التخمين (٥ محاولات في الدقيقة).

## الـ API (قراءة فقط)

| المسار | الوصف |
|---|---|
| `GET /api/site` | الإعدادات + المواضيع |
| `GET /api/home` | المقطع المميز + أحدث صوتية + السلاسل المختارة |
| `GET /api/feed?topic=&type=&page=` | كل المحتوى مختلط ومرتب بالتاريخ |
| `GET /api/search?q=` | بحث عربي (بيتجاهل التشكيل والهمزات) |
| `GET /api/videos`، `/api/videos/{id}` | المرئيات (+ `sort=popular`) |
| `GET /api/audios`، `/api/audios/{id}` | الصوتيات + قائمة التشغيل |
| `POST /api/audios/{id}/played` | تسجيل مرة تشغيل |
| `GET /api/lessons`، `/api/lessons/{id}` | الدروس + السابق/التالي في السلسلة |
| `GET /api/posts`، `/api/posts/{id}` | المنشورات |
| `GET /api/series`، `/api/series/{id}` | السلاسل وعناصرها |
| `GET /api/topics/{slug}` | محتوى موضوع معيّن |

## النشر على سيرفر

1. ارفع `backend/` واجعل الـ document root هو `backend/public`.
2. `APP_ENV=production`، `APP_DEBUG=false`، و `APP_URL` و `FRONTEND_URL` بالعناوين الحقيقية.
3. في `php.ini`: `upload_max_filesize=256M` و `post_max_size=260M` لملفات الصوت.
4. `composer install --no-dev -o && php artisan migrate --force && php artisan storage:link && php artisan optimize`
5. ابنِ الواجهة: `VITE_API_URL=https://api.example.com npm run build` وارفع `dist/` مع إعادة توجيه كل المسارات لـ `index.html`.
