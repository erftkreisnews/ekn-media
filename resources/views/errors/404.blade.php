<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Seite nicht gefunden</title>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 0;
            font-family: "Inter", "Segoe UI", Arial, Helvetica, sans-serif;
            background:
                radial-gradient(1200px 400px at 15% -10%, #dbeafe 0%, transparent 60%),
                radial-gradient(900px 300px at 90% 0%, #cffafe 0%, transparent 55%),
                linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            color: #0f172a;
            display: grid;
            place-items: center;
            min-height: 100vh;
            padding: 24px;
        }
        .card {
            width: min(980px, 100%);
            background: #ffffff;
            border: 1px solid #dbe3ef;
            border-radius: 20px;
            padding: 34px 30px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.10);
        }
        .label {
            display: inline-block;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #0b3a63;
            background: #e0ecff;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 6px 11px;
            margin-bottom: 10px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: clamp(27px, 3vw, 34px);
            line-height: 1.2;
            text-align: center;
        }
        h2 {
            margin: 0 0 18px;
            font-size: clamp(24px, 2.3vw, 30px);
            line-height: 1.3;
            text-align: center;
            color: #0b3a63;
        }
        p {
            margin: 0 0 13px;
            line-height: 1.6;
            color: #334155;
            font-size: 17px;
        }
        .header {
            text-align: center;
            margin-bottom: 18px;
        }
        .subline {
            margin: 0 auto 18px;
            max-width: 680px;
            text-align: center;
            font-size: 15px;
            color: #64748b;
        }
        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 240px;
            gap: 28px;
            align-items: start;
        }
        .copy {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 18px 16px;
        }
        .portrait {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #d1d9e6;
            background: #f8fafc;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .portrait img {
            width: 100%;
            height: auto;
            display: block;
        }
        .portrait figcaption {
            margin: 0;
            padding: 10px 12px;
            background: #ffffff;
            color: #475569;
            font-size: 12px;
            line-height: 1.4;
        }
        .cta {
            margin-top: 14px;
            padding: 14px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background: #ffffff;
        }
        .phone {
            font-weight: 700;
            color: #0f172a;
        }
        .actions {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .button {
            display: inline-block;
            text-decoration: none;
            background: #0b3a63;
            color: #ffffff;
            padding: 11px 17px;
            border-radius: 8px;
            font-weight: 600;
        }
        .button:hover {
            background: #0a3358;
        }
        .button.secondary {
            background: #ffffff;
            color: #0b3a63;
            border: 1px solid #93c5fd;
        }
        .button.secondary:hover {
            background: #f0f9ff;
        }
        .muted {
            color: #64748b;
            font-size: 14px;
            margin-top: 12px;
        }
        @media (max-width: 760px) {
            .card {
                padding: 24px 18px;
                border-radius: 16px;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            .portrait {
                max-width: 240px;
                margin: 0 auto;
            }
            p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    @php($targetUrl = $homeUrl ?? url('/'))
    <main class="card" role="main">
        <header class="header">
            <span class="label">404 - Seite nicht gefunden</span>
            <h1>Diese Seite ist aktuell nicht erreichbar.</h1>
            <p class="subline">Du wirst automatisch zur Startseite weitergeleitet. Falls du sofort weiter moechtest, nutze den Button unten.</p>
        </header>
        <div class="grid">
            <section class="copy">
                <h2>Bild- und Videomaterial aus dem Großraum Köln/Bonn</h2>
                <p>Wenn im Großraum Köln/Bonn etwas passiert, zählt für Redaktionen vor allem eines: schnell verfügbares, verlässliches Bild- und Videomaterial.</p>
                <p>Ich bin Alexander Franz, Foto- und Videojournalist.<br>Ich begleite Einsatzlagen und aktuelle Ereignisse vor Ort und arbeite direkt für Redaktionen.</p>
                <p>Hier finden Sie Pressefotos und Videomaterial aus dem Großraum Köln/Bonn – kurzfristig verfügbar, klar zugeordnet und für die redaktionelle Nutzung vorbereitet.</p>

                <div class="cta">
                    <p class="phone">📞 Material verfügbar – jetzt direkt anrufen: 02236 4809 488</p>
                    <p>Automatische Weiterleitung zur Startseite in <strong><span id="countdown">8</span> Sekunden</strong>.</p>
                    <div class="actions">
                        <a class="button" href="{{ $targetUrl }}">Zur Startseite</a>
                        <a class="button secondary" href="tel:+4922364809488">Jetzt anrufen</a>
                    </div>
                    <p class="muted">Wenn der Fehler bleibt, bitte Seite neu laden oder den Link prüfen.</p>
                </div>
            </section>

            <figure class="portrait">
                <img
                    src="{{ asset('images/alexander-franz-portrait.png') }}"
                    alt="Bild- und Videojournalist Alexander Franz im Einsatz"
                    title="Bild- und Videojournalist Alexander Franz im Einsatz"
                    loading="lazy"
                    decoding="async"
                >
                <figcaption>Alexander Franz beim 24-Stunden-Rennen am Nuerburgring.</figcaption>
            </figure>
        </div>
    </main>

    <script>
        (function () {
            var seconds = 8;
            var target = @json($targetUrl);
            var el = document.getElementById('countdown');
            var timer = window.setInterval(function () {
                seconds -= 1;
                if (el) {
                    el.textContent = String(seconds);
                }
                if (seconds <= 0) {
                    window.clearInterval(timer);
                    window.location.assign(target);
                }
            }, 1000);
        })();
    </script>
</body>
</html>
