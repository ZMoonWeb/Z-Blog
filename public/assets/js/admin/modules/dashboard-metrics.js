// 服务器子区块折叠/展开（系统负载、详细信息各自独立）
function initServerCollapse() {
    document.querySelectorAll('[data-collapse]').forEach((block) => {
        const toggle = block.querySelector('[data-collapse-toggle]');
        if (!toggle) return;
        // 初始状态：info 默认收起，load 默认展开
        const initial = block.getAttribute('data-collapse');
        const startCollapsed = initial === 'info';
        block.setAttribute('data-collapsed', startCollapsed ? 'true' : 'false');
        toggle.setAttribute('aria-expanded', startCollapsed ? 'false' : 'true');

        toggle.addEventListener('click', () => {
            const collapsed = block.getAttribute('data-collapsed') === 'true';
            const next = !collapsed;
            block.setAttribute('data-collapsed', next ? 'true' : 'false');
            toggle.setAttribute('aria-expanded', next ? 'false' : 'true');
        });
    });
}

// 每 1 秒轮询服务器指标，更新圆环 + 负载折线图
function initServerMetrics() {
    const card = document.querySelector('[data-server-card]');
    if (!card) return;

    const ringEls = {
        cpu: { ring: card.querySelector('[data-ring="cpu"]') },
        memory: { ring: card.querySelector('[data-ring="memory"]') },
        disk: { ring: card.querySelector('[data-ring="disk"]') },
    };

    const loadNow = card.querySelector('[data-load-now]');
    const loadPath = card.querySelector('[data-load-path]');
    const loadM1 = card.querySelector('[data-load-m1]');
    const loadM5 = card.querySelector('[data-load-m5]');
    const loadM15 = card.querySelector('[data-load-m15]');

    const loadHistory = [];
    const MAX_POINTS = 30;

    // 颜色分级：< 50 深绿 | 50-69 浅绿 | 70-79 黄 | 80-89 橙 | >= 90 红
    const colorFor = (p) => {
        if (p === null || p === undefined) return 'var(--admin-text-muted)';
        if (p >= 90) return '#dc2626';
        if (p >= 80) return '#ea580c';
        if (p >= 70) return '#ca8a04';
        if (p >= 50) return '#65a30d';
        return '#15803d';
    };

    const updateRing = (key, percent, sub) => {
        const r = ringEls[key];
        if (!r || !r.ring) return;
        const prog = r.ring.querySelector('.dash-ring-prog');
        const pctEl = r.ring.querySelector('[data-ring-pct]');
        const subEl = r.ring.querySelector('[data-ring-sub]');
        const p = percent !== null && percent !== undefined ? Math.max(0, Math.min(100, percent)) : 0;
        const circ = parseFloat(prog.getAttribute('data-circumference')) || (2 * Math.PI * 42);
        prog.setAttribute('stroke-dashoffset', String(circ * (1 - p / 100)));
        prog.setAttribute('stroke', colorFor(percent));
        if (pctEl) pctEl.textContent = (percent !== null && percent !== undefined) ? percent + '%' : '—';
        if (subEl && sub !== undefined) subEl.textContent = sub;
    };

    const drawLoad = (load) => {
        if (!load || !loadPath) return;
        loadHistory.push(load.m1);
        if (loadHistory.length > MAX_POINTS) loadHistory.shift();

        const maxVal = Math.max(1, ...loadHistory);
        const w = 300, h = 80, pad = 6;
        const stepX = loadHistory.length > 1 ? (w - pad * 2) / (MAX_POINTS - 1) : 0;
        const points = [];
        loadHistory.forEach((v, i) => {
            const x = pad + i * stepX;
            const y = h - pad - (v / maxVal) * (h - pad * 2);
            points.push((i === 0 ? 'M' : 'L') + x.toFixed(1) + ',' + y.toFixed(1));
        });
        loadPath.setAttribute('d', points.join(' '));

        if (loadNow) loadNow.textContent = String(load.m1);
        if (loadM1) loadM1.textContent = '1m: ' + load.m1;
        if (loadM5) loadM5.textContent = '5m: ' + load.m5;
        if (loadM15) loadM15.textContent = '15m: ' + load.m15;
    };

    let metricsRequestRunning = false;
    const fetchOnce = () => {
        if (metricsRequestRunning) return;
        metricsRequestRunning = true;

        fetch('/admin/api/server-metrics', {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...adminCsrfHeaders(),
            },
        })
            .then((res) => res.ok ? res.json() : Promise.reject())
            .then((data) => {
                updateRing('cpu', data.cpu.percent, (data.cpu.cores || 0) + ' 核');
                updateRing('memory', data.memory.percent, String(data.memory.used || '0') + ' / ' + String(data.memory.total || '0'));
                updateRing('disk', data.disk.percent, String(data.disk.used || '0') + ' / ' + String(data.disk.total || '0'));
                if (data.load) drawLoad(data.load);
            })
            .catch(() => {})
            .finally(() => {
                metricsRequestRunning = false;
            });
    };

    // 首次延迟 1s 再开始，避免和页面加载争抢
    setTimeout(fetchOnce, 1000);
    setInterval(fetchOnce, 1000);
}

