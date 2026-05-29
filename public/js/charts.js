/**
 * Attendify — Lightweight Canvas Charts
 */

class MiniChart {
    constructor(canvas, options = {}) {
        this.canvas = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.options = { padding: 40, barRadius: 6, lineWidth: 3, dotRadius: 5, colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#d946ef'], gridColor: 'rgba(255,255,255,0.05)', labelColor: '#64748b', fontFamily: 'Inter, sans-serif', animate: true, ...options };
        this.resize();
        window.addEventListener('resize', () => this.resize());
    }

    resize() {
        const rect = this.canvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        this.canvas.width = rect.width * dpr;
        this.canvas.height = (this.options.height || 250) * dpr;
        this.canvas.style.width = rect.width + 'px';
        this.canvas.style.height = (this.options.height || 250) + 'px';
        this.ctx.scale(dpr, dpr);
        this.w = rect.width;
        this.h = this.options.height || 250;
    }

    clear() { this.ctx.clearRect(0, 0, this.w, this.h); }

    drawBar(labels, datasets) {
        this.clear();
        const { padding, barRadius, colors, gridColor, labelColor, fontFamily } = this.options;
        const chartW = this.w - padding * 2, chartH = this.h - padding * 2;
        const maxVal = Math.max(...datasets.flatMap(d => d.data), 1);
        const groupW = chartW / labels.length;
        const barW = Math.min(groupW * 0.6 / datasets.length, 32);
        const ctx = this.ctx;

        // Grid lines
        ctx.strokeStyle = gridColor; ctx.lineWidth = 1;
        for (let i = 0; i <= 4; i++) {
            const y = padding + (chartH / 4) * i;
            ctx.beginPath(); ctx.moveTo(padding, y); ctx.lineTo(this.w - padding, y); ctx.stroke();
            ctx.fillStyle = labelColor; ctx.font = `11px ${fontFamily}`; ctx.textAlign = 'right';
            ctx.fillText(Math.round(maxVal - (maxVal / 4) * i), padding - 8, y + 4);
        }

        // Bars
        labels.forEach((label, i) => {
            const x = padding + groupW * i + groupW / 2;
            datasets.forEach((ds, di) => {
                const barX = x - (datasets.length * barW) / 2 + di * barW;
                const barH = (ds.data[i] / maxVal) * chartH;
                const barY = padding + chartH - barH;
                ctx.fillStyle = colors[di % colors.length];
                ctx.beginPath();
                ctx.roundRect(barX, barY, barW - 2, barH, [barRadius, barRadius, 0, 0]);
                ctx.fill();
            });
            ctx.fillStyle = labelColor; ctx.font = `11px ${fontFamily}`; ctx.textAlign = 'center';
            ctx.fillText(label, x, this.h - padding / 3);
        });
    }

    drawLine(labels, datasets) {
        this.clear();
        const { padding, lineWidth, dotRadius, colors, gridColor, labelColor, fontFamily } = this.options;
        const chartW = this.w - padding * 2, chartH = this.h - padding * 2;
        const maxVal = Math.max(...datasets.flatMap(d => d.data), 1);
        const ctx = this.ctx;

        // Grid
        ctx.strokeStyle = gridColor; ctx.lineWidth = 1;
        for (let i = 0; i <= 4; i++) {
            const y = padding + (chartH / 4) * i;
            ctx.beginPath(); ctx.moveTo(padding, y); ctx.lineTo(this.w - padding, y); ctx.stroke();
            ctx.fillStyle = labelColor; ctx.font = `11px ${fontFamily}`; ctx.textAlign = 'right';
            ctx.fillText(Math.round(maxVal - (maxVal / 4) * i), padding - 8, y + 4);
        }

        // Lines
        datasets.forEach((ds, di) => {
            const color = colors[di % colors.length];
            ctx.strokeStyle = color; ctx.lineWidth = lineWidth; ctx.lineJoin = 'round';
            ctx.beginPath();
            ds.data.forEach((val, i) => {
                const x = padding + (chartW / (labels.length - 1)) * i;
                const y = padding + chartH - (val / maxVal) * chartH;
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            ctx.stroke();
            // Dots
            ds.data.forEach((val, i) => {
                const x = padding + (chartW / (labels.length - 1)) * i;
                const y = padding + chartH - (val / maxVal) * chartH;
                ctx.fillStyle = color; ctx.beginPath(); ctx.arc(x, y, dotRadius, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = '#06080f'; ctx.beginPath(); ctx.arc(x, y, dotRadius - 2, 0, Math.PI * 2); ctx.fill();
            });
        });

        // Labels
        labels.forEach((label, i) => {
            const x = padding + (chartW / (labels.length - 1)) * i;
            ctx.fillStyle = labelColor; ctx.font = `11px ${fontFamily}`; ctx.textAlign = 'center';
            ctx.fillText(label, x, this.h - padding / 3);
        });
    }

    drawDonut(data, labels) {
        this.clear();
        const { colors, labelColor, fontFamily } = this.options;
        const ctx = this.ctx;
        const cx = this.w / 2, cy = this.h / 2;
        const radius = Math.min(cx, cy) - 40;
        const innerRadius = radius * 0.6;
        const total = data.reduce((a, b) => a + b, 0) || 1;
        let startAngle = -Math.PI / 2;

        data.forEach((val, i) => {
            const sliceAngle = (val / total) * Math.PI * 2;
            ctx.fillStyle = colors[i % colors.length];
            ctx.beginPath(); ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
            ctx.closePath(); ctx.fill();
            startAngle += sliceAngle;
        });

        // Inner circle
        ctx.fillStyle = '#06080f';
        ctx.beginPath(); ctx.arc(cx, cy, innerRadius, 0, Math.PI * 2); ctx.fill();

        // Center text
        ctx.fillStyle = '#f1f5f9'; ctx.font = `bold 24px ${fontFamily}`; ctx.textAlign = 'center';
        ctx.fillText(total, cx, cy + 4);
        ctx.fillStyle = labelColor; ctx.font = `12px ${fontFamily}`;
        ctx.fillText('Total', cx, cy + 22);

        // Legend
        const legendY = this.h - 20;
        const legendW = labels.length * 90;
        const startX = (this.w - legendW) / 2;
        labels.forEach((label, i) => {
            const x = startX + i * 90;
            ctx.fillStyle = colors[i % colors.length];
            ctx.fillRect(x, legendY - 8, 10, 10);
            ctx.fillStyle = labelColor; ctx.font = `11px ${fontFamily}`; ctx.textAlign = 'left';
            ctx.fillText(`${label} (${data[i]})`, x + 14, legendY);
        });
    }
}

window.MiniChart = MiniChart;
