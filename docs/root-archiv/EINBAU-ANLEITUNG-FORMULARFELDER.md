# Einbau-Anleitung: Formularfelder aus „lave hetzner neu“

Konkrete Schritte, um Felder aus `lave hetzner neu/resources/views/news/_form.blade.php` in eure **Admin-News-Views** (create.blade.php & edit.blade.php) zu übernehmen. Alle Code-Blöcke nutzen **Tailwind** wie in eurem Projekt; **nichts wird überschrieben**, nur eingefügt.

---

## Übersicht: Feld-Mapping

| Feld aus „lave hetzner neu“ | Bei euch vorhanden? | Aktion |
|-----------------------------|---------------------|--------|
| **Titel** | ✅ `title` | Bereits vorhanden |
| **Kicker** | ✅ als **Dachzeile** (`teaser`) | Bereits vorhanden; optional Label in „Kicker“ umbenennen |
| **Unterzeile** | ❌ | **Neu einbauen** (subheadline) |
| **Veröffentlicht am** | ✅ `published_at` | Bereits in Publikation |
| **Embargo bis** | ❌ | **Neu einbauen** (embargo_at) |
| **Eilig / Top / Live** | ❌ (ihr habt Breaking, Video-Upload, LiveU) | **Optional ergänzen** (is_urgent, is_top, is_live) |
| **Angebotstext** | ❌ | **Neu einbauen** (offer_text) |
| **Fließtext HTML** | Teilweise als **Web-Text** (`body`) | Ihr habt ein Feld; optional zweites Feld „Fließtext (Plain)“ als body_text |
| **Fließtext Plain** | ❌ | **Optional** (body_text) |
| **Autor** | ✅ Relation `author_id` | Ihr nutzt User-Relation; optional **Autor-Credit** als neues Feld |
| **Autor-Credit** | ❌ | **Neu einbauen** (author_credit) |
| **Ortsangabe** | Ihr habt Land/Bundesland/Stadt/Straße | Optional **location_label** als Kurztext ergänzen (oder weglassen) |
| **Taxonomien** | ❌ | Erst nach Einführung eines Taxonomy-Modells einbauen |

---

## Voraussetzung: Migration & Model

Bevor ihr die neuen Felder in den Views nutzt, müssen sie in der Datenbank und im Model existieren.

### 1. Migration anlegen (nur wenn ihr die neuen Felder wollt)

```bash
php artisan make:migration add_news_fields_from_lave_hetzner_to_news_items_table --table=news_items
```

In der Migration (z.B. `database/migrations/xxxx_add_news_fields_...`):

```php
public function up(): void
{
    Schema::table('news_items', function (Blueprint $table) {
        $table->string('subheadline', 512)->nullable()->after('teaser');
        $table->dateTime('embargo_at')->nullable()->after('published_at');
        $table->boolean('is_urgent')->default(false)->after('liveu_on_site');
        $table->boolean('is_top')->default(false)->after('is_urgent');
        $table->boolean('is_live')->default(false)->after('is_top');
        $table->text('offer_text')->nullable()->after('body');
        $table->text('body_text')->nullable()->after('offer_text'); // optional
        $table->string('author_credit', 255)->nullable()->after('author_id');
        $table->string('location_label', 255)->nullable()->after('street');
    });
}

public function down(): void
{
    Schema::table('news_items', function (Blueprint $table) {
        $table->dropColumn([
            'subheadline', 'embargo_at', 'is_urgent', 'is_top', 'is_live',
            'offer_text', 'body_text', 'author_credit', 'location_label'
        ]);
    });
}
```

### 2. Model `NewsItem` erweitern

In `app/Models/NewsItem.php`:

- **fillable** ergänzen um: `'subheadline', 'embargo_at', 'is_urgent', 'is_top', 'is_live', 'offer_text', 'body_text', 'author_credit', 'location_label'` (nur die, die ihr wirklich nutzt).
- **casts** ergänzen, z.B.:
  - `'embargo_at' => 'datetime'`
  - `'is_urgent' => 'bool'`, `'is_top' => 'bool'`, `'is_live' => 'bool'`

---

## Einbau in die Views

Alle Einfügungen sind **nach** dem genannten Block, damit die bestehende Struktur erhalten bleibt.

---

### A) Unterzeile (subheadline) – nach „Dachzeile“

**Datei:** `resources/views/admin/news/create.blade.php`  
**Einfügen nach:** dem kompletten Block mit `name="teaser"` (Ende des `<div class="space-y-2">` mit `@error('teaser')`), also **nach Zeile ~151**.

**Datei:** `resources/views/admin/news/edit.blade.php`  
**Einfügen nach:** dem gleichen Teaser-Block, **nach Zeile ~155**.

**Code (Tailwind, für create):**

```blade
<div class="space-y-2">
    <label for="subheadline" class="block text-sm font-medium text-gray-700">
        Unterzeile <span class="text-xs text-gray-400">(max. 512 Zeichen)</span>
    </label>
    <input
        type="text"
        name="subheadline"
        id="subheadline"
        value="{{ old('subheadline') }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
        maxlength="512"
    />
    @error('subheadline')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

**Für edit:** `value="{{ old('subheadline', $newsItem->subheadline) }}"` verwenden.

---

### B) Embargo bis (embargo_at) – in „Publikation“

**Datei:** create.blade.php und edit.blade.php  
**Einfügen nach:** dem Block mit `name="published_at"` (nach `@error('published_at')`), also im Publikation-Grid **nach dem Veröffentlichungsdatum**.

**Code (create):**

```blade
<div class="space-y-2">
    <label for="embargo_at" class="block text-sm font-medium text-gray-700">
        Embargo bis
    </label>
    <input
        type="datetime-local"
        name="embargo_at"
        id="embargo_at"
        value="{{ old('embargo_at') }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
    />
    @error('embargo_at')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