// 近 7 天趋势折线图：鼠标悬停/点击显示当天数量
function initTrendChart() {
    const body = document.querySelector('[data-trend-chart]');
    if (!body) return;
    const svg = body.querySelector('.dash-chart');
    const tooltip = body.querySelector('[data-trend-tooltip]');
    if (!svg || !tooltip) return;

    let data = [];
    try {
        data = JSON.parse(body.getAttribute('data-trend-json') || '[]');
    } catch (e) { return; }
    if (!data.length) return;

    const series = [
        { key: 'posts', color: '#111827', label: '文章' },
        { key: 'comments', color: '#6366f1', label: '评论' },
        { key: 'likes', color: '#ec4899', label: '点赞' },
    ];

    // 预渲染每个数据点的圆点（透明，hover 时高亮）
    const vb = svg.viewBox.baseVal;
    const padL = 44, padR = 16, padT = 20, padB = 34;
    const plotW = vb.width - padL - padR;
    const plotH = vb.height - padT - padB;
    const stepX = data.length > 1 ? plotW / (data.length - 1) : plotW;
    const maxV = Math.max(4, Math.ceil(Math.max(...data.flatMap(d => [d.posts, d.comments, d.likes])) * 1.15));
    const yOf = (v) => padT + plotH - (v / maxV) * plotH;

    const pointsLayer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    const dots = [];
    series.forEach((s) => {
        data.forEach((d, i) => {
            const x = padL + i * stepX;
            const y = yOf(d[s.key]);
            const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', x.toFixed(1));
            c.setAttribute('cy', y.toFixed(1));
            c.setAttribute('r', '3.5');
            c.setAttribute('fill', s.color);
            c.setAttribute('stroke', 'var(--admin-surface)');
            c.setAttribute('stroke-width', '1.5');
            c.setAttribute('opacity', '0');
            c.style.transition = 'opacity 0.15s ease, r 0.15s ease';
            pointsLayer.appendChild(c);
            dots.push({ el: c, si: series.indexOf(s), di: i });
        });
    });
    svg.appendChild(pointsLayer);

    // 高亮指示线
    const guide = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    guide.setAttribute('x1', '0'); guide.setAttribute('y1', padT);
    guide.setAttribute('x2', '0'); guide.setAttribute('y2', padT + plotH);
    guide.setAttribute('stroke', 'currentColor');
    guide.setAttribute('stroke-width', '1');
    guide.setAttribute('stroke-dasharray', '3 3');
    guide.setAttribute('opacity', '0');
    svg.appendChild(guide);

    let activeIdx = -1;

    const showAt = (idx) => {
        if (idx < 0 || idx >= data.length) return;
        activeIdx = idx;
        const x = padL + idx * stepX;
        guide.setAttribute('x1', x.toFixed(1));
        guide.setAttribute('x2', x.toFixed(1));
        guide.setAttribute('opacity', '0.35');

        dots.forEach((dt) => {
            if (dt.di === idx) {
                dt.el.setAttribute('opacity', '1');
                dt.el.setAttribute('r', '4.5');
            } else {
                dt.el.setAttribute('opacity', '0');
                dt.el.setAttribute('r', '3.5');
            }
        });

        const d = data[idx];
        let rows = '';
        series.forEach((s) => {
            rows += '<div class="dash-chart-tooltip-row">'
                + '<span class="dash-chart-tooltip-label"><i style="background:' + s.color + '"></i>' + s.label + '</span>'
                + '<span class="dash-chart-tooltip-val">' + d[s.key] + '</span>'
                + '</div>';
        });
        tooltip.innerHTML = '<div class="dash-chart-tooltip-title">' + d.label + '</div>' + rows;
        tooltip.hidden = false;

        // 定位 tooltip（基于 SVG 显示尺寸换算）
        const rect = svg.getBoundingClientRect();
        const scaleX = rect.width / vb.width;
        const scaleY = rect.height / vb.height;
        const tipX = x * scaleX;
        const tipY = padT * scaleY;

        tooltip.style.left = tipX + 'px';
        tooltip.style.top = tipY + 'px';
        tooltip.hidden = false;

        // 边缘检测：tooltip 显示后根据实际宽度判断是否超出容器，加 class 调整偏移
        const bodyRect = body.getBoundingClientRect();
        const tipRect = tooltip.getBoundingClientRect();
        const overflowsRight = (tipRect.right > bodyRect.right - 4);
        const overflowsLeft = (tipRect.left < bodyRect.left + 4);
        tooltip.classList.toggle('is-edge-right', overflowsRight);
        tooltip.classList.toggle('is-edge-left', overflowsLeft);
    };

    const hide = () => {
        activeIdx = -1;
        guide.setAttribute('opacity', '0');
        dots.forEach((dt) => { dt.el.setAttribute('opacity', '0'); });
        tooltip.hidden = true;
    };

    const idxFromEvent = (e) => {
        const rect = svg.getBoundingClientRect();
        const px = (e.clientX - rect.left) / rect.width * vb.width;
        // 找最近的点
        let best = 0, bestDist = Infinity;
        for (let i = 0; i < data.length; i++) {
            const x = padL + i * stepX;
            const dist = Math.abs(px - x);
            if (dist < bestDist) { bestDist = dist; best = i; }
        }
        return best;
    };

    svg.addEventListener('mousemove', (e) => showAt(idxFromEvent(e)));
    svg.addEventListener('mouseleave', hide);
    svg.addEventListener('click', (e) => showAt(idxFromEvent(e)));
}

initServerMetrics();
initServerCollapse();
initTrendChart();
