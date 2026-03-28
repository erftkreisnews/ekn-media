@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Bereiche unkenntlich machen</h1>
                <p class="mt-1 text-sm text-gray-600">Markieren Sie Bereiche (z. B. Gesichter oder Kennzeichen). Wählen Sie „Verwischen“ für weiche Unkenntlichkeit (Kundenbilder) oder „Pixelieren“ für starke Anonymisierung.</p>
            </div>
            <a href="{{ route('admin.news.media.edit', [$newsItem, $medium]) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                Zurück zur Bearbeitung
            </a>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
            <strong>Hinweis:</strong> Nach dem Speichern werden die markierten Bereiche dauerhaft in der Bilddatei verändert. Eine Rücknahme ist nicht möglich.
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <p class="text-sm text-gray-600">Rechteck aufziehen zum Markieren. Markierung verschieben: in die Fläche klicken und ziehen. Größe ändern: an den roten Ecken ziehen. Mehrere Bereiche möglich.</p>
            </div>
            <div class="p-4 flex justify-center bg-gray-100 min-h-[70vh] overflow-auto" id="unkentlich-canvas-container">
                <div class="inline-block">
                    <canvas id="unkentlich-canvas" class="cursor-crosshair block" style="background: #e5e7eb;"></canvas>
                </div>
                <p id="unkentlich-load-error" class="hidden text-sm text-red-600 mt-2">Bild konnte nicht geladen werden.</p>
            </div>
            <form method="post" action="{{ route('admin.news.media.unkentlich.apply', [$newsItem, $medium->id]) }}" class="p-6 border-t border-gray-200" id="unkentlich-form">
                @csrf
                <input type="hidden" name="regions" id="regions-input" value="[]">
                <div class="flex flex-wrap items-center justify-center gap-6 mb-4">
                    <fieldset class="flex flex-wrap gap-4 items-center">
                        <legend class="sr-only">Art der Unkenntlichkeit</legend>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="mode" value="blur" class="h-4 w-4 border-gray-300 text-[#092E48] focus:ring-[#092E48]" checked>
                            <span class="text-sm font-medium text-gray-700">Verwischen</span>
                            <span class="text-xs text-gray-500">(weich, z. B. Gesichter)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="mode" value="pixelate" class="h-4 w-4 border-gray-300 text-[#092E48] focus:ring-[#092E48]">
                            <span class="text-sm font-medium text-gray-700">Pixelieren</span>
                            <span class="text-xs text-gray-500">(stark, z. B. Kennzeichen)</span>
                        </label>
                    </fieldset>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <button type="button" id="btn-clear" class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        Alle Markierungen löschen
                    </button>
                    <button type="submit" id="btn-apply" class="px-4 py-2 text-sm font-medium rounded-md bg-[#092E48] text-white hover:bg-[#0b3858]" disabled>
                        Bereiche unkenntlich machen und speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
