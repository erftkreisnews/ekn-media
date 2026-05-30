import Alpine from 'alpinejs';

/**
 * API-gesteuerte Kölnimage-Galerie (Hub): Filter, Layout, Lightbox, Merken/Warenkorb.
 *
 * @param {object} config
 */
Alpine.data('koelnimageGalleryHub', (config = {}) => ({
    apiBase: config.apiBase || '',
    timeWindowUrl: config.timeWindowUrl || '',
    favoritesUrl: config.favoritesUrl || '',
    cartUrl: config.cartUrl || '',
    csrfToken: config.csrfToken || '',
    articleAltFallback: config.articleAltFallback || 'Bild',
    publishedAtLabel: config.publishedAtLabel || null,
    locationLabel: config.locationLabel || null,
    photographerCredit: config.photographerCredit || null,

    filters: {
        q: '',
        sort: 'newest',
        from: '',
        to: '',
        per_page: 25,
    },
    layout: 'grid',
    photos: [],
    meta: {
        total: 0,
        current_page: 1,
        last_page: 1,
        per_page: 25,
        favorites_count: 0,
        cart_count: 0,
        licensed_download: false,
    },
    loading: false,
    error: null,

    lightboxOpen: false,
    lightboxIndex: 0,
    timeWindowOpen: false,
    timeWindowLoading: false,
    timeWindowPhotos: [],
    timeWindowMeta: null,

    init() {
        const params = new URLSearchParams(window.location.search);
        this.filters.q = params.get('q') || '';
        this.filters.sort = params.get('sort') || 'newest';
        this.filters.from = params.get('from') || '';
        this.filters.to = params.get('to') || '';
        const perPage = parseInt(params.get('per_page') || '25', 10);
        if ([10, 25, 50, 100, 200].includes(perPage)) {
            this.filters.per_page = perPage;
        }
        const page = parseInt(params.get('page') || '1', 10);
        this.fetchPhotos(page >= 1 ? page : 1);
    },

    get lightboxPhoto() {
        return this.photos[this.lightboxIndex] || null;
    },

    get resultSummary() {
        if (this.loading) {
            return 'Lade Bilder…';
        }
        const total = this.meta?.total ?? 0;
        if (total === 0) {
            return 'Keine Bilder gefunden';
        }
        const page = this.meta?.current_page ?? 1;
        const last = this.meta?.last_page ?? 1;

        return `${total} Bild${total === 1 ? '' : 'er'} · Seite ${page}/${last}`;
    },

    async fetchPhotos(page = 1) {
        if (!this.apiBase) {
            return;
        }
        this.loading = true;
        this.error = null;

        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('per_page', String(this.filters.per_page));
        params.set('sort', this.filters.sort);
        if (this.filters.q.trim() !== '') {
            params.set('q', this.filters.q.trim());
        }
        if (this.filters.from) {
            params.set('from', this.filters.from);
        }
        if (this.filters.to) {
            params.set('to', this.filters.to);
        }

        try {
            const response = await fetch(`${this.apiBase}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('Galerie konnte nicht geladen werden.');
            }
            const json = await response.json();
            this.photos = (json.data || []).map((item) => this.normalizePhoto(item));
            this.meta = json.meta || this.meta;
            this.syncBrowserUrl(page);
        } catch (err) {
            this.error = err instanceof Error ? err.message : 'Unbekannter Fehler';
            this.photos = [];
        } finally {
            this.loading = false;
        }
    },

    normalizePhoto(item) {
        return {
            ...item,
            captureLabel: this.formatCaptureLabel(item.capture_time),
            displayTitle: (item.title && String(item.title).trim())
                || (item.caption && String(item.caption).trim())
                || this.articleAltFallback,
        };
    },

    formatCaptureLabel(iso) {
        if (!iso) {
            return null;
        }
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return null;
        }
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${day}.${month}.${year}, ${hours}:${minutes} Uhr`;
    },

    applyFilters() {
        this.fetchPhotos(1);
    },

    resetFilters() {
        this.filters.q = '';
        this.filters.sort = 'newest';
        this.filters.from = '';
        this.filters.to = '';
        this.filters.per_page = 25;
        this.fetchPhotos(1);
    },

    goToPage(page) {
        const target = Math.max(1, Math.min(page, this.meta.last_page || 1));
        this.fetchPhotos(target);
        this.$refs.galleryTop?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    setLayout(mode) {
        this.layout = mode;
    },

    openLightbox(index) {
        this.lightboxIndex = index;
        this.lightboxOpen = true;
        this.timeWindowOpen = false;
    },

    closeLightbox() {
        this.lightboxOpen = false;
        this.timeWindowOpen = false;
    },

    lightboxPrev() {
        if (!this.photos.length) {
            return;
        }
        this.lightboxIndex = (this.lightboxIndex - 1 + this.photos.length) % this.photos.length;
    },

    lightboxNext() {
        if (!this.photos.length) {
            return;
        }
        this.lightboxIndex = (this.lightboxIndex + 1) % this.photos.length;
    },

    cardImageSrc(photo) {
        return photo.thumb_url || photo.preview_url || photo.url;
    },

    lightboxImageSrc(photo) {
        return photo?.url || photo?.preview_url || photo?.thumb_url;
    },

    async toggleFavorite(photo, event) {
        event?.stopPropagation();
        if (!this.favoritesUrl || !photo?.id) {
            return;
        }
        const json = await this.postToggle(this.favoritesUrl, photo.id);
        if (!json) {
            return;
        }
        photo.is_favorite = json.is_favorite;
        this.meta.favorites_count = json.favorites_count;
        this.meta.cart_count = json.cart_count;
    },

    async toggleCart(photo, event) {
        event?.stopPropagation();
        if (!this.cartUrl || !photo?.id) {
            return;
        }
        const json = await this.postToggle(this.cartUrl, photo.id);
        if (!json) {
            return;
        }
        photo.is_in_cart = json.is_in_cart;
        this.meta.cart_count = json.cart_count;
        this.meta.favorites_count = json.favorites_count;
    },

    async postToggle(url, mediaId) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ media_id: mediaId }),
            });
            if (!response.ok) {
                return null;
            }

            return await response.json();
        } catch {
            return null;
        }
    },

    async loadTimeWindow(window = '60s') {
        const photo = this.lightboxPhoto;
        if (!photo?.id || !this.timeWindowUrl) {
            return;
        }
        this.timeWindowLoading = true;
        this.timeWindowOpen = true;
        this.timeWindowPhotos = [];

        const params = new URLSearchParams({
            reference_media_id: String(photo.id),
            window,
            direction: 'both',
        });

        try {
            const response = await fetch(`${this.timeWindowUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('Zeitfenster konnte nicht geladen werden.');
            }
            const json = await response.json();
            this.timeWindowPhotos = (json.data || []).map((item) => this.normalizePhoto(item));
            this.timeWindowMeta = json.meta || null;
        } catch {
            this.timeWindowPhotos = [];
            this.timeWindowMeta = null;
        } finally {
            this.timeWindowLoading = false;
        }
    },

    openTimeWindowPhoto(photo) {
        const index = this.photos.findIndex((p) => p.id === photo.id);
        if (index >= 0) {
            this.lightboxIndex = index;
        } else {
            this.photos = [...this.timeWindowPhotos];
            const newIndex = this.photos.findIndex((p) => p.id === photo.id);
            this.lightboxIndex = newIndex >= 0 ? newIndex : 0;
        }
        this.timeWindowOpen = false;
    },

    syncBrowserUrl(page) {
        const params = new URLSearchParams();
        if (page > 1) {
            params.set('page', String(page));
        }
        if (this.filters.per_page !== 25) {
            params.set('per_page', String(this.filters.per_page));
        }
        if (this.filters.sort !== 'newest') {
            params.set('sort', this.filters.sort);
        }
        if (this.filters.q.trim() !== '') {
            params.set('q', this.filters.q.trim());
        }
        if (this.filters.from) {
            params.set('from', this.filters.from);
        }
        if (this.filters.to) {
            params.set('to', this.filters.to);
        }
        const query = params.toString();
        const next = query ? `${window.location.pathname}?${query}` : window.location.pathname;
        window.history.replaceState({}, '', next);
    },
}));
