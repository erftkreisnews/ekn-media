<section class="admin-image-editor-tool-section">
    <h3 class="admin-image-editor-tool-title">Seitenverhältnis</h3>
    <div class="admin-image-editor-aspect-grid">
        <template x-for="opt in editorAspectOptions" :key="opt.id">
            <button
                type="button"
                class="admin-image-editor-aspect-btn"
                :class="editorAspect === opt.id ? 'is-active' : ''"
                @click="setEditorAspect(opt.id)"
                x-text="opt.label"
            ></button>
        </template>
    </div>
    <p class="admin-image-editor-hint">Doppelklick auf ein Verhältnis dreht es um (z. B. 3:2 → 2:3).</p>
    <p class="admin-image-editor-hint">Rahmen verschieben oder an den Ecken/Seiten ziehen.</p>
</section>

<section class="admin-image-editor-tool-section">
    <h3 class="admin-image-editor-tool-title">Drehen</h3>
    <div class="admin-image-editor-btn-row">
        <button type="button" class="admin-image-editor-btn admin-image-editor-btn--ghost" @click="rotateEditor(-90)">−90°</button>
        <button type="button" class="admin-image-editor-btn admin-image-editor-btn--ghost" @click="rotateEditor(90)">+90°</button>
    </div>
</section>

<section class="admin-image-editor-tool-section">
    <h3 class="admin-image-editor-tool-title">Begradigen</h3>
    <input type="range" min="-15" max="15" step="0.1" x-model.number="editorStraighten" @input="renderEditorCanvas()" class="admin-image-editor-range">
    <p class="admin-image-editor-hint"><span x-text="editorStraighten.toFixed(1)"></span>°</p>
</section>

<section class="admin-image-editor-tool-section">
    <h3 class="admin-image-editor-tool-title">Filter</h3>
    <label class="admin-image-editor-range-label">Helligkeit <span x-text="editorBrightness + '%'"></span></label>
    <input type="range" min="50" max="150" x-model.number="editorBrightness" @input="renderEditorCanvas()" class="admin-image-editor-range">
    <label class="admin-image-editor-range-label">Kontrast <span x-text="editorContrast + '%'"></span></label>
    <input type="range" min="50" max="150" x-model.number="editorContrast" @input="renderEditorCanvas()" class="admin-image-editor-range">
    <label class="admin-image-editor-range-label">Sättigung <span x-text="editorSaturation + '%'"></span></label>
    <input type="range" min="0" max="200" x-model.number="editorSaturation" @input="renderEditorCanvas()" class="admin-image-editor-range">
    <label class="admin-image-editor-range-label">Farbton <span x-text="editorHue + '°'"></span></label>
    <input type="range" min="-180" max="180" x-model.number="editorHue" @input="renderEditorCanvas()" class="admin-image-editor-range">
    <label class="admin-image-editor-range-label">
        Nachschärfen <span x-text="editorSharpen"></span>
        <span class="text-white/35">/ <span x-text="editorSharpenSliderMax"></span></span>
    </label>
    <input
        type="range"
        min="0"
        :max="editorSharpenSliderMax"
        step="1"
        x-model.number="editorSharpen"
        @input="clampEditorSharpen(); renderEditorCanvas()"
        class="admin-image-editor-range"
    >
    <p class="admin-image-editor-hint">0 = aus. Empfohlen: 10–35 (Überzeichnung wird automatisch begrenzt).</p>
    <p
        x-show="editorSharpenWarning()"
        x-cloak
        class="admin-image-editor-hint text-amber-200/90"
    >Starker Wert – bei Körnung oder Halos bitte reduzieren.</p>
</section>

<section class="admin-image-editor-tool-section">
    <h3 class="admin-image-editor-tool-title">JPEG-Qualität</h3>
    <input type="range" min="60" max="100" x-model.number="editorQuality" class="admin-image-editor-range">
    <p class="admin-image-editor-hint"><span x-text="editorQuality"></span>% · Originaldatei im Speicher bleibt erhalten.</p>
</section>

<section class="admin-image-editor-tool-section admin-image-editor-tool-section--actions">
    <button
        type="button"
        class="admin-image-editor-btn admin-image-editor-btn--save w-full justify-center"
        :disabled="editorSaving"
        @click="saveEditorAsNew()"
    >
        <span x-text="editorSaving ? 'Speichern…' : 'Als Kopie speichern'"></span>
    </button>
    <p class="admin-image-editor-hint mt-2 text-center">Bearbeitung auf der Originaldatei, nicht die Web-Vorschau.</p>
</section>