(function () {
    var canvas = document.getElementById('unkentlich-canvas');
    var ctx = canvas.getContext('2d');
    var regionsInput = document.getElementById('regions-input');
    var btnClear = document.getElementById('btn-clear');
    var btnApply = document.getElementById('btn-apply');
    var img = new Image();
    img.src = "{{ $imageUrl ?? $medium->url }}";

    var regions = [];
    var startX, startY, isDrawing = false;
    var isMoving = false, moveRegionIndex = -1, moveOffsetX = 0, moveOffsetY = 0;
    var isResizing = false, resizeRegionIndex = -1, resizeCorner = '';
    var scale = 1, offsetX = 0, offsetY = 0, drawW = 0, drawH = 0, dpr = 1;
    var imgW = 0, imgH = 0;
    var HANDLE = 22;
    var HANDLE_HIT = 32;

    function getEventPos(e) {
        var clientX = e.clientX != null ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : (e.changedTouches && e.changedTouches[0] ? e.changedTouches[0].clientX : 0));
        var clientY = e.clientY != null ? e.clientY : (e.touches && e.touches[0] ? e.touches[0].clientY : (e.changedTouches && e.changedTouches[0] ? e.changedTouches[0].clientY : 0));
        var rect = canvas.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return { x: 0, y: 0 };
        var scaleX = drawW / rect.width;
        var scaleY = drawH / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    }

    function toPercent(cx, cy, cw, ch) {
        return {
            x: Math.max(0, Math.min(100, ((cx - offsetX) / drawW) * 100)),
            y: Math.max(0, Math.min(100, ((cy - offsetY) / drawH) * 100)),
            w: Math.max(0.1, Math.min(100, (cw / drawW) * 100)),
            h: Math.max(0.1, Math.min(100, (ch / drawH) * 100))
        };
    }

    function fromPercent(r) {
        return {
            x: offsetX + (r.x / 100) * drawW,
            y: offsetY + (r.y / 100) * drawH,
            w: (r.w / 100) * drawW,
            h: (r.h / 100) * drawH
        };
    }

    function hitTest(px, py) {
        var hitHalf = HANDLE_HIT / 2;
        var innerMargin = HANDLE_HIT / 2;
        for (var i = regions.length - 1; i >= 0; i--) {
            var r = regions[i];
            var rect = fromPercent(r);
            var corners = [
                { id: 'nw', cx: rect.x, cy: rect.y },
                { id: 'ne', cx: rect.x + rect.w, cy: rect.y },
                { id: 'sw', cx: rect.x, cy: rect.y + rect.h },
                { id: 'se', cx: rect.x + rect.w, cy: rect.y + rect.h }
            ];
            for (var c = 0; c < corners.length; c++) {
                var co = corners[c];
                if (px >= co.cx - hitHalf && px <= co.cx + hitHalf && py >= co.cy - hitHalf && py <= co.cy + hitHalf) {
                    return { type: 'corner', index: i, corner: co.id };
                }
            }
            if (rect.w > HANDLE_HIT && rect.h > HANDLE_HIT &&
                px >= rect.x + innerMargin && px <= rect.x + rect.w - innerMargin &&
                py >= rect.y + innerMargin && py <= rect.y + rect.h - innerMargin) {
                return { type: 'region', index: i };
            }
        }
        return { type: 'none' };
    }

    function redraw() {
        if (!imgW || !drawW) return;
        ctx.fillStyle = '#e5e7eb';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, offsetX, offsetY, drawW, drawH);
        var hs = HANDLE;
        regions.forEach(function (r) {
            var rect = fromPercent(r);
            ctx.fillStyle = 'rgba(0,0,0,0.5)';
            ctx.fillRect(rect.x, rect.y, rect.w, rect.h);
            ctx.strokeStyle = '#dc2626';
            ctx.lineWidth = 3;
            ctx.strokeRect(rect.x, rect.y, rect.w, rect.h);
            var corners = [
                [rect.x, rect.y],
                [rect.x + rect.w - hs, rect.y],
                [rect.x, rect.y + rect.h - hs],
                [rect.x + rect.w - hs, rect.y + rect.h - hs]
            ];
            corners.forEach(function (xy) {
                ctx.fillStyle = '#dc2626';
                ctx.fillRect(xy[0], xy[1], hs, hs);
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.strokeRect(xy[0], xy[1], hs, hs);
            });
        });
        regionsInput.value = JSON.stringify(regions);
        btnApply.disabled = regions.length === 0;
    }

    function initCanvas() {
        if (!img.width || !img.height) return;
        document.getElementById('unkentlich-load-error').classList.add('hidden');
        imgW = img.width;
        imgH = img.height;
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        var container = document.getElementById('unkentlich-canvas-container');
        var minWorkW = 1200;
        var minWorkH = 900;
        var fromContainerW = container ? container.clientWidth : Math.min(window.innerWidth * 0.96, 2800);
        var fromContainerH = container ? container.clientHeight : Math.min(window.innerHeight * 0.82, 2600);
        var availW = Math.max(fromContainerW, minWorkW);
        var availH = Math.max(fromContainerH, minWorkH);
        scale = Math.min(availW / imgW, availH / imgH, 8);
        drawW = imgW * scale;
        drawH = imgH * scale;
        offsetX = 0;
        offsetY = 0;
        canvas.width = Math.ceil(drawW * dpr);
        canvas.height = Math.ceil(drawH * dpr);
        canvas.style.width = drawW + 'px';
        canvas.style.height = drawH + 'px';
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(dpr, dpr);
        redraw();
    }

    function handleStart(e) {
        e.preventDefault();
        var p = getEventPos(e);
        var hit = hitTest(p.x, p.y);
        if (hit.type === 'corner') {
            isResizing = true;
            resizeRegionIndex = hit.index;
            resizeCorner = hit.corner;
            startX = p.x;
            startY = p.y;
            return;
        }
        if (hit.type === 'region') {
            isMoving = true;
            moveRegionIndex = hit.index;
            var rect = fromPercent(regions[hit.index]);
            moveOffsetX = p.x - rect.x;
            moveOffsetY = p.y - rect.y;
            return;
        }
        startX = p.x;
        startY = p.y;
        isDrawing = true;
    }
    function handleMove(e) {
        var p = getEventPos(e);
        if (isResizing && resizeRegionIndex >= 0) {
            e.preventDefault();
            var r = regions[resizeRegionIndex];
            var minSize = 2;
            var px = (p.x - offsetX) / drawW * 100;
            var py = (p.y - offsetY) / drawH * 100;
            var x2 = r.x + r.w, y2 = r.y + r.h;
            if (resizeCorner === 'se') {
                r.w = Math.max(minSize, Math.min(100 - r.x, px - r.x));
                r.h = Math.max(minSize, Math.min(100 - r.y, py - r.y));
            } else if (resizeCorner === 'nw') {
                r.x = Math.max(0, Math.min(x2 - minSize, px));
                r.y = Math.max(0, Math.min(y2 - minSize, py));
                r.w = Math.max(minSize, x2 - r.x);
                r.h = Math.max(minSize, y2 - r.y);
            } else if (resizeCorner === 'ne') {
                r.y = Math.max(0, Math.min(y2 - minSize, py));
                r.w = Math.max(minSize, Math.min(100 - r.x, px - r.x));
                r.h = Math.max(minSize, y2 - r.y);
            } else if (resizeCorner === 'sw') {
                r.x = Math.max(0, Math.min(x2 - minSize, px));
                r.w = Math.max(minSize, x2 - r.x);
                r.h = Math.max(minSize, Math.min(100 - r.y, py - r.y));
            }
            redraw();
            return;
        }
        if (isMoving && moveRegionIndex >= 0) {
            e.preventDefault();
            var r = regions[moveRegionIndex];
            var nx = (p.x - offsetX - moveOffsetX) / drawW * 100;
            var ny = (p.y - offsetY - moveOffsetY) / drawH * 100;
            r.x = Math.max(0, Math.min(100 - r.w, nx));
            r.y = Math.max(0, Math.min(100 - r.h, ny));
            redraw();
            return;
        }
        if (!isDrawing && !isMoving && !isResizing) {
            var hit = hitTest(p.x, p.y);
            if (hit.type === 'corner') {
                var cur = (hit.corner === 'nw' || hit.corner === 'se') ? 'nwse-resize' : 'nesw-resize';
                canvas.style.cursor = cur;
            } else if (hit.type === 'region') {
                canvas.style.cursor = 'move';
            } else {
                canvas.style.cursor = 'crosshair';
            }
        }
        if (!isDrawing) return;
        e.preventDefault();
        redraw();
        var x = Math.min(startX, p.x), y = Math.min(startY, p.y);
        var w = Math.abs(p.x - startX), h = Math.abs(p.y - startY);
        if (w > 2 && h > 2) {
            ctx.strokeStyle = '#dc2626';
            ctx.lineWidth = 2;
            ctx.setLineDash([5, 5]);
            ctx.strokeRect(x, y, w, h);
            ctx.setLineDash([]);
        }
    }
    function handleEnd(e) {
        if (isResizing) {
            isResizing = false;
            resizeRegionIndex = -1;
            resizeCorner = '';
            redraw();
            return;
        }
        if (isMoving) {
            isMoving = false;
            moveRegionIndex = -1;
            redraw();
            return;
        }
        if (!isDrawing) return;
        e.preventDefault();
        var p = getEventPos(e);
        isDrawing = false;
        var x = Math.min(startX, p.x), y = Math.min(startY, p.y);
        var w = Math.abs(p.x - startX), h = Math.abs(p.y - startY);
        if (w > 5 && h > 5) {
            regions.push(toPercent(x, y, w, h));
        }
        redraw();
    }
    function handleCancel() {
        if (isDrawing) { isDrawing = false; redraw(); }
        if (isResizing) { isResizing = false; resizeRegionIndex = -1; resizeCorner = ''; redraw(); }
        if (isMoving) { isMoving = false; moveRegionIndex = -1; redraw(); }
    }

    canvas.addEventListener('mousedown', handleStart);
    canvas.addEventListener('mousemove', handleMove);
    canvas.addEventListener('mouseup', handleEnd);
    canvas.addEventListener('mouseleave', handleCancel);

    canvas.addEventListener('touchstart', handleStart, { passive: false });
    canvas.addEventListener('touchmove', handleMove, { passive: false });
    canvas.addEventListener('touchend', handleEnd, { passive: false });
    canvas.addEventListener('touchcancel', handleCancel);

    btnClear.addEventListener('click', function () {
        regions = [];
        redraw();
    });

    img.onload = initCanvas;
    img.onerror = function () {
        document.getElementById('unkentlich-load-error').classList.remove('hidden');
    };
    if (img.complete && img.width) initCanvas();
})();
    </script>
@endsection
