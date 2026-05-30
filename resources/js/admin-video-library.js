import Alpine from 'alpinejs';

Alpine.data('adminVideoLibrary', (config = {}) => ({
    videoIds: config.videoIds || [],
    detailUrlTemplate: config.detailUrlTemplate || '',
    updateUrlTemplate: config.updateUrlTemplate || '',

    detailOpen: false,
    detailLoading: false,
    detail: null,
    detailIndex: 0,
    editing: false,
    saving: false,
    statusSaving: false,
    saveMessage: '',
    saveError: false,
    form: {
        image_title: '',
        caption: '',
        description: '',
        photographer: '',
        media_keywords: '',
        metadata_location: '',
        metadata_recorded_at: '',
    },

    init() {
        this.$watch('detailOpen', (open) => {
            document.body.classList.toggle('overflow-hidden', open);
        });
    },

    editPageUrl() {
        return this.detail?.urls?.edit || '#';
    },

    openCardDetail(mediaId, event) {
        if (event?.target?.closest('a, button')) {
            const tag = event.target.tagName?.toLowerCase();
            if (tag === 'a' || tag === 'button') {
                return;
            }
        }
        this.openDetail(mediaId);
    },

    async openDetail(mediaId) {
        const idx = this.videoIds.indexOf(Number(mediaId));
        this.detailIndex = idx >= 0 ? idx : 0;
        this.detailOpen = true;
        this.editing = false;
        this.saveMessage = '';
        await this.loadDetail(this.videoIds[this.detailIndex] ?? mediaId);
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
            description: detail.description || '',
            photographer: detail.photographer || '',
            media_keywords: detail.media_keywords || '',
            metadata_location: detail.metadata_location || '',
            metadata_recorded_at: detail.metadata_recorded_at || '',
        };
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
            this.detail = { error: 'Video-Details konnten nicht geladen werden.' };
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
                description: this.form.description,
                photographer: this.form.photographer,
                media_keywords: this.form.media_keywords,
                metadata_location: this.form.metadata_location,
                metadata_recorded_at: this.form.metadata_recorded_at || null,
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
        const next = !Boolean(this.detail.status?.[field]);
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
        if (this.videoIds.length < 2) {
            return;
        }
        this.detailIndex = (this.detailIndex - 1 + this.videoIds.length) % this.videoIds.length;
        this.editing = false;
        await this.loadDetail(this.videoIds[this.detailIndex]);
    },

    async detailNext() {
        if (this.videoIds.length < 2) {
            return;
        }
        this.detailIndex = (this.detailIndex + 1) % this.videoIds.length;
        this.editing = false;
        await this.loadDetail(this.videoIds[this.detailIndex]);
    },

    get detailPositionLabel() {
        if (!this.videoIds.length) {
            return '';
        }

        return `${this.detailIndex + 1} / ${this.videoIds.length}`;
    },
}));
