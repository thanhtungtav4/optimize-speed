<div class="wrap">
    <h1>Optimize Speed Settings</h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('optimize_speed_group');
        do_settings_sections('optimize-speed');
        submit_button();
        ?>
    </form>

    <hr>

    <h2>Image Optimization Status</h2>
    <div class="card" style="max-width:100%">
        <p><strong>Native Image Optimization</strong> is active.</p>
        <p>Features:</p>
        <ul>
            <li>Native Lazy Loading (loading="lazy")</li>
            <li>LCP Optimization (fetchpriority="high")</li>
            <li>Auto WebP/AVIF generation</li>
        </ul>

        <hr>

        <h3>Regenerate Images</h3>
        <p>Total Images (JPEG/PNG): <strong id="total-count">loading...</strong></p>

        <p>
            <strong>AVIF Status:</strong>
            <?php
            $avif_support = false;
            if (class_exists('Imagick')) {
                $formats = Imagick::queryFormats();
                if (in_array('AVIF', $formats) && in_array('HEIF', $formats)) {
                    $avif_support = true;
                }
            }
            if ($avif_support): ?>
                <span style="color:#00a32a">✅ Active</span>
            <?php else: ?>
                <span style="color:#d63638">❌ Not Supported (Imagick/AVIF missing)</span>
            <?php endif; ?>
            &nbsp;
            <a href="<?php echo wp_nonce_url(add_query_arg('reset_avif_test', '1'), 'reset_avif_test'); ?>"
                class="button button-small">Test Again</a>
            &nbsp;
            <button id="cleanup-avif-btn" class="button button-small"
                style="background:#ff9800;color:#fff;border-color:#f57c00">
                🗑️ Cleanup Bad AVIF
            </button>
        </p>

        <p>
            <button id="start-btn" class="button button-primary button-large">Start Regenerate</button>
            <button id="pause-btn" class="button button-secondary" style="display:none">Pause</button>
            <button id="resume-btn" class="button button-secondary" style="display:none">Resume</button>
        </p>

        <div id="progress-container" style="margin:25px 0;display:none">
            <div style="background:#ddd;height:40px;border-radius:8px;position:relative;overflow:hidden">
                <div id="progress-bar" style="background:#2271b1;width:0%;height:100%"></div>
                <div style="position:absolute;top:10px;left:15px;color:#fff;font-weight:bold">
                    <span id="processed">0</span> / <span id="total">0</span> (<span id="percent">0</span>%)
                </div>
            </div>
        </div>

        <div id="log"
            style="background:#1d2327;color:#0f0;padding:15px;height:200px;overflow-y:auto;font-family:monospace;border:1px solid #333;border-radius:4px;font-size:13px">
            Ready...
        </div>
    </div>
</div>

