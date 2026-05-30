/**
 * Aufteilung von Vorlagen-Blocktext (Titel:, Webtext:, …) in die News-Formularfelder.
 * Button nutzt bevorzugt POST /admin/news/parse-blocktext (PHP = Referenz).
 */
(function () {
    var FIELD_BY_LABEL = {
        titel: 'title',
        dachzeile: 'teaser',
        unterzeile: 'subheadline',
        webtext: 'body',
        schlagwoerter: 'keywords',
        metadaten: null,
        bundesland: 'federal_state',
        stadt: 'city',
        strasse: 'street',
        status: 'status',
        veroeffentlichungsdatum: 'published_at',
        embargo: 'embargo_at',
        byline: 'author_credit',
        land: 'country',
    };

    var LABEL_LINE =
        /^\s*\*{0,2}\s*(Titel|Dachzeile|Unterzeile|Web-Text|Webtext|Web\s+Text|Schlagwörter|Schlagwoerter|Metadaten|Bundesland|Stadt|Straße|Strasse|Status|Veröffentlichungsdatum|Veroeffentlichungsdatum|Embargo|Byline|Land)\s*\*{0,2}\s*:\s*\*{0,2}\s*(.*)$/imu;

    function normalizeBlockText(s) {
        return String(s || '')
            .replace(/^\uFEFF/, '')
            .replace(/[\u200B-\u200D\u2060]/g, '')
            .replace(/\uFF1A/g, ':')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n');
    }

    function normalizeLabelKey(label) {
        return String(label)
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '')
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss')
            .replace(/-/g, '');
    }

    function trimValue(s) {
        return String(s || '')
            .replace(/^\s+|\s+$/g, '')
            .replace(/\n{3,}/g, '\n\n');
    }

    function parseBlocktext(raw) {
        var text = normalizeBlockText(raw);
        var lines = text.split('\n');
        var acc = {};
        var currentField = null;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var m = line.match(LABEL_LINE);
            if (m) {
                var norm = normalizeLabelKey(m[1]);
                var rest = m[2] || '';
                if (norm === 'metadaten') {
                    currentField = null;
                    continue;
                }
                var field = FIELD_BY_LABEL[norm];
                if (!field) {
                    continue;
                }
                currentField = field;
                if (rest) {
                    acc[field] = acc[field] ? acc[field] + '\n' + rest : rest;
                }
                continue;
            }
            if (currentField) {
                acc[currentField] = acc[currentField] ? acc[currentField] + '\n' + line : line;
            }
        }

        var out = {};
        for (var k in acc) {
            if (!Object.prototype.hasOwnProperty.call(acc, k)) continue;
            var t = trimValue(acc[k]);
            if (t) {
                out[k] = t;
            }
        }
        return out;
    }

    function limitChars(s, max) {
        var a = Array.from(String(s || ''));
        if (a.length <= max) {
            return String(s || '');
        }
        return a.slice(0, max).join('');
    }

    function formatDatetimeLocal(d) {
        function pad(n) {
            return String(n).padStart(2, '0');
        }
        return (
            d.getFullYear() +
            '-' +
            pad(d.getMonth() + 1) +
            '-' +
            pad(d.getDate()) +
            'T' +
            pad(d.getHours()) +
            ':' +
            pad(d.getMinutes())
        );
    }

    function parsePublishedAtInput(val) {
        var t = String(val || '').trim();
        var low = t.toLowerCase();
        if (!t || low === 'sofort' || low === 'jetzt' || low === 'n/v' || low === '—' || low === '-') {
            return formatDatetimeLocal(new Date());
        }
        var dm = t.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/);
        if (dm) {
            var day = parseInt(dm[1], 10);
            var month = parseInt(dm[2], 10) - 1;
            var year = parseInt(dm[3], 10);
            var hour = dm[4] !== undefined ? parseInt(dm[4], 10) : new Date().getHours();
            var min = dm[5] !== undefined ? parseInt(dm[5], 10) : new Date().getMinutes();
            return formatDatetimeLocal(new Date(year, month, day, hour, min));
        }
        var parsed = new Date(t);
        if (!isNaN(parsed.getTime())) {
            return formatDatetimeLocal(parsed);
        }
        return null;
    }

    function parseEmbargoInput(val) {
        var t = String(val || '').trim();
        var low = t.toLowerCase();
        if (!t || low === '-' || low === '—' || low === 'keine' || low === 'nein') {
            return '';
        }
        return parsePublishedAtInput(t) || '';
    }

    function mapStatusToSelect(val) {
        var v = String(val || '').trim().toLowerCase();
        try {
            v = v.normalize('NFD').replace(/\p{M}/gu, '');
        } catch (e1) {
            try {
                v = v.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            } catch (e2) {
                /* ignorieren */
            }
        }
        if (!v) {
            return null;
        }
        if (v.indexOf('entwurf') !== -1) {
            return 'draft';
        }
        if (v.indexOf('review') !== -1) {
            return 'review';
        }
        if (v.indexOf('archiv') !== -1) {
            return 'archived';
        }
        if (
            v.indexOf('ready') !== -1 ||
            v.indexOf('veroffentlich') !== -1 ||
            v.indexOf('published') !== -1 ||
            v.indexOf('freigabe') !== -1
        ) {
            return 'published';
        }
        return null;
    }

    function toFormFields(parsed) {
        var out = {};
        if (parsed.title) {
            out.title = limitChars(parsed.title, 265);
        }
        if (parsed.teaser) {
            out.teaser = limitChars(parsed.teaser, 255);
        }
        if (parsed.subheadline) {
            out.subheadline = limitChars(parsed.subheadline, 512);
        }
        if (parsed.keywords) {
            out.keywords = limitChars(parsed.keywords.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim(), 512);
        }
        if (parsed.body) {
            out.body = parsed.body;
        }
        if (parsed.country) {
            out.country = limitChars(parsed.country, 255);
        }
        if (parsed.federal_state) {
            out.federal_state = limitChars(parsed.federal_state, 255);
        }
        if (parsed.city) {
            out.city = limitChars(parsed.city, 255);
        }
        if (parsed.street) {
            out.street = limitChars(parsed.street, 255);
        }
        if (parsed.status) {
            var st = mapStatusToSelect(parsed.status);
            if (st) {
                out.status = st;
            }
        }
        if (parsed.published_at) {
            var pub = parsePublishedAtInput(parsed.published_at);
            if (pub) {
                out.published_at = pub;
            }
        }
        if (Object.prototype.hasOwnProperty.call(parsed, 'embargo_at')) {
            out.embargo_at = parseEmbargoInput(parsed.embargo_at);
        }
        if (parsed.author_credit) {
            out.author_credit = limitChars(parsed.author_credit, 255);
        }
        return out;
    }

    function getBodyTextarea() {
        var el = document.getElementById('body');
        if (el && el.tagName === 'TEXTAREA') {
            return el;
        }
        var q = document.querySelector('textarea[name="body"]');
        return q && q.tagName === 'TEXTAREA' ? q : null;
    }

    function getParseUrl() {
        var f = document.getElementById('newsEditForm') || document.getElementById('news-form');
        if (!f) {
            return '';
        }
        return f.getAttribute('data-ekn-parse-blocktext-url') || '';
    }

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') || '' : '';
    }

    function setField(id, value, eventName) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.value = value == null ? '' : String(value);
        el.dispatchEvent(new Event(eventName || 'input', { bubbles: true }));
    }

    /** Byline aus Blocktext: passenden Benutzer im Credit-Dropdown wählen (Name = Anzeigetext). */
    function setAuthorCreditUserFromByline(byline) {
        var sel = document.getElementById('author_credit_user_id');
        if (!sel || sel.tagName !== 'SELECT') {
            return;
        }
        var want = String(byline || '')
            .replace(/^\s+|\s+$/g, '');
        if (!want) {
            return;
        }
        var opts = sel.options;
        for (var i = 0; i < opts.length; i++) {
            var o = opts[i];
            if (String(o.textContent || '')
                .replace(/^\s+|\s+$/g, '') === want) {
                sel.value = o.value;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }
    }

    function applyFields(fields) {
        if (!fields || typeof fields !== 'object') {
            return;
        }
        if (fields.title) {
            setField('title', fields.title);
        }
        if (fields.teaser) {
            setField('teaser', fields.teaser);
        }
        if (fields.subheadline) {
            setField('subheadline', fields.subheadline);
        }
        if (fields.body) {
            setField('body', fields.body);
        }
        if (fields.keywords) {
            setField('keywords', fields.keywords);
        }
        if (fields.country) {
            setField('country', fields.country);
        }
        if (fields.federal_state) {
            setField('federal_state', fields.federal_state);
        }
        if (fields.city) {
            setField('city', fields.city);
        }
        if (fields.street) {
            setField('street', fields.street);
        }
        if (fields.status) {
            setField('status', fields.status, 'change');
        }
        if (fields.published_at) {
            setField('published_at', fields.published_at);
        }
        if (Object.prototype.hasOwnProperty.call(fields, 'embargo_at')) {
            setField('embargo_at', fields.embargo_at);
        }
        if (fields.author_credit) {
            setAuthorCreditUserFromByline(fields.author_credit);
        }
    }

    function showFeedback(msg, isError) {
        var fb = document.getElementById('news-blocktext-split-feedback');
        if (!fb) {
            return;
        }
        fb.textContent = msg;
        fb.classList.remove('hidden', 'text-emerald-700', 'text-red-700');
        fb.classList.add(isError ? 'text-red-700' : 'text-emerald-700');
        fb.classList.remove('hidden');
        window.clearTimeout(showFeedback._t);
        showFeedback._t = window.setTimeout(function () {
            fb.classList.add('hidden');
        }, 12000);
    }

    /** Erster Satz bzw. gekürzter Anfang für Titel (max. maxLen), wenn nur Fließtext im Web-Text steht. */
    function extractFirstSentenceForTitle(raw, maxLen) {
        maxLen = maxLen || 265;
        var s = String(raw || '')
            .replace(/\s+/g, ' ')
            .trim();
        if (!s) {
            return '';
        }
        var end = s.indexOf('. ');
        if (end === -1) {
            end = s.indexOf('.\n');
        }
        if (end === -1 && s.charAt(s.length - 1) === '.') {
            end = s.length - 1;
        }
        var one = end >= 0 ? s.slice(0, end + 1) : s;
        one = one.trim();
        if (one.length > maxLen) {
            one = limitChars(one, maxLen);
            var lastSp = one.lastIndexOf(' ');
            if (lastSp > 40) {
                one = one.slice(0, lastSp).trim() + '…';
            }
        }
        return one.trim();
    }

    /**
     * Nur bei Button: kein Vorlagen-Block, aber langer Text und leeres Titel-Feld → Titel aus erstem Satz.
     * @returns {boolean} true wenn übernommen
     */
    function tryPlainTextTitleFallback(raw, fromButton) {
        if (!fromButton) {
            return false;
        }
        var t = String(raw || '').trim();
        if (t.length < 40) {
            return false;
        }
        var titleEl = document.getElementById('title');
        if (!titleEl || String(titleEl.value || '').trim() !== '') {
            return false;
        }
        var candidate = extractFirstSentenceForTitle(t, 265);
        if (candidate.length < 8) {
            return false;
        }
        setField('title', candidate);
        showFeedback(
            'Kein Vorlagen-Block erkannt. Titel wurde aus dem ersten Satz vorgeschlagen; Web-Text bleibt unverändert. Jetzt speichern möglich.',
            false,
        );
        return true;
    }

    function hasEnoughLabels(t) {
        var re =
            /^\s*\*{0,2}\s*(Titel|Dachzeile|Unterzeile|Web-Text|Webtext|Web\s+Text|Schlagwörter|Schlagwoerter|Metadaten|Bundesland|Stadt|Straße|Strasse|Status)\s*\*{0,2}\s*:\s*\*{0,2}\s*/gim;
        var m = t.match(re);
        return m && m.length >= 2;
    }

    function looksStructured(t) {
        if (!t || t.length < 8) {
            return false;
        }
        if (!/^\s*\*{0,2}\s*Titel\s*\*{0,2}\s*:\s*\*{0,2}\s*/im.test(t)) {
            return false;
        }
        if (/^\s*\*{0,2}\s*(Web-Text|Webtext|Web\s+Text)\s*\*{0,2}\s*:\s*\*{0,2}\s*/im.test(t)) {
            return true;
        }
        return /^\s*\*{0,2}\s*(Dachzeile|Unterzeile|Schlagwörter|Schlagwoerter|Metadaten|Bundesland|Stadt|Straße|Strasse|Status)\s*\*{0,2}\s*:\s*\*{0,2}\s*/im.test(
            t,
        );
    }

    var debounceTimer = null;
    var splitBusy = false;

    function clearSplitTimer() {
        window.clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    function fetchServerApply(text, fromButton) {
        var url = getParseUrl();
        if (!url) {
            splitBusy = false;
            if (fromButton) {
                showFeedback('Server-Aufteilen nicht konfiguriert (data-ekn-parse-blocktext-url).', true);
            }
            return;
        }
        var token = csrfToken();
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ text: text }),
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (j) {
                if (j && j.ok === true && j.fields && typeof j.fields === 'object') {
                    applyFields(j.fields);
                    clearSplitTimer();
                    showFeedback(
                        'Felder übernommen (Server). Im Web-Text steht nur noch der Abschnitt unter „Webtext:“.',
                        false,
                    );
                } else if (fromButton) {
                    var reason = j && j.reason ? String(j.reason) : 'Antwort ungültig';
                    showFeedback('Aufteilen nicht möglich: ' + reason + '.', true);
                }
            })
            .catch(function (err) {
                if (fromButton) {
                    showFeedback(
                        err && err.message ? 'Netzwerkfehler: ' + err.message : 'Netzwerkfehler beim Aufteilen.',
                        true,
                    );
                }
                if (typeof console !== 'undefined' && console.error) {
                    console.error('ekn blocktext server parse', err);
                }
            })
            .finally(function () {
                splitBusy = false;
            });
    }

    /** @param {boolean} fromButton – Fehlermeldungen nur bei Klick */
    function runSplit(fromButton) {
        if (splitBusy) {
            return;
        }
        var ta = getBodyTextarea();
        if (!ta) {
            if (fromButton) {
                showFeedback('Web-Text-Feld nicht gefunden.', true);
            }
            return;
        }
        var text = normalizeBlockText(ta.value);
        if (!looksStructured(text) || !hasEnoughLabels(text)) {
            if (fromButton) {
                if (tryPlainTextTitleFallback(text.trim(), true)) {
                    return;
                }
                showFeedback(
                    'Kein Vorlagen-Block: Entweder Zeilen wie „Titel:“ und „Webtext:“ einfügen, oder nur Fließtext bei leerem Titel – dann „Nachricht aufteilen“ füllt den Titel aus dem ersten Satz.',
                    true,
                );
            }
            return;
        }

        if (fromButton && getParseUrl()) {
            splitBusy = true;
            fetchServerApply(text, true);
            return;
        }

        splitBusy = true;
        try {
            var parsed = parseBlocktext(text);
            var keys = Object.keys(parsed);
            if (keys.length >= 2) {
                applyFields(toFormFields(parsed));
                clearSplitTimer();
                showFeedback(
                    'Felder übernommen: Titel, Dachzeile, Unterzeile … Im Web-Text nur noch „Webtext:“.',
                    false,
                );
                splitBusy = false;
                return;
            }
            if (getParseUrl()) {
                fetchServerApply(text, fromButton);
                return;
            }
            if (fromButton) {
                showFeedback('Zu wenig Felder erkannt.', true);
            }
        } catch (err) {
            if (fromButton) {
                showFeedback(
                    err && err.message ? 'Aufteilen-Fehler: ' + err.message : 'Aufteilen fehlgeschlagen (JavaScript).',
                    true,
                );
            }
            if (typeof console !== 'undefined' && console.error) {
                console.error('ekn blocktext split', err);
            }
        }
        splitBusy = false;
    }

    window.eknSplitNewsBlocktext = function () {
        clearSplitTimer();
        runSplit(true);
    };

    function scheduleRun(ms) {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(function () {
            debounceTimer = null;
            runSplit(false);
        }, ms);
    }

    function isBodyTextareaEl(node) {
        if (!node || node.tagName !== 'TEXTAREA') {
            return false;
        }
        return node.id === 'body' || node.getAttribute('name') === 'body';
    }

    function resolveBodyTextareaFromEvent(e) {
        var t = e.target;
        if (!t) {
            return null;
        }
        var ta = isBodyTextareaEl(t) ? t : t.closest && t.closest('textarea#body');
        if (!ta && t.closest) {
            ta = t.closest('textarea[name="body"]');
        }
        if (!ta && document.activeElement && isBodyTextareaEl(document.activeElement)) {
            ta = document.activeElement;
        }
        return ta || null;
    }

    document.addEventListener(
        'paste',
        function (e) {
            var ta = resolveBodyTextareaFromEvent(e);
            if (!ta) {
                return;
            }
            clearSplitTimer();
            scheduleRun(200);
        },
        true,
    );

    document.addEventListener('focusout', function (e) {
        if (!isBodyTextareaEl(e.target)) {
            return;
        }
        var nv = normalizeBlockText(e.target.value);
        if (looksStructured(nv) && hasEnoughLabels(nv)) {
            clearSplitTimer();
            scheduleRun(30);
        }
    });

    document.addEventListener('input', function (e) {
        if (!isBodyTextareaEl(e.target)) {
            return;
        }
        if (e.isComposing) {
            return;
        }
        var nv = normalizeBlockText(e.target.value);
        if (!looksStructured(nv) || !hasEnoughLabels(nv)) {
            return;
        }
        clearSplitTimer();
        scheduleRun(500);
    });

    window.addEventListener('pageshow', function () {
        clearSplitTimer();
    });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            clearSplitTimer();
        }
    });
})();