**Für edit:** `value="{{ old('embargo_at', optional($newsItem->embargo_at)->format('Y-m-d\TH:i')) }}"`

---

### C) Flags Eilig / Top / Live – in „Besondere Merkmale“

**Datei:** create und edit  
**Einfügen nach:** dem dritten Checkbox-Label („LiveU vor Ort“), **innerhalb** des gleichen `<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">` – also entweder die Grid-Spalten erweitern (z.B. `sm:grid-cols-6`) oder eine zweite Zeile mit drei weiteren Checkboxen einfügen.

**Code (eine zweite Zeile, gleiche Optik wie eure Merkmale):**

```blade
<label class="inline-flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
    <input type="checkbox" name="is_urgent" value="1" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]" @checked(old('is_urgent'))>
    <span class="text-xs font-medium text-gray-700">Eilig</span>
</label>
<label class="inline-flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
    <input type="checkbox" name="is_top" value="1" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]" @checked(old('is_top'))>
    <span class="text-xs font-medium text-gray-700">Top</span>
</label>
<label class="inline-flex items-center space-x-2 cursor-pointer bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg border border-gray-200">
    <input type="checkbox" name="is_live" value="1" class="h-4 w-4 rounded border-gray-300 text-[#092E48] focus:ring-[#092E48]" @checked(old('is_live'))>
    <span class="text-xs font-medium text-gray-700">Live</span>
</label>
```

**Edit:** `@checked(old('is_urgent', $newsItem->is_urgent))` usw.

---

### D) Angebotstext (offer_text) – nach „Web-Text“

**Datei:** create und edit  
**Einfügen nach:** dem Block mit `name="body"` (Web-Text) und `@error('body')`, **vor** „Besondere Merkmale“.

**Code (create):**

```blade
<div class="space-y-2">
    <label for="offer_text" class="block text-sm font-medium text-gray-700">
        Angebotstext <span class="text-xs text-gray-400">(Kurzer Vorspann / Teaser)</span>
    </label>
    <textarea
        name="offer_text"
        id="offer_text"
        rows="4"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
        placeholder="Kurzer Vorspann / Teaser"
    >{{ old('offer_text') }}</textarea>
    @error('offer_text')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

**Edit:** `>{{ old('offer_text', $newsItem->offer_text) }}</textarea>`

---

### E) Fließtext Plain (body_text) – optional

**Einfügen nach:** dem Web-Text (`body`), wenn ihr zusätzlich eine Nur-Text-Version wollt. Gleicher Stil wie „Angebotstext“, nur `name="body_text"`, `id="body_text"`, Platzhalter z.B. „Nur-Text-Version“.

---

### F) Autor-Credit (author_credit) – in „Publikation“ oder neue Sektion

Ihr habt bereits `author_id` (User). **Autor-Credit** ist ein optionaler Zusatztext (z.B. „Redaktion Köln“).  

**Einfügen:** z.B. im Publikation-Grid als weiteres Feld nach „Veröffentlichungsdatum“ oder „Embargo“.

**Code (create):**

```blade
<div class="space-y-2 md:col-span-2">
    <label for="author_credit" class="block text-sm font-medium text-gray-700">
        Autor-Credit
    </label>
    <input
        type="text"
        name="author_credit"
        id="author_credit"
        value="{{ old('author_credit') }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
        maxlength="255"
    />
    @error('author_credit')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

**Edit:** `value="{{ old('author_credit', $newsItem->author_credit) }}"`

---

### G) Ortsangabe (location_label) – optional

Falls ihr eine **einzeilige** Ortsangabe zusätzlich zu Land/Stadt/Straße wollt: im Publikation-Grid ein weiteres Textfeld `location_label` einfügen (gleicher Stil wie die anderen Felder). Sonst weglassen, ihr habt bereits die detaillierte Ortszeile.

---

## Controller

In `app/Http/Controllers/Admin/NewsController.php` (oder wo ihr die News speichert):

- **store:** alle neuen Feldnamen in `$request->validate([...])` und in `NewsItem::create([...])` aufnehmen.
- **update:** dieselben Felder in `validate` und `$newsItem->update([...])`.

Beispiel Validierung (nur die neuen Felder):

```php
'subheadline' => ['nullable', 'string', 'max:512'],
'embargo_at' => ['nullable', 'date'],
'is_urgent' => ['nullable', 'boolean'],
'is_top' => ['nullable', 'boolean'],
'is_live' => ['nullable', 'boolean'],
'offer_text' => ['nullable', 'string'],
'body_text' => ['nullable', 'string'],
'author_credit' => ['nullable', 'string', 'max:255'],
'location_label' => ['nullable', 'string', 'max:255'],
```

---

## Reihenfolge empfohlen

1. Migration anlegen & ausführen (`php artisan migrate`).
2. Model `NewsItem`: fillable & casts anpassen.
3. Controller: Validierung und create/update um die neuen Felder erweitern.
4. Views: die gewünschten Blöcke nacheinander einfügen (create zuerst, dann edit mit `$newsItem->...`).

So übernehmt ihr die Inhalte aus „lave hetzner neu“ schrittweise, ohne bestehende Funktionalität zu überschreiben.