<script>
    (function () {
        const NONCE_QUEUE = '<?php echo wp_create_nonce('modern_opti_queue'); ?>';
        const NONCE_REGEN = '<?php echo wp_create_nonce('modern_opti_regen'); ?>';
        const NONCE_CLEANUP = '<?php echo wp_create_nonce('modern_opti_cleanup_avif'); ?>';
        const AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';

        let running = false, paused = false, processed = 0, total = 0, queue = [];
        let retries = {};
        const MAX_RETRIES = 2;

        function log(m, c = '#0f0') {
            const l = document.getElementById('log');
            const d = document.createElement('div');
            d.textContent = '[' + new Date().toLocaleTimeString() + '] ' + m;
            d.style.color = c;
            l.appendChild(d);
            l.scrollTop = l.scrollHeight;
        }

        function update() {
            const p = total > 0 ? Math.round((processed / total) * 100) : 0;
            document.getElementById('progress-bar').style.width = p + '%';
            document.getElementById('processed').textContent = processed;
            document.getElementById('percent').textContent = p;
        }

        function processBatch() {
            if (!running || paused || queue.length === 0) {
                if (queue.length === 0 && processed > 0 && running) finish();
                return;
            }
            const batch = queue.splice(0, 1);
            let done = 0;

            batch.forEach(id => {
                fetch(AJAX_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ action: 'modern_opti_regen', id: id, _wpnonce: NONCE_REGEN })
                })
                    .then(r => r.json())
                    .then(d => {
                        done++;
                        if (d && d.success) {
                            processed++;
                            log('✓ Completed ID ' + id, '#0f0');
                            delete retries[id];
                        } else {
                            const msg = (d && d.data ? d.data : 'Unknown error');
                            log('✗ Error ID ' + id + ': ' + msg, '#f55');
                            retries[id] = (retries[id] || 0) + 1;
                            if (retries[id] < MAX_RETRIES) { queue.push(id); } else { processed++; }
                        }
                        update();
                        if (done === batch.length) setTimeout(processBatch, 500);
                    }).catch(err => {
                        done++;
                        log('✗ Network Error ID ' + id, '#fa0');
                        retries[id] = (retries[id] || 0) + 1;
                        if (retries[id] < MAX_RETRIES) { queue.push(id); } else { processed++; }
                        if (done === batch.length) setTimeout(processBatch, 1500);
                    });
            });
        }

        function start() {
            log('Loading image list...');
            fetch(AJAX_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ action: 'modern_opti_queue', _wpnonce: NONCE_QUEUE })
            }).then(r => r.json())
                .then(d => {
                    if (d && d.success) {
                        queue = Array.isArray(d.data.ids) ? d.data.ids : [];
                        total = queue.length;
                        document.getElementById('total-count').textContent = total;
                        document.getElementById('total').textContent = total;
                        document.getElementById('progress-container').style.display = 'block';
                        document.getElementById('start-btn').style.display = 'none';
                        document.getElementById('pause-btn').style.display = 'inline-block';
                        running = true;
                        log('Starting process for ' + total + ' images...');
                        processBatch();
                    } else {
                        log('Error fetching image list!', '#f55');
                    }
                });
        }

        function finish() {
            running = false;
            document.getElementById('pause-btn').style.display = 'none';
            document.getElementById('resume-btn').style.display = 'none';
            document.getElementById('start-btn').style.display = 'inline-block';
            log('ALL DONE!', 'lime');
        }

        document.getElementById('start-btn').onclick = start;
        document.getElementById('pause-btn').onclick = () => {
            paused = true;
            document.getElementById('pause-btn').style.display = 'none';
            document.getElementById('resume-btn').style.display = 'inline-block';
            log('PAUSED', 'orange');
        };
        document.getElementById('resume-btn').onclick = () => {
            paused = false;
            document.getElementById('resume-btn').style.display = 'none';
            document.getElementById('pause-btn').style.display = 'inline-block';
            log('RESUMING...', '#0f0');
            processBatch();
        };

        // Initial count
        fetch(AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ action: 'modern_opti_queue', _wpnonce: NONCE_QUEUE })
        }).then(r => r.json()).then(d => {
            document.getElementById('total-count').textContent = (d && d.success) ? (Array.isArray(d.data.ids) ? d.data.ids.length : 0) : '0';
        });

        document.getElementById('cleanup-avif-btn').onclick = function () {
            if (!confirm('Delete all bad AVIF files (< 100 bytes)?')) return;
            const btn = this; btn.disabled = true;
            btn.textContent = '⏳ Scanning...';
            fetch(AJAX_URL, {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ action: 'modern_opti_cleanup_avif', _wpnonce: NONCE_CLEANUP })
            }).then(r => r.json())
                .then(d => {
                    btn.disabled = false; btn.textContent = '🗑️ Cleanup Bad AVIF';
                    if (d && d.success) { alert('Deleted: ' + d.data.deleted + ' files'); }
                    else { alert('Error!'); }
                });
        };
    })();
</script>