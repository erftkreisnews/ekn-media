import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Drop-Zone für Bild-Upload: Drag & Drop + Klick, Duplikate (gleicher Dateiname) werden ausgefiltert.
 * @param {string[]} existingNames - Bereits vorhandene Dateinamen (z. B. bereits hochgeladene Bilder dieser Meldung)
 */
Alpine.data('imageUploadDropzone', (existingNames = [], existingCount = null) => ({
    existingNames: Array.isArray(existingNames) ? existingNames : [],
    // Wird serverseitig (im Blade) mitgegeben, damit die Anzeige korrekt bleibt,
    // auch wenn bei vorhandenen Medien z. B. `original_name` leer ist.
    existingCount: typeof existingCount === 'number'
        ? existingCount
        : (Array.isArray(existingNames) ? existingNames.length : 0),
    pendingFiles: [],
    dragOver: false,
    _skipped: 0,

    get pendingCount() {
        return this.pendingFiles.length;
    },
    get skippedCount() {
        return this._skipped || 0;
    },

    addFiles(fileList) {
        if (!fileList || fileList.length === 0) return;
        const existingSet = new Set(this.existingNames.map((n) => n.toLowerCase()));
        const pendingNames = new Set(this.pendingFiles.map((f) => f.name.toLowerCase()));
        let skipped = 0;
        const toAdd = [];
        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];
            const nameLower = file.name.toLowerCase();
            if (existingSet.has(nameLower) || pendingNames.has(nameLower)) {
                skipped++;
                continue;
            }
            toAdd.push(file);
            pendingNames.add(nameLower);
        }
        this._skipped = skipped;
        this.pendingFiles = [...this.pendingFiles, ...toAdd];
        this.syncFileInput();
    },

    addFilesFromDrop(files) {
        this.addFiles(files);
    },

    syncFileInput() {
        const input = this.$refs.fileInput;
        if (!input) return;
        const dt = new DataTransfer();
        this.pendingFiles.forEach((f) => dt.items.add(f));
        input.files = dt.files;
    },
}));

/**
 * Drop-Zone für Video-Upload: Drag & Drop + Klick, Duplikate (gleicher Dateiname) werden ausgefiltert.
 * @param {string[]} existingNames - Bereits vorhandene Dateinamen (bereits hochgeladene Videos dieser Meldung)
 */
Alpine.data('videoUploadDropzone', (existingNames = []) => ({
    existingNames: Array.isArray(existingNames) ? existingNames : [],
    pendingFiles: [],
    dragOver: false,
    _skipped: 0,

    get pendingCount() {
        return this.pendingFiles.length;
    },
    get skippedCount() {
        return this._skipped || 0;
    },

    addFiles(fileList) {
        if (!fileList || fileList.length === 0) return;
        const existingSet = new Set(this.existingNames.map((n) => n.toLowerCase()));
        const pendingNames = new Set(this.pendingFiles.map((f) => f.name.toLowerCase()));
        let skipped = 0;
        const toAdd = [];
        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];
            const nameLower = file.name.toLowerCase();
            if (existingSet.has(nameLower) || pendingNames.has(nameLower)) {
                skipped++;
                continue;
            }
            toAdd.push(file);
            pendingNames.add(nameLower);
        }
        this._skipped = skipped;
        this.pendingFiles = [...this.pendingFiles, ...toAdd];
        this.syncFileInput();
    },

    addFilesFromDrop(files) {
        this.addFiles(files);
    },

    syncFileInput() {
        const input = this.$refs.fileInput;
        if (!input) return;
        const dt = new DataTransfer();
        this.pendingFiles.forEach((f) => dt.items.add(f));
        input.files = dt.files;
    },
}));

document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
});

// Admin-UX: Status-Icons (Nicht sichtbar / Versand / Teaser) bei Klick sofort umschalten.
// Ziel: Grüner Kreis + Haken <-> Weißes Kästchen + X (ohne Reload).
function syncStatusIcons(input) {
    if (!input || !(input instanceof HTMLInputElement)) return;
    if (!input.matches('input[data-status-input="1"]')) return;

    const label = input.closest('label[data-status-kind]');
    if (!label) return;

    const kind = label.dataset.statusKind;
    const isOn = input.checked === true;

    // Für Teaser (Radio-Gruppe) müssen auch die anderen Labels aktualisiert werden.
    if (kind === 'teaser') {
        const all = document.querySelectorAll('label[data-status-kind="teaser"]');
        all.forEach((lab) => {
            const inpt = lab.querySelector('input[type="radio"][data-status-input="1"]');
            const on = inpt ? inpt.checked === true : false;
            const onIcon = lab.querySelector('svg[data-status-icon="on"]');
            const offIcon = lab.querySelector('svg[data-status-icon="off"]');
            if (onIcon && offIcon) {
                        // Tailwind-Klassen setzen die Start-States oft auf `hidden`.
                        // Daher beim Umschalten explizit inline auf `block/none` setzen.
                        onIcon.style.display = on ? 'block' : 'none';
                        offIcon.style.display = on ? 'none' : 'block';
            }
        });
        return;
    }

    const onIcon = label.querySelector('svg[data-status-icon="on"]');
    const offIcon = label.querySelector('svg[data-status-icon="off"]');
    if (onIcon && offIcon) {
                // Tailwind-Klassen setzen die Start-States oft auf `hidden`.
                // Daher beim Umschalten explizit inline auf `block/none` setzen.
                onIcon.style.display = isOn ? 'block' : 'none';
                offIcon.style.display = isOn ? 'none' : 'block';
    }
}

document.addEventListener('click', (e) => {
    const t = e.target;
    if (!t || !(t instanceof Element)) return;

    // Oft liegt über dem unsichtbaren Input noch ein SVG (z-index/DOM-Reihenfolge),
    // dann ist e.target nicht das Input-Element. Wir holen uns das zugehörige Label
    // und synchronisieren danach im nächsten Frame.
    const label = t.closest('label[data-status-kind]');
    if (!label) return;

    const input = label.querySelector('input[data-status-input="1"]');
    if (!input || !(input instanceof HTMLInputElement)) return;

    requestAnimationFrame(() => syncStatusIcons(input));
});

document.addEventListener('change', (e) => {
    const t = e.target;
    if (!t || !(t instanceof HTMLInputElement)) return;
    syncStatusIcons(t);
});
