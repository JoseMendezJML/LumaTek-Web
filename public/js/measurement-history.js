(function (root) {
    'use strict';
    const time = row => Date.parse(row.recorded_at_iso || row.recorded_at?.replace(' ', 'T'));
    function series(rows) {
        const groups = new Map();
        for (const row of rows) {
            const unit = row.unit ?? row.sensor?.unit ?? 'unknown';
            const key = JSON.stringify([row.sensor_id ?? row.sensor?.id, unit, row.provenance, row.calibration_snapshot ?? null]);
            if (!groups.has(key)) groups.set(key, { unit, label: `${row.sensor?.name ?? row.sensor_id} · ${unit} · ${row.source_label ?? 'Origen no verificado'}`, points: [] });
            groups.get(key).points.push(row);
        }
        return [...groups.values()].map(group => {
            group.points.sort((a, b) => time(a) - time(b));
            group.segments = [];
            for (const row of group.points) {
                const segment = group.segments.at(-1);
                const previous = segment?.at(-1);
                const gap = previous && time(row) - time(previous) > Math.max(300, 3 * (row.interval_snapshot ?? 60)) * 1000;
                if (!segment || gap || row.break_before) group.segments.push([row]);
                else segment.push(row);
            }
            return group;
        });
    }
    function draw(canvas, rows, width, height, period = {}) {
        const groups = series(rows);
        const units = [...new Set(groups.map(group => group.unit))];
        const ratio = root.devicePixelRatio || 1;
        width = Math.max(240, width);
        const context = canvas.getContext('2d');
        const textWidth = text => context.measureText ? context.measureText(text).width : text.length * 7;
        // Wrap even unbroken sensor names; every legend has its own measured space.
        function wrap(text, available) {
            const lines = [];
            let line = '';
            for (const char of text) {
                if (line && textWidth(line + char) > available) { lines.push(line.trim()); line = ''; }
                line += char;
            }
            if (line) lines.push(line.trim());
            return lines;
        }
        context.font = '12px Arial';
        const panels = units.map(unit => {
            const selected = groups.filter(group => group.unit === unit);
            const legends = selected.map((group, index) => ({ group, index, lines: wrap(group.label, width - 48) }));
            const legendHeight = legends.reduce((sum, legend) => sum + legend.lines.length * 17 + 10, 0);
            return { unit, selected, legends, legendHeight, height: 285 + legendHeight };
        });
        height = Math.max(height, panels.reduce((sum, panel) => sum + panel.height, 0));
        canvas.width = width * ratio; canvas.height = height * ratio;
        canvas.style.width = `${width}px`; canvas.style.height = `${height}px`;
        context.setTransform(ratio, 0, 0, ratio, 0, 0); context.clearRect(0, 0, width, height);
        context.font = '12px Arial';
        const dates = rows.map(time).filter(Number.isFinite);
        const start = period.start ? Date.parse(period.start) : Math.min(...dates);
        const end = Math.max(start + 1000, period.end ? Date.parse(period.end) : Math.max(...dates));
        const colors = ['#176136', '#3776a3', '#9c5700', '#873c91', '#be3742'];
        let base = 0;
        panels.forEach(({ unit, selected, legends, legendHeight, height: panelHeight }) => {
            const values = selected.flatMap(group => group.points.map(point => Number(point.value)));
            const low = Math.min(...values), high = Math.max(low + 1, ...values);
            const left = Math.min(width * .4, Math.max(64, textWidth(high.toFixed(2)), textWidth(low.toFixed(2))) + 12);
            const right = width - 16;
            const top = base + 68 + legendHeight, bottom = top + 140;
            const x = point => left + (time(point) - start) / (end - start) * (right - left);
            const y = point => bottom - (Number(point.value) - low) / (high - low) * (bottom - top);
            context.fillStyle = '#314239'; context.font = 'bold 13px Arial';
            context.fillText(`Unidad: ${unit}`, 12, base + 20);
            context.font = '11px Arial'; context.fillStyle = '#637269';
            context.fillText('Puntos originales / huecos sin interpolar', 12, base + 39);
            context.font = '12px Arial';
            let legendY = base + 61;
            legends.forEach(({ group, index, lines }) => {
                context.strokeStyle = colors[index % colors.length]; context.beginPath();
                context.moveTo(12, legendY - 4); context.lineTo(26, legendY - 4); context.stroke();
                context.fillStyle = '#314239';
                lines.forEach(line => { context.fillText(line, 34, legendY); legendY += 17; });
                legendY += 10;
            });
            context.strokeStyle = '#dce5de'; context.beginPath(); context.moveTo(left, top); context.lineTo(left, bottom); context.lineTo(right, bottom); context.stroke();
            context.fillStyle = '#55645b'; context.textAlign = 'right';
            context.fillText(high.toFixed(2), left - 10, top + 4);
            context.fillText(low.toFixed(2), left - 10, bottom);
            context.textAlign = 'left';
            const dateLabel = value => new Date(value).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            context.font = '11px Arial';
            context.fillText(`Desde: ${dateLabel(start)}`, 12, bottom + 25);
            context.fillText(`Hasta: ${dateLabel(end)}`, 12, bottom + 43);
            selected.forEach((group, index) => {
                context.strokeStyle = colors[index % colors.length]; context.fillStyle = context.strokeStyle;
                for (const segment of group.segments) {
                    context.beginPath();
                    segment.forEach((point, i) => i ? context.lineTo(x(point), y(point)) : context.moveTo(x(point), y(point)));
                    context.stroke();
                    segment.forEach(point => { context.beginPath(); context.arc(x(point), y(point), 2, 0, Math.PI * 2); context.fill(); });
                }
            });
            base += panelHeight;
        });
        return groups;
    }
    function paginationText(pagination) {
        const { page = 1, last_page = 1, total = 0, from = null, to = null } = pagination ?? {};
        const range = from === null || to === null ? 'Sin registros' : `Registros ${from}–${to} de ${total}`;
        return `Página ${page} de ${last_page} · ${range} · Registros paginados; estadísticas de todo el período`;
    }
    function metricText(metric, field) {
        const groups = metric?.by_unit ?? (metric ? [metric] : []);
        return groups.map(group => `${group[field] == null ? '—' : Number(group[field]).toFixed(2)} ${group.unit ?? 'unknown'}`).join(' | ') || '—';
    }
    root.LumaMeasurementHistory = { series, draw, metricText, paginationText };
})(typeof window === 'undefined' ? globalThis : window);
