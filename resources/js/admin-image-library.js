import Alpine from 'alpinejs';

Alpine.data('adminImageLibrary', (config = {}) => ({
    imageIds: config.imageIds || [],
    detailUrlTemplate: config.detailUrlTemplate || '',
    updateUrlTemplate: config.updateUrlTemplate || '',

    selectAll: false,
    detailOpen: false,
    detailLoading: false,
    detail: null,
    detailIndex: 0,
    previewMode: 'original',
    editing: false,
    saving: false,
    statusSaving: false,
    saveMessage: '',
    saveError: false,
    form: {
        image_title: '',
        caption: '',
        city: '',
        country: '',
        media_keywords: '',
    },

    init() {
        this.$watch('detailOpen', (open) => {
            document.body.classList.toggle('overflow-hidden', open);
        });
    },

    editPageUrl() {
        return this.detail?.urls?.image_editor || this.detail?.urls?.edit || '#';
    },

    toggleSelectAll(event) {
        const checked = event?.target?.checked === true;
        this.selectAll = checked;
        this.$root.querySelectorAll('input[data-image-select]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.checked = checked;
            }
        });
    },

    onCardCheckboxChange() {
        const boxes = [...this.$root.querySelectorAll('input[data-image-select]')];
        const checked = boxes.filter((b) => b.checked).length;
        this.selectAll = boxes.length > 0 && checked === boxes.length;
        const master = this.$root.querySelector('input[data-image-select-all]');
        if (master instanceof HTMLInputElement) {
            master.checked = this.selectAll;
            master.indeterminate = checked > 0 && checked < boxes.length;
        }
    },

    openCardDetail(mediaId, event) {
        if (event?.target?.closest('input[data-image-select], a, button')) {
            const tag = event.target.tagName?.toLowerCase();
            if (tag === 'input' || tag === 'a' || tag === 'button') {
                return;
            }
        }
        this.openDetail(mediaId);
    },

    async openDetail(mediaId) {
        const idx = this.imageIds.indexOf(Number(mediaId));
        this.detailIndex = idx >= 0 ? idx : 0;
        this.detailOpen = true;
        this.editing = false;
        this.previewMode = 'original';
        this.saveMessage = '';
        await this.loadDetail(this.imageIds[this.detailIndex] ?? mediaId);
    },

    closeDetail() {
        this.detailOpen = false;
        this.detail = null;
        this.editing = false;
        this.saveMessage = '';
    },

    detailUrlFor(id) {
        return this.detailUrlTemplate.replace('__ID__', String(id));
    },

    updateUrlFor(id) {
        const template = this.updateUrlTemplate || this.detail?.urls?.update || '';
        if (!template) {
            return '';
        }
        return template.replace('__ID__', String(id));
    },

    csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta instanceof HTMLMetaElement ? meta.content : '';
    },

    applyDetail(detail) {
        this.detail = detail;
        this.form = {
            image_title: detail.image_title || '',
            caption: detail.caption || '',
            city: detail.city || '',
            country: detail.country || '',
            media_keywords: detail.media_keywords || '',
        };
        this.previewMode = 'original';
    },

    async loadDetail(mediaId) {
        this.detailLoading = true;
        try {
            const response = await fetch(this.detailUrlFor(mediaId), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('load failed');
            }
            this.applyDetail(await response.json());
        } catch {
            this.detail = { error: 'Bild-Details konnten nicht geladen werden.' };
        } finally {
            this.detailLoading = false;
        }
    },

    async patchDetail(body) {
        const id = this.detail?.id;
        const url = this.updateUrlFor(id);
        if (!id || !url) {
            throw new Error('no update url');
        }

        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error('patch failed');
        }

        const data = await response.json();
        if (data?.detail) {
            this.applyDetail(data.detail);
        }

        return data;
    },

    async saveDetail() {
        if (!this.detail?.id || this.saving) {
            return;
        }
        this.saving = true;
        this.saveMessage = '';
        this.saveError = false;
        try {
            await this.patchDetail({
                image_title: this.form.image_title,
                caption: this.form.caption,
                city: this.form.city,
                country: this.form.country,
                media_keywords: this.form.media_keywords,
            });
            this.editing = false;
            this.saveMessage = 'Metadaten gespeichert.';
        } catch {
            this.saveError = true;
            this.saveMessage = 'Speichern fehlgeschlagen.';
        } finally {
            this.saving = false;
        }
    },

    async toggleStatus(field) {
        if (!this.detail?.id || this.statusSaving) {
            return;
        }
        const current = Boolean(this.detail.status?.[field]);
        const next = !current;
        this.statusSaving = true;
        this.saveMessage = '';
        this.saveError = false;
        try {
            await this.patchDetail({ [field]: next });
            this.saveMessage = 'Status aktualisiert.';
        } catch {
            this.saveError = true;
            this.saveMessage = 'Status konnte nicht gespeichert werden.';
        } finally {
            this.statusSaving = false;
        }
    },

    toggleEditing(force) {
        if (typeof force === 'boolean') {
            this.editing = force;
        } else {
            this.editing = !this.editing;
        }
        if (this.editing && this.detail) {
            this.applyDetail(this.detail);
        }
    },

    async detailPrev() {
        if (this.imageIds.length < 2) {
            return;
        }
        this.detailIndex = (this.detailIndex - 1 + this.imageIds.length) % this.imageIds.length;
        this.editing = false;
        await this.loadDetail(this.imageIds[this.detailIndex]);
    },

    async detailNext() {
        if (this.imageIds.length < 2) {
            return;
        }
        this.detailIndex = (this.detailIndex + 1) % this.imageIds.length;
        this.editing = false;
        await this.loadDetail(this.imageIds[this.detailIndex]);
    },

    get detailPositionLabel() {
        if (!this.imageIds.length) {
            return '';
        }

        return `${this.detailIndex + 1} / ${this.imageIds.length}`;
    },

    get activePreviewUrl() {
        if (!this.detail) {
            return '';
        }
        const urls = this.detail.preview_urls || {};

        return urls.original || this.detail.preview_url || this.detail.thumb_url || '';
    },
}));
