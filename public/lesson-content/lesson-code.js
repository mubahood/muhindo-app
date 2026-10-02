(() => {
  const isTeacher = document.documentElement.dataset.lessonTeacher === 'true';
  const languageNames = { html: 'HTML', xml: 'HTML', css: 'CSS', javascript: 'JavaScript', js: 'JavaScript', json: 'JSON', bash: 'Terminal', plaintext: 'Text' };

  function languageFor(pre, code) {
    const declared = [...code.classList].find((name) => name.startsWith('language-'))?.slice(9);
    if (declared) return declared;
    const label = pre.previousElementSibling?.textContent?.trim().toLowerCase();
    const map = { html: 'xml', css: 'css', javascript: 'javascript', js: 'javascript', json: 'json', 'command line': 'bash', terminal: 'bash' };
    const name = map[label];
    if (name) code.classList.add(`language-${name}`);
    return name;
  }

  function practiceLanguage(language) {
    if (['html', 'xml'].includes(language)) return 'html';
    if (language === 'css') return 'css';
    if (['javascript', 'js'].includes(language)) return 'javascript';
    return null;
  }

  function exampleHtml() {
    const section = [...document.querySelectorAll('main .lesson-section')].find((candidate) => {
      const heading = candidate.querySelector(':scope > h2');
      return heading?.textContent.trim().toLowerCase() === 'example';
    });
    if (!section) return '<main><h1>Practice example</h1><p>Change the code, then run it again.</p></main>';
    const copy = section.cloneNode(true);
    copy.querySelectorAll('script,iframe,object,embed').forEach((node) => node.remove());
    return copy.innerHTML;
  }

  function addButton(label, title, action) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'lesson-code-button';
    button.textContent = label;
    button.title = title;
    button.setAttribute('aria-label', title);
    button.addEventListener('click', action);
    return button;
  }

  async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return;
      } catch {
        // Older browsers and sandboxed previews may deny the Clipboard API.
      }
    }
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;left:-9999px;top:0';
    document.body.appendChild(field);
    field.select();
    document.execCommand('copy');
    field.remove();
  }

  document.querySelectorAll('pre').forEach((pre) => {
    const code = pre.querySelector('code');
    if (!code || pre.parentElement?.classList.contains('lesson-code-block')) return;
    const language = languageFor(pre, code);
    if (window.hljs) window.hljs.highlightElement(code);

    const block = document.createElement('section');
    block.className = 'lesson-code-block';
    block.setAttribute('aria-label', `${languageNames[language] || language || 'Code'} example`);
    pre.before(block);

    const tools = document.createElement('div');
    tools.className = 'lesson-code-tools';
    const badge = document.createElement('span');
    badge.className = 'lesson-code-language';
    badge.textContent = languageNames[language] || language || 'Code';
    tools.appendChild(badge);

    const hint = document.createElement('span');
    hint.className = 'lesson-code-hint';
    hint.textContent = isTeacher ? 'Changes are temporary. Copy to keep them.' : 'Select, copy and try this code.';
    tools.appendChild(hint);

    const copy = addButton('⧉ Copy', 'Copy this code', async () => {
      try {
        await copyText(code.textContent);
        copy.textContent = '✓ Copied';
        window.setTimeout(() => { copy.textContent = '⧉ Copy'; }, 1500);
      } catch {
        copy.textContent = 'Select code to copy';
      }
    });
    tools.appendChild(copy);

    const targetLanguage = practiceLanguage(language);
    if (targetLanguage && window.parent !== window) {
      const practice = addButton('↗ Practice', `Open this ${languageNames[language] || 'code'} snippet in the code playground`, () => {
        // Open synchronously in the student's click. The parent must first save
        // the snippet, so opening only after its response would trigger blockers.
        const practiceTab = window.open('about:blank', '_blank');
        if (!practiceTab) {
          practice.textContent = 'Allow pop-ups to practise';
          window.setTimeout(() => { practice.textContent = '↗ Practice'; }, 2500);
          return;
        }
        practiceTab.opener = null;
        const requestId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        practice.disabled = true;
        practice.textContent = 'Opening…';
        window.parent.postMessage({
          type: 'lesson-code:open-in-practice', requestId, language: targetLanguage,
          code: code.textContent, exampleHtml: exampleHtml(),
        }, '*');
        window.setTimeout(() => {
          if (practice.disabled) {
            practice.disabled = false;
            practice.textContent = '↗ Practice';
            practiceTab.close();
          }
        }, 12000);
        const onResult = (event) => {
          if (event.source !== window.parent || event.data?.type !== 'lesson-code:practice-result' || event.data?.requestId !== requestId) return;
          window.removeEventListener('message', onResult);
          if (event.data.success && typeof event.data.url === 'string') {
            practiceTab.location.href = event.data.url;
            return;
          }
          practiceTab.close();
          practice.disabled = false;
          practice.textContent = 'Try again';
          window.setTimeout(() => { practice.textContent = '↗ Practice'; }, 2000);
        };
        window.addEventListener('message', onResult);
      });
      tools.appendChild(practice);
    }

    if (isTeacher) {
      let original = '';
      let editing = false;
      const reset = addButton('Reset', 'Restore the original example', () => {
        code.textContent = original;
        if (window.hljs) window.hljs.highlightElement(code);
      });
      reset.hidden = true;
      const edit = addButton('✎ Edit', 'Edit this code in the teaching view', () => {
        editing = !editing;
        if (editing) {
          original = code.textContent;
          code.contentEditable = 'true';
          code.spellcheck = false;
          block.classList.add('is-editing');
          edit.textContent = 'Done';
          reset.hidden = false;
          code.focus();
        } else {
          code.contentEditable = 'false';
          block.classList.remove('is-editing');
          edit.textContent = '✎ Edit';
          reset.hidden = true;
          if (window.hljs) window.hljs.highlightElement(code);
        }
      });
      tools.append(reset, edit);
    }

    block.append(tools, pre);
  });
})();
