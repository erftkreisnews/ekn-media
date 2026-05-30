import Alpine from 'alpinejs';

/**
 * Kölnimage: sequentieller Einzel-Upload per XHR (ein Bild pro Request).
 */
Alpine.data('koelnimageFotoUpload', (config = {}) => ({
    uploadUrl: config.uploadUrl || '',
    editUrl: config.editUrl || '',
    csrf: config.csrf || '',
    uploadedCount: typeof config.initialCount === 'number' ? config.initialCount : 0,
    queue: [],
    errors: [],
    dragOver: false,
    _processing: false,
    _nextId: 1,

    queueFiles(fileList) {
        if (!fileList || fileList.length === 0) return;
        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];
            if (!file || !file.type || !file.type.startsWith('image/')) {
                continue;
            }
            this.queue.push({
                id: this._nextId++,
                file,
                name: file.name,
                status: 'pending',
                statusLabel: 'Wartet',
            });
        }
        this.processQueue();
    },

    async processQueue() {
        if (this._processing) return;
        this._processing = true;
        while (true) {
            const item = this.queue.find((q) => q.status === 'pending');
            if (!item) break;
            await this.uploadOne(item);
        }
        this._processing = false;
    },

    uploadOne(item) {
        return new Promise((resolve) => {
            item.status = 'uploading';
            item.statusLabel = 'Lädt…';

            const form = new FormData();
            form.append('image', item.file, item.name);
            form.append('_token', this.csrf);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.uploadUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.onload = () => {
                let payload = null;
                try {
                    payload = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    payload = null;
                }

                if (xhr.status === 201 && payload?.success) {
                    item.status = 'done';
                    item.statusLabel = 'OK';
                    if (typeof payload.images_count === 'number') {
                        this.uploadedCount = payload.images_count;
                    } else {
                        this.uploadedCount += 1;
                    }
                    resolve();
                    return;
                }

                if (xhr.status === 409 && payload?.skipped_duplicate) {
                    item.status = 'skipped';
                    item.statusLabel = 'Duplikat';
                    resolve();
                    return;
                }

                item.status = 'error';
                item.statusLabel = 'Fehler';
                const msg = payload?.message
                    || (payload?.errors?.image?.[0])
                    || `Upload fehlgeschlagen (${xhr.status})`;
                if (!this.errors.includes(msg)) {
                    this.errors.push(msg);
                }
                resolve();
            };

            xhr.onerror = () => {
                item.status = 'error';
                item.statusLabel = 'Netzwerk';
                this.errors.push('Netzwerkfehler beim Upload.');
                resolve();
            };

            xhr.send(form);
        });
    },
}));
