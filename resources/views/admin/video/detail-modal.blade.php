<div
    x-show="detailOpen"
    x-cloak
    class="admin-image-detail-overlay"
    @keydown.escape.window="closeDetail()"
    @keydown.arrow-left.window="detailOpen && detailPrev()"
    @keydown.arrow-right.window="detailOpen && detailNext()"
>
    <div class="admin-image-detail-backdrop" @click="closeDetail()"></div>

    <div
        class="admin-image-detail-dialog"
        role="dialog"
        aria-modal="true"
        aria-label="Video-Details"
        @click.stop
    >
        <header class="admin-image-detail-header">
            <div class="admin-image-detail-header-row">
                <button type="button" class="admin-image-detail-icon-btn" @click="closeDetail()" aria-label="Schließen">
                    <span aria-hidden="true">✕</span>
                </button>
                <template x-if="detail && !detail.error">
                    <span class="admin-image-detail-id" x-text="'#' + detail.id"></span>
                </template>
                <div class="admin-image-detail-nav" x-show="videoIds.length > 1">
                    <button type="button" class="admin-image-detail-icon-btn" @click="detailPrev()" aria-label="Vorheriges Video">‹</button>
                    <span class="admin-image-detail-counter" x-text="detailPositionLabel"></span>
                    <button type="button" class="admin-image-detail-icon-btn" @click="detailNext()" aria-label="Nächstes Video">›</button>
                </div>
                <p class="admin-image-detail-filename" x-show="detail?.original_name" x-text="detail?.original_name"></p>
            </div>
        </header>

        <div
            x-show="detail?.status?.versand && !detailLoading && !detail?.error"
            class="admin-image-detail-sent-banner"
            x-cloak
        >
            <span aria-hidden="true">✓</span>
            <span x-text="detail.flags?.ftp_sent ? 'Bereits gesendet' : 'Versand freigegeben'"></span>
        </div>

        <div x-show="detailLoading" class="admin-image-detail-loading">
            Details werden geladen…
        </div>

        <p x-show="detail?.error" x-text="detail?.error" class="admin-image-detail-error" x-cloak></p>

        <div x-show="detail && !detailLoading && !detail?.error" class="admin-image-detail-body" x-cloak>
            <aside class="admin-image-detail-sidebar">
                <div class="admin-image-detail-preview-box">
                    <div class="admin-image-detail-preview-wrap">
                        <template x-if="detail?.playback_url">
                            <video
                                :key="detail.id"
                                :src="detail.playback_url"
                                :poster="detail.poster_url || undefined"
                                controls
                                playsinline
                                preload="metadata"
                                class="admin-image-detail-preview admin-image-detail-preview--video"
                            ></video>
                        </template>
                        <template x-if="!detail?.playback_url && (detail?.poster_url || detail?.preview_url)">
                            <img
                                :src="detail.poster_url || detail.preview_url"
                                :alt="detail.headline || detail.original_name || 'Video'"
                                class="admin-image-detail-preview"
                            >
                        </template>
                        <template x-if="!detail?.playback_url && !detail?.poster_url && !detail?.preview_url">
                            <div class="flex min-h-[12rem] flex-col items-center justify-center gap-2 bg-[#111827] text-gray-400">
                                <svg class="h-10 w-10 opacity-60" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                <span class="text-xs">Keine Vorschau</span>
                            </div>
                        </template>
                        <a
                            x-show="detail?.urls?.edit"
                            :href="editPageUrl()"
                            class="admin-image-detail-edit-chip"
                            @click.stop
                        >
                            <span aria-hidden="true">✎</span> Vollständig bearbeiten
                        </a>
                    </div>
                </div>

                <section class="admin-image-detail-panel">
                    <h3 class="admin-image-detail-section-title">
                        <span class="admin-image-detail-section-icon" aria-hidden="true">⚑</span> Status
                    </h3>
                    <div class="admin-image-detail-status-grid">
                        <span class="admin-image-detail-status-pill" :class="detail.flags?.processed ? 'is-ok' : ''">Verarbeitet</span>
                        <span class="admin-image-detail-status-pill" :class="detail.flags?.ai_processed ? 'is-ok' : ''">KI verarbeitet</span>
                        <span class="admin-image-detail-status-pill" :class="detail.flags?.metadata_written ? 'is-ok' : ''">Metadaten</span>
                        <span class="admin-image-detail-status-pill" :class="detail.flags?.ftp_sent ? 'is-ok' : ''">FTP versendet</span>
                        <span class="admin-image-detail-status-pill" :class="detail.flags?.public ? 'is-ok' : ''">Öffentlich</span>
                    </div>

                    <div class="admin-image-detail-toggle-row">
                        <button
                            type="button"
                            class="admin-image-detail-feature-btn"
                            :class="detail.status?.versand ? 'is-active' : ''"
                            :disabled="statusSaving"
                            @click="toggleStatus('versand')"
                        >
                            Versand
                        </button>
                        <button
                            type="button"
                            class="admin-image-detail-feature-btn"
                            :class="detail.status?.is_visible ? 'is-active' : ''"
                            :disabled="statusSaving"
                            @click="toggleStatus('is_visible')"
                        >
                            Sichtbar
                        </button>
                    </div>
                </section>

                <section class="admin-image-detail-panel admin-image-detail-panel--tech">
                    <h3 class="admin-image-detail-section-title">
                        <span class="admin-image-detail-section-icon" aria-hidden="true">⚙</span> Technische Details
                    </h3>
                    <dl class="admin-image-detail-tech-list">
                        <div><dt>Dateiname</dt><dd x-text="detail.original_name || '—'"></dd></div>
                        <div><dt>Größe</dt><dd x-text="detail.file_size_label || '—'"></dd></div>
                        <div><dt>Dauer</dt><dd x-text="detail.duration_label || '—'"></dd></div>
                        <div><dt>Format</dt><dd x-text="detail.format || '—'"></dd></div>
                        <div x-show="detail.metadata_recorded_at_label"><dt>Aufnahmedatum</dt><dd x-text="detail.metadata_recorded_at_label"></dd></div>
                        <div x-show="detail.created_at_label"><dt>Hochgeladen</dt><dd x-text="detail.created_at_label"></dd></div>
                    </dl>
                </section>

                <section class="admin-image-detail-panel" x-show="!detail.flags?.ftp_sent">
                    <h3 class="admin-image-detail-section-title">FTP / Übertragungen</h3>
                    <p class="admin-image-detail-muted">Noch keine Übertragungen protokolliert.</p>
                </section>
            </aside>

            <div class="admin-image-detail-main">
                <div class="admin-image-detail-main-head">
                    <h3 class="admin-image-detail-section-title">
                        <span class="admin-image-detail-section-icon" aria-hidden="true">📋</span> Metadaten
                    </h3>
                    <button
                        type="button"
                        class="admin-image-detail-edit-toggle"
                        @click="toggleEditing()"
                        x-text="editing ? 'Ansicht' : 'Bearbeiten'"
                    ></button>
                </div>

                <p x-show="saveMessage" class="admin-image-detail-save-msg" :class="saveError ? 'is-error' : 'is-ok'" x-text="saveMessage" x-cloak></p>

                <template x-if="!editing">
                    <div class="admin-image-detail-fields admin-image-detail-fields--view">
                        <div class="admin-image-detail-field">
                            <p class="admin-image-detail-label">Titel</p>
                            <p class="admin-image-detail-headline" x-text="detail.headline || '—'"></p>
                        </div>
                        <div class="admin-image-detail-field">
                            <p class="admin-image-detail-label">Beschreibung / Unterschrift</p>
                            <p class="admin-image-detail-text" x-text="detail.caption || '—'"></p>
                        </div>
                        <div class="admin-image-detail-field" x-show="detail.description">
                            <p class="admin-image-detail-label">Interne Beschreibung</p>
                            <p class="admin-image-detail-text" x-text="detail.description"></p>
                        </div>
                        <div class="admin-image-detail-field" x-show="detail.photographer">
                            <p class="admin-image-detail-label">Fotograf / Quelle</p>
                            <p x-text="detail.photographer"></p>
                        </div>
                        <div class="admin-image-detail-field" x-show="detail.metadata_location">
                            <p class="admin-image-detail-label">Ort (Lieferant)</p>
                            <p x-text="detail.metadata_location"></p>
                        </div>
                        <div class="admin-image-detail-field" x-show="detail.news_item">
                            <p class="admin-image-detail-label">Einsatz / Meldung</p>
                            <a :href="detail.news_item.edit_url" class="admin-image-detail-link" x-text="detail.news_item.title"></a>
                        </div>
                        <div class="admin-image-detail-field" x-show="detail.keywords?.length">
                            <p class="admin-image-detail-label">Keywords</p>
                            <div class="admin-image-detail-keywords">
                                <template x-for="kw in detail.keywords" :key="kw">
                                    <span class="admin-image-detail-keyword" x-text="kw"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="editing">
                    <form class="admin-image-detail-fields admin-image-detail-fields--edit" @submit.prevent="saveDetail()">
                        <div class="admin-image-detail-field">
                            <label class="admin-image-detail-label" for="detail-video-title">Titel</label>
                            <input id="detail-video-title" type="text" class="admin-image-detail-input" x-model="form.image_title">
                        </div>
                        <div class="admin-image-detail-field">
                            <label class="admin-image-detail-label" for="detail-video-caption">Beschreibung / Unterschrift</label>
                            <textarea id="detail-video-caption" rows="4" class="admin-image-detail-input admin-image-detail-textarea" x-model="form.caption"></textarea>
                        </div>
                        <div class="admin-image-detail-field">
                            <label class="admin-image-detail-label" for="detail-video-description">Interne Beschreibung</label>
                            <textarea id="detail-video-description" rows="3" class="admin-image-detail-input admin-image-detail-textarea" x-model="form.description"></textarea>
                        </div>
                        <div class="admin-image-detail-field">
                            <label class="admin-image-detail-label" for="detail-video-photographer">Fotograf / Quelle</label>
                            <input id="detail-video-photographer" type="text" class="admin-image-detail-input" x-model="form.photographer">
                        </div>
                        <div class="admin-image-detail-field-grid">
                            <div class="admin-image-detail-field">
                                <label class="admin-image-detail-label" for="detail-video-location">Ort (Lieferant)</label>
                                <input id="detail-video-location" type="text" class="admin-image-detail-input" x-model="form.metadata_location">
                            </div>
                            <div class="admin-image-detail-field">
                                <label class="admin-image-detail-label" for="detail-video-recorded">Aufnahmedatum</label>
                                <input id="detail-video-recorded" type="date" class="admin-image-detail-input" x-model="form.metadata_recorded_at">
                            </div>
                        </div>
                        <div class="admin-image-detail-field">
                            <label class="admin-image-detail-label" for="detail-video-keywords">Keywords</label>
                            <input id="detail-video-keywords" type="text" class="admin-image-detail-input" x-model="form.media_keywords" placeholder="kommagetrennt">
                        </div>
                        <div class="admin-image-detail-form-actions">
                            <button type="button" class="admin-image-detail-footer-btn" @click="toggleEditing(false)">Abbrechen</button>
                            <button type="submit" class="admin-image-detail-footer-btn admin-image-detail-footer-btn--primary" :disabled="saving">
                                <span x-text="saving ? 'Speichern…' : 'Speichern'"></span>
                            </button>
                        </div>
                    </form>
                </template>

                <div class="admin-image-detail-quick-links">
                    <a x-show="detail.urls?.quick_send" :href="detail.urls?.quick_send" class="admin-image-detail-quick-link admin-image-detail-quick-link--primary">Direktversand</a>
                    <a x-show="detail.urls?.publication_finding" :href="detail.urls?.publication_finding" class="admin-image-detail-quick-link">Fundstelle</a>
                </div>
            </div>
        </div>

        <footer class="admin-image-detail-footer">
            <div class="admin-image-detail-footer-nav">
                <button type="button" class="admin-image-detail-footer-btn admin-image-detail-footer-btn--nav" @click="detailPrev()" :disabled="videoIds.length < 2">‹ Vorheriges</button>
                <button type="button" class="admin-image-detail-footer-btn admin-image-detail-footer-btn--nav" @click="detailNext()" :disabled="videoIds.length < 2">Nächstes ›</button>
            </div>
            <div class="admin-image-detail-footer-actions">
                <a x-show="detail?.urls?.edit" :href="editPageUrl()" class="admin-image-detail-footer-btn">Vollständig bearbeiten</a>
                <button type="button" class="admin-image-detail-footer-btn admin-image-detail-footer-btn--primary" @click="closeDetail()">Schließen</button>
            </div>
        </footer>
    </div>
</div>
