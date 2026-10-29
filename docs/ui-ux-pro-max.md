# مهارة UI/UX Pro Max

> **حدود الاستخدام في هذا المشروع (قرار ق-31، القاعدة 7 في [ENGINEERING_GUIDELINES.md](ENGINEERING_GUIDELINES.md)):** المهارة أداة **فحص جودة** للواجهة: إمكانية الوصول، واللمس، والتجاوب، والنماذج، والتنقل، والأداء، وقائمة الفحص قبل التسليم. **لا تُستخدم لاختيار الألوان أو الخطوط أو الأسلوب، ولا لتوليد نظام تصميم أو حفظه** (`--design-system` و`--persist`). شكل الموقع تحدده حزمة المالك `ATHER-Agent-Handoff` وحدها.


تم تثبيت المهارة محليًا داخل هذا المشروع لاستخدامها في تصميم الواجهات وتحسين تجربة المستخدم مع Codex.

## تنظيم الملفات

```text
portfolio-project/
├── AGENTS.md                         # تعليمات استخدام المهارة في مهام الواجهات
├── .agents/
│   ├── .gitignore                    # استثناء ملفات Python المؤقتة
│   └── skills/ui-ux-pro-max/
│       ├── SKILL.md                  # تعليمات المهارة بمسارات مناسبة للمشروع
│       ├── data/                     # بيانات الأنماط والألوان والخطوط وإرشادات UX
│       ├── scripts/                  # أدوات البحث وتوليد توصيات نظام التصميم
│       ├── references/               # مراجع التصميم وإمكانية الوصول
│       ├── LICENSE                   # الترخيص الأصلي MIT
│       └── UPSTREAM.json             # المصدر وإصدار الشيفرة والتعديلات المحلية
└── docs/ui-ux-pro-max.md              # هذا الدليل
```

المصدر: [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill).
نسخة المصدر المثبتة: `28b3b8dee8c976df4fa3e94aed4e092ad43fcb03` بتاريخ 2026-10-29.
استُخدم مجلد `.agents/skills/` طبقًا لإعداد Codex في المستودع الأصلي.

## الاستخدام مع Codex

ستكون المهارة متاحة في الدور القادم. مثال طلب:

> استخدم مهارة ui-ux-pro-max لتحسين واجهة البورتفوليو، مع مراعاة العربية وRTL والتجاوب وإمكانية الوصول، والحفاظ على بنية PHP وCSS وJavaScript الحالية.

المهارة توفر إرشادات وتوصيات يستخدمها المساعد عند تنفيذ طلبك. تشغيل البحث وحده لا يغيّر واجهات الموقع.

## التشغيل اليدوي

نفّذ الأوامر التالية من جذر المشروع في PowerShell. المطلوب Python 3 فقط، دون مكتبات Python إضافية أو تثبيت npm.
يفضل استخدام كلمات بحث إنجليزية قصيرة لأن قاعدة البيانات باللغة الإنجليزية.

البحث عن إرشادات الوصول بلوحة المفاتيح:

```powershell
python .agents/skills/ui-ux-pro-max/scripts/search.py "keyboard focus modal" --domain ux
```

توليد توصيات لتصميم البورتفوليو وعرضها دون حفظها:

```powershell
python .agents/skills/ui-ux-pro-max/scripts/search.py "developer portfolio minimal" --design-system -p "Portfolio Project" -f markdown
```

بعد مراجعة ملاءمة التوصيات، يمكن حفظها للرجوع إليها في المهام القادمة:

```powershell
python .agents/skills/ui-ux-pro-max/scripts/search.py "developer portfolio minimal" --design-system -p "Portfolio Project" --persist --output-dir .
```

هذا ينشئ `design-system/portfolio-project/MASTER.md`. ويمكن إضافة `--page owner-dashboard` لإنشاء ملف خاص بتلك الصفحة داخل `pages/`.
اقرأ الملفات الموجودة قبل إعادة التوليد؛ لا تستخدم `--force` دون تفويض صريح باستبدال القرارات المحفوظة.
لم يُنشأ نظام تصميم محفوظ أثناء التثبيت؛ اختيار الشكل البصري يأتي مع طلب تطوير الواجهة.

المشروع يستخدم PHP وCSS وJavaScript. وجود أمثلة Tailwind أو Laravel في المهارة لا يعني إضافتها إلى المشروع.

## التحديث

راجع `UPSTREAM.json` أولًا، واحتفظ بأي تخصيصات محلية. افحص الإصدار الجديد في مجلد مؤقت قبل استبدال النسخة الحالية.
للمثبت الرسمي للمستودع، اسم حزمة npm الحالي هو `ui-ux-pro-max-cli` والأمر الخاص بـCodex هو:

```powershell
npx ui-ux-pro-max-cli init --ai codex
```

هذا الأمر مسار تثبيت بديل؛ لا يلزم تشغيله بعد هذا الإعداد. راجع تعليمات المصدر عند التحديث، ثم أعد التحقق من البحث وتوليد توصيات التصميم وحدّث بيانات المصدر.

المهارة أداة تطوير محلية، ويستثنيها `.dockerignore` من سياق بناء Docker.
