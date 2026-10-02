(() => {
  const root = document.querySelector('[data-practice-root]');
  if (!root) return;

  const editor = Object.fromEntries(['html', 'css', 'js'].map((key) => [key, root.querySelector(`[data-code="${key}"]`)]));
  const preview = root.querySelector('[data-preview]');
  const consoleLines = root.querySelector('[data-console-lines]');
  const status = root.querySelector('[data-save-status]');
  const runLabel = root.querySelector('[data-run-label]');
  const bootstrapToggle = root.querySelector('[data-bootstrap]');
  const bootstrapCss = root.dataset.bootstrapCss;
  const csrf = root.dataset.csrf;
  let saveTimer;
  let currentToken = '';
  let runningAt = 0;
  let bootstrapCssText = '';

  const clearConsole = () => { consoleLines.replaceChildren(); };
  const log = (message, kind = 'log') => {
    if (consoleLines.querySelector('.console-empty')) clearConsole();
    if (consoleLines.children.length >= 100) return;
    const line = document.createElement('div');
    line.className = `console-line ${kind === 'error' ? 'error' : kind === 'warn' ? 'warn' : ''}`;
    line.textContent = message.slice(0, 1200);
    consoleLines.append(line);
  };

  const run = async () => {
    clearConsole();
    currentToken = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    runningAt = Date.now();
    runLabel.textContent = 'Running';
    if (bootstrapToggle.checked && !bootstrapCssText) {
      runLabel.textContent = 'Loading Bootstrap…';
      try {
        const response = await fetch(bootstrapCss, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('Could not load Bootstrap');
        bootstrapCssText = await response.text();
      } catch (_) {
        runLabel.textContent = 'Could not load Bootstrap';
        log('Bootstrap CSS could not be loaded. Check the local course assets.', 'error');
        return;
      }
    }
    const csp = "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data: blob:; font-src data:; connect-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'; navigate-to 'none'";
    const instrumentation = `<script>
      (() => {
        const token = ${JSON.stringify(currentToken)};
        const send = (kind, values) => {
          try { parent.postMessage({ source: 'practice-frame', token, kind, text: values.map(value => {
            if (typeof value === 'string') return value;
            try { return JSON.stringify(value); } catch (_) { return String(value); }
          }).join(' ').slice(0, 1200) }, '*'); } catch (_) {}
        };
        ['log','info','warn','error'].forEach(kind => {
          const original = console[kind].bind(console);
          console[kind] = (...values) => { original(...values); send(kind === 'info' ? 'log' : kind, values); };
        });
        addEventListener('error', event => send('error', [event.message || 'Script error', event.filename, event.lineno].filter(Boolean)));
        addEventListener('unhandledrejection', event => send('error', ['Unhandled promise rejection:', event.reason?.message || event.reason]));
      })();
    <\/script>`;
    const documentText = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="${csp}"><style>${(bootstrapToggle.checked ? bootstrapCssText + '\n' : '') + editor.css.value.replace(/<\/style/gi, '<\\/style')}</style></head><body>${editor.html.value.replace(/<\/body/gi, '&lt;/body').replace(/<\/html/gi, '&lt;/html')}${instrumentation}<script>${editor.js.value.replace(/<\/script/gi, '<\\/script')}<\/script></body></html>`;
    preview.srcdoc = documentText;
  };

  window.addEventListener('message', (event) => {
    if (event.source !== preview.contentWindow || event.data?.source !== 'practice-frame' || event.data?.token !== currentToken) return;
    log(event.data.text || '', event.data.kind);
    if (event.data.kind === 'error') runLabel.textContent = 'Check the console';
  });
  preview.addEventListener('load', () => {
    if (!currentToken) return;
    runLabel.textContent = 'Ran just now';
    window.setTimeout(() => { if (Date.now() - runningAt >= 5000) runLabel.textContent = 'Ready to run again'; }, 5000);
  });

  const save = async () => {
    status.textContent = 'Saving…';
    try {
      const response = await fetch(root.dataset.saveUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: JSON.stringify({ workspace_id: Number(root.dataset.workspaceId), html_content: editor.html.value, css_content: editor.css.value, js_content: editor.js.value, bootstrap_enabled: bootstrapToggle.checked }),
      });
      if (!response.ok) throw new Error('save failed');
      status.textContent = 'Saved for 12 hours';
    } catch (_) { status.textContent = 'Could not save. Check your connection.'; }
  };
  const queueSave = () => { status.textContent = 'Unsaved changes'; clearTimeout(saveTimer); saveTimer = setTimeout(save, 900); };

  root.querySelectorAll('[data-tab]').forEach((tab) => tab.addEventListener('click', () => {
    root.querySelectorAll('[data-tab]').forEach((other) => { other.classList.toggle('active', other === tab); other.setAttribute('aria-selected', other === tab ? 'true' : 'false'); });
    root.querySelectorAll('[data-panel]').forEach((panel) => { panel.hidden = panel.dataset.panel !== tab.dataset.tab; panel.classList.toggle('active', panel.dataset.panel === tab.dataset.tab); });
  }));
  Object.values(editor).forEach((field) => field.addEventListener('input', queueSave));
  bootstrapToggle.addEventListener('change', queueSave);
  root.querySelector('[data-run]').addEventListener('click', run);
  root.querySelector('[data-clear-console]').addEventListener('click', () => { clearConsole(); consoleLines.innerHTML = '<span class="console-empty">JavaScript messages and errors will appear here.</span>'; });
  root.querySelector('[data-confirm-clear]').addEventListener('submit', (event) => { if (!window.confirm('Open a separate fresh practice? This workspace will remain available until it expires.')) event.preventDefault(); });
  root.querySelector('[data-workspace-switch]')?.addEventListener('change', (event) => { window.location.assign(event.target.value); });

  root.querySelector('#practice-files').addEventListener('change', async (event) => {
    for (const file of event.target.files || []) {
      const extension = file.name.split('.').pop().toLowerCase();
      const key = extension === 'html' || extension === 'htm' ? 'html' : extension === 'css' ? 'css' : extension === 'js' ? 'js' : null;
      if (!key) continue;
      editor[key].value = await file.text();
      root.querySelector(`[data-tab="${key}"]`).click();
    }
    event.target.value = '';
    queueSave();
  });

  const crc32 = (bytes) => {
    let crc = -1;
    for (const byte of bytes) {
      crc ^= byte;
      for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
    return (crc ^ -1) >>> 0;
  };
  const createZip = (files) => {
    const encoder = new TextEncoder();
    const local = [];
    const central = [];
    let offset = 0;
    for (const [name, content] of Object.entries(files)) {
      const filename = encoder.encode(name);
      const bytes = typeof content === 'string' ? encoder.encode(content) : content;
      const crc = crc32(bytes);
      const header = new Uint8Array(30 + filename.length);
      const view = new DataView(header.buffer);
      view.setUint32(0, 0x04034b50, true); view.setUint16(4, 20, true);
      view.setUint32(14, crc, true); view.setUint32(18, bytes.length, true); view.setUint32(22, bytes.length, true);
      view.setUint16(26, filename.length, true); header.set(filename, 30);
      local.push(header, bytes);

      const record = new Uint8Array(46 + filename.length);
      const centralView = new DataView(record.buffer);
      centralView.setUint32(0, 0x02014b50, true); centralView.setUint16(4, 20, true); centralView.setUint16(6, 20, true);
      centralView.setUint32(16, crc, true); centralView.setUint32(20, bytes.length, true); centralView.setUint32(24, bytes.length, true);
      centralView.setUint16(28, filename.length, true); centralView.setUint32(42, offset, true); record.set(filename, 46);
      central.push(record);
      offset += header.length + bytes.length;
    }
    const centralSize = central.reduce((sum, item) => sum + item.length, 0);
    const end = new Uint8Array(22);
    const endView = new DataView(end.buffer);
    endView.setUint32(0, 0x06054b50, true); endView.setUint16(8, central.length, true); endView.setUint16(10, central.length, true);
    endView.setUint32(12, centralSize, true); endView.setUint32(16, offset, true);
    return new Blob([...local, ...central, end], { type: 'application/zip' });
  };
  root.querySelector('[data-download]').addEventListener('click', async () => {
    const files = { 'index.html': `<!doctype html>\n<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${bootstrapToggle.checked ? '<link rel="stylesheet" href="css/bootstrap.min.css">' : ''}<link rel="stylesheet" href="style.css"><title>My practice page</title></head><body>${editor.html.value}<script src="script.js"><\/script></body></html>`, 'style.css': editor.css.value, 'script.js': editor.js.value };
    if (bootstrapToggle.checked) {
      status.textContent = 'Preparing your project…';
      try { const response = await fetch(bootstrapCss, { credentials: 'same-origin' }); if (!response.ok) throw new Error(); files['css/bootstrap.min.css'] = await response.text(); }
      catch (_) { status.textContent = 'Could not include Bootstrap. Try again.'; return; }
    }
    const url = URL.createObjectURL(createZip(files));
    const link = Object.assign(document.createElement('a'), { href: url, download: 'my-practice-project.zip' });
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    status.textContent = 'Project downloaded';
  });

  run();
})();
