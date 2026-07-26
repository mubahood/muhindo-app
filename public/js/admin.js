/* ================================================================
   ONYX Legal — Admin JS
   Stack: jQuery 3.7 · SweetAlert2 · Select2 · Flatpickr
   Globals: ONYX_CONFIG  (set by admin layout)
   ================================================================ */

(function ($) {
  'use strict';

  const CFG   = window.ONYX_CONFIG || {};
  const API   = CFG.api  || '/admin/api';
  const TOKEN = CFG.token || $('meta[name="csrf-token"]').attr('content') || '';

  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': TOKEN, Accept: 'application/json' } });

  /* ================================================================
     TOAST
  ================================================================ */
  const Toast = {
    show(msg, type = 'success', dur = 3800) {
      const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
      const $t = $(`<div class="ox-toast ${type}"><i class="fas ${icons[type]||icons.success}"></i><span>${msg}</span></div>`);
      $('#oxToastContainer').append($t);
      if (dur > 0) setTimeout(() => { $t.addClass('out'); setTimeout(() => $t.remove(), 240); }, dur);
    },
    success(m) { this.show(m, 'success'); },
    error(m)   { this.show(m, 'error', 6000); },
    warning(m) { this.show(m, 'warning'); },
    info(m)    { this.show(m, 'info'); },
  };

  /* ================================================================
     DRAWER (right slide-in panel — case/client details)
  ================================================================ */
  const Drawer = {
    open(title, html) {
      $('#oxDrawerTitle').text(title);
      $('#oxDrawerBody').html(html || '<div class="ox-spinner-wrap"><div class="ox-spinner"></div></div>');
      $('#oxDrawerOverlay').addClass('open');
      $('#oxDrawer').addClass('open');
      $('body').css('overflow', 'hidden');
    },
    setContent(html) {
      $('#oxDrawerBody').html(html);
    },
    setTitle(t) {
      $('#oxDrawerTitle').text(t);
    },
    close() {
      $('#oxDrawerOverlay, #oxDrawer').removeClass('open');
      $('body').css('overflow', '');
      setTimeout(() => $('#oxDrawerBody').html('<div class="ox-spinner-wrap"><div class="ox-spinner"></div></div>'), 300);
    },
    loading() {
      $('#oxDrawerBody').html('<div class="ox-spinner-wrap"><div class="ox-spinner"></div></div>');
    },
  };

  $('#oxDrawerClose, #oxDrawerOverlay').on('click', function (e) {
    if (e.target === this) Drawer.close();
  });

  /* ================================================================
     MODAL (centered dialog — forms / confirms)
  ================================================================ */
  const Modal = {
    open(title, html, size = 'md') {
      $('#oxModalTitle').text(title);
      $('#oxModalBody').html(html || '<div class="ox-spinner-wrap"><div class="ox-spinner"></div></div>');
      $('#oxModal').attr('class', 'ox-modal ' + size);
      $('#oxModalOverlay').addClass('open');
      $('body').css('overflow', 'hidden');
    },
    setContent(html) { $('#oxModalBody').html(html); },
    close() {
      $('#oxModalOverlay').removeClass('open');
      $('body').css('overflow', '');
      setTimeout(() => $('#oxModalBody').html('<div class="ox-spinner-wrap"><div class="ox-spinner"></div></div>'), 260);
    },
    loading() { $('#oxModalBody').html('<div class="ox-spinner-wrap"><div class="ox-spinner"></div></div>'); },
  };

  $('#oxModalClose, #oxModalOverlay').on('click', function (e) {
    if (e.target === this) Modal.close();
  });

  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') { Drawer.close(); Modal.close(); }
  });

  /* ================================================================
     DELETE CONFIRM (SweetAlert2)
  ================================================================ */
  function confirmDelete(label, onConfirm) {
    Swal.fire({
      title: 'Delete ' + (label || 'record') + '?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#DC2626',
      cancelButtonColor: '#6B7280',
      confirmButtonText: '<i class="fas fa-trash"></i> Delete',
      cancelButtonText: 'Cancel',
      buttonsStyling: true,
      focusCancel: true,
      customClass: { popup: 'swal-onyx' },
    }).then(result => { if (result.isConfirmed) onConfirm(); });
  }

  /* Attach to all ox-delete-btn */
  $(document).on('click', '.ox-delete-btn', function () {
    const $btn  = $(this);
    const label = $btn.data('label') || 'this record';
    const $form = $btn.closest('form');
    confirmDelete(label, () => $form.submit());
  });

  /* ================================================================
     SIDEBAR TOGGLE (mobile)
  ================================================================ */
  $('#adSidebarToggle').on('click', () => {
    $('#adSidebar').addClass('open');
    $('<div class="ox-sidebar-backdrop"></div>').appendTo('body').on('click', function () {
      $('#adSidebar').removeClass('open');
      $(this).remove();
    });
  });
  $('#adSidebarClose').on('click', () => {
    $('#adSidebar').removeClass('open');
    $('.ox-sidebar-backdrop').remove();
  });

  /* ================================================================
     USER DROPDOWN
  ================================================================ */
  $('#adUserTrigger').on('click', function (e) {
    e.stopPropagation();
    $('#adUserMenu').toggleClass('open');
  });
  $(document).on('click', () => $('#adUserMenu').removeClass('open'));

  /* ================================================================
     AUTO-DISMISS ALERTS
  ================================================================ */
  $('.ad-auto-dismiss').each(function () {
    const $el = $(this);
    setTimeout(() => {
      $el.css({ transition: 'opacity 0.4s', opacity: 0 });
      setTimeout(() => $el.remove(), 400);
    }, 4800);
  });

  /* ================================================================
     CONDITIONAL FIELD TOGGLE
  ================================================================ */
  $('[data-toggle-target]').each(function () {
    const $cb  = $(this);
    const $tgt = $('#' + $cb.data('toggle-target'));
    const sync = () => $tgt.toggle($cb.is(':checked'));
    sync();
    $cb.on('change', sync);
  });

  /* ================================================================
     CHAR COUNTER
  ================================================================ */
  $('textarea[data-maxlength]').each(function () {
    const $ta  = $(this);
    const max  = parseInt($ta.data('maxlength'));
    const $ctr = $('<span style="font-size:.7rem;color:var(--mt);float:right;margin-top:3px;"></span>');
    $ta.after($ctr);
    const up = () => {
      const left = max - $ta.val().length;
      $ctr.text(left + ' left').css('color', left < 50 ? '#DC2626' : 'var(--mt)');
    };
    $ta.on('input', up); up();
  });

  /* ================================================================
     AUTO-SUBMIT SELECT
  ================================================================ */
  $(document).on('change', '.ad-auto-submit', function () {
    $(this).closest('form').submit();
  });

  /* ================================================================
     INLINE STATUS SELECT
  ================================================================ */
  $(document).on('change', '.ox-status-sel[data-case-id]', function () {
    const $sel   = $(this);
    const caseId = $sel.data('case-id');
    const status = $sel.val();
    $sel.attr('class', 'ox-status-sel st-' + status);
    $.ajax({ url: API + '/cases/' + caseId + '/status', method: 'PATCH', data: JSON.stringify({ status }), contentType: 'application/json' })
      .done(() => Toast.success('Case status updated to ' + status))
      .fail(() => Toast.error('Failed to update status'));
  });

  /* ================================================================
     HELPER: skeleton HTML
  ================================================================ */
  function skel(lines = 4) {
    return '<div style="padding:16px">' +
      Array.from({ length: lines }, (_, i) =>
        `<div style="height:11px;background:linear-gradient(90deg,#f0ece8 25%,#e8e2dd 50%,#f0ece8 75%);background-size:200%;animation:oxSkel 1.2s infinite;border-radius:3px;margin-bottom:10px;width:${[100,72,88,55,40][i % 5]}%"></div>`
      ).join('') + '</div>';
  }
  if (!$('#oxSkelStyle').length) {
    $('head').append('<style id="oxSkelStyle">@keyframes oxSkel{from{background-position:200% 0}to{background-position:-200% 0}}.swal-onyx{font-family:"Inter",system-ui,sans-serif;font-size:13px;}</style>');
  }

  function badge(status) {
    const cls = { pending: 'badge-pending', active: 'badge-active', ongoing: 'badge-ongoing', closed: 'badge-closed', archived: 'badge-archived' };
    return `<span class="badge-ad ${cls[status] || 'badge-gray'}">${status}</span>`;
  }

  /* ================================================================
     CASE DETAIL DRAWER
  ================================================================ */
  function renderCaseDrawer(c) {
    function noteAvatar(n) {
      if (n.avatar_url) {
        return `<img src="${n.avatar_url}" alt="${n.author}" class="ox-note-avatar"
                    style="border-radius:50%;object-fit:cover;width:28px;height:28px;"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="ox-note-avatar" style="display:none;">${n.initials}</div>`;
      }
      return `<div class="ox-note-avatar">${n.initials}</div>`;
    }

    const notesHtml = c.notes.length
      ? c.notes.map(n => `
        <div class="ox-note-item">
          ${noteAvatar(n)}
          <div class="ox-note-body">
            <div class="ox-note-meta">${n.author} · ${n.diff}</div>
            <div class="ox-note-text">${n.note}</div>
          </div>
          ${n.can_delete ? `<button class="btn-ad btn-ad-ghost btn-ad-sm" style="color:#DC2626;flex-shrink:0;" data-delete-note="${n.id}"><i class="fas fa-times"></i></button>` : ''}
        </div>
      `).join('')
      : '<p style="font-size:.75rem;color:var(--mt);padding:8px 0;">No notes yet.</p>';

    const docsHtml = c.documents.length
      ? c.documents.map(d => `
        <div class="ox-doc-item">
          <i class="fas fa-file-alt" style="color:var(--br);font-size:.9rem;"></i>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.75rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${d.title}</div>
            <div style="font-size:.625rem;color:var(--mt);">${d.category}</div>
          </div>
          <a href="${d.download_url}" class="btn-ad btn-ad-ghost btn-ad-xs" title="Download"><i class="fas fa-download"></i></a>
        </div>
      `).join('')
      : '<p style="font-size:.75rem;color:var(--mt);">No documents.</p>';

    const txnsHtml = c.transactions.length
      ? c.transactions.map(t => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--bd);font-size:.75rem;">
          <span style="color:var(--mt);">${t.date} · ${t.account||''}</span>
          <span style="font-weight:700;color:${t.type==='income'?'#15803D':'#DC2626'};">${t.type==='income'?'+':'-'}${t.amount}</span>
        </div>
      `).join('')
      : '<p style="font-size:.75rem;color:var(--mt);">No transactions.</p>';

    return `
      <div class="ox-case-head">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
          <div>
            <div style="font-size:.625rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--mt);margin-bottom:3px;">${c.case_number}</div>
            <div style="font-size:.9375rem;font-weight:700;color:var(--tx);line-height:1.3;">${c.title}</div>
          </div>
          <a href="${c.edit_url}" class="btn-ad btn-ad-ghost btn-ad-sm" style="flex-shrink:0;"><i class="fas fa-pen"></i> Edit</a>
        </div>
        <div class="ox-case-status-row">
          ${badge(c.status)}
          <span class="badge-ad badge-${c.priority}">${c.priority}</span>
          <span class="badge-ad badge-gray">${c.category_label}</span>
          ${c.is_in_court ? '<span class="ad-tracker-badge tracker-court"><i class="fas fa-gavel"></i> Court</span>' : ''}
          ${c.is_at_police ? '<span class="ad-tracker-badge tracker-police"><i class="fas fa-shield-halved"></i> Police</span>' : ''}
        </div>
      </div>

      <div class="ox-case-grid">
        <div class="ox-case-field"><div class="ox-case-field-label">Client</div><div class="ox-case-field-value">${c.client?.full_name || '—'} ${c.client ? `<span style="color:var(--mt);font-size:.625rem;">(${c.client.client_number})</span>` : ''}</div></div>
        <div class="ox-case-field"><div class="ox-case-field-label">Stage</div><div class="ox-case-field-value">${c.stage_label}</div></div>
        <div class="ox-case-field"><div class="ox-case-field-label">Officer</div><div class="ox-case-field-value">${c.main_officer?.name || '—'}</div></div>
        <div class="ox-case-field"><div class="ox-case-field-label">Filed</div><div class="ox-case-field-value">${c.filing_date}</div></div>
        <div class="ox-case-field"><div class="ox-case-field-label">Days Open</div><div class="ox-case-field-value">${c.days_open}</div></div>
        ${c.closed_date ? `<div class="ox-case-field"><div class="ox-case-field-label">Closed</div><div class="ox-case-field-value">${c.closed_date}</div></div>` : ''}
        ${c.client?.phone ? `<div class="ox-case-field"><div class="ox-case-field-label">Client Phone</div><div class="ox-case-field-value">${c.client.phone}</div></div>` : ''}
      </div>

      ${c.is_in_court ? `
      <div class="ox-section">
        <div class="ox-section-title"><i class="fas fa-gavel"></i> Court Details</div>
        <div class="ox-tracker-panel">
          <div class="ox-case-field"><div class="ox-case-field-label">Court</div><div class="ox-case-field-value">${c.court_name||'—'}</div></div>
          <div class="ox-case-field"><div class="ox-case-field-label">Division</div><div class="ox-case-field-value">${c.court_division||'—'}</div></div>
          <div class="ox-case-field"><div class="ox-case-field-label">Case No.</div><div class="ox-case-field-value">${c.court_case_number||'—'}</div></div>
          <div class="ox-case-field"><div class="ox-case-field-label">Judge</div><div class="ox-case-field-value">${c.judge_name||'—'}</div></div>
          ${c.next_hearing_date ? `<div class="ox-case-field" style="grid-column:1/-1;"><div class="ox-case-field-label">Next Hearing</div><div class="ox-case-field-value" style="color:var(--br);font-weight:600;">${c.next_hearing_date}</div></div>` : ''}
        </div>
      </div>` : ''}

      ${c.is_at_police ? `
      <div class="ox-section">
        <div class="ox-section-title"><i class="fas fa-shield-halved"></i> Police Details</div>
        <div class="ox-tracker-panel police">
          <div class="ox-case-field"><div class="ox-case-field-label">Station</div><div class="ox-case-field-value">${c.police_station||'—'}</div></div>
          <div class="ox-case-field"><div class="ox-case-field-label">Ref No.</div><div class="ox-case-field-value">${c.police_ref_number||'—'}</div></div>
          <div class="ox-case-field"><div class="ox-case-field-label">Inv. Officer</div><div class="ox-case-field-value">${c.investigating_officer||'—'}</div></div>
        </div>
      </div>` : ''}

      ${c.description ? `<div class="ox-section"><div class="ox-section-title"><i class="fas fa-align-left"></i> Description</div><p style="font-size:.75rem;line-height:1.6;color:var(--tx);">${c.description}</p></div>` : ''}

      <div class="ox-section">
        <div class="ox-section-title"><i class="fas fa-sticky-note"></i> Notes (${c.notes.length})</div>
        <div id="oxCaseNotes">${notesHtml}</div>
        <div class="ox-add-note-form" style="margin-top:10px;">
          <textarea class="ad-input ad-textarea" id="oxNoteText" rows="2" placeholder="Add a note…" style="min-height:56px;"></textarea>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <button class="btn-ad btn-ad-primary btn-ad-sm" id="oxAddNoteBtn" data-case="${c.id}">
              <i class="fas fa-plus"></i> Add Note
            </button>
            <label class="ad-check-group" style="font-size:.7rem;">
              <input type="checkbox" id="oxNotePrivate"> <span>Private</span>
            </label>
          </div>
        </div>
      </div>

      <div class="ox-section">
        <div class="ox-section-title"><i class="fas fa-folder-open"></i> Documents (${c.documents.length})</div>
        ${docsHtml}
      </div>

      <div class="ox-section">
        <div class="ox-section-title"><i class="fas fa-money-bill-transfer"></i> Recent Transactions</div>
        ${txnsHtml}
      </div>

      ${c.can_close ? `
      <div class="ox-drawer-foot">
        <button class="btn-ad btn-ad-ghost btn-ad-sm" onclick="ONYX.cases.closePrompt(${c.id})">
          <i class="fas fa-lock"></i> Close Case
        </button>
        <a href="${c.show_url}" class="btn-ad btn-ad-primary btn-ad-sm">
          <i class="fas fa-arrow-right"></i> Full View
        </a>
      </div>` : `
      <div class="ox-drawer-foot">
        <a href="${c.show_url}" class="btn-ad btn-ad-primary btn-ad-sm">
          <i class="fas fa-arrow-right"></i> Full View
        </a>
      </div>`}
    `;
  }

  /* ================================================================
     CLIENT DETAIL DRAWER
  ================================================================ */
  function renderClientDrawer(client) {
    const casesHtml = client.cases.length
      ? client.cases.map(c => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--bd);">
          <div>
            <a href="#" onclick="ONYX.cases.showDetail(${c.id});return false;" class="case-number-badge" style="font-size:.625rem;">${c.case_number}</a>
            <span style="font-size:.75rem;margin-left:6px;">${c.title}</span>
          </div>
          ${badge(c.status)}
        </div>
      `).join('')
      : '<p style="font-size:.75rem;color:var(--mt);">No cases yet.</p>';

    return `
      <div class="ox-case-head">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--br),var(--ac));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.875rem;flex-shrink:0;">
            ${client.initials}
          </div>
          <div>
            <div style="font-size:.625rem;color:var(--mt);font-weight:600;letter-spacing:.06em;">${client.client_number}</div>
            <div style="font-size:.9375rem;font-weight:700;color:var(--tx);">${client.full_name}</div>
            ${client.company ? `<div style="font-size:.7rem;color:var(--mt);">${client.company}</div>` : ''}
          </div>
          <a href="${client.edit_url}" class="btn-ad btn-ad-ghost btn-ad-sm" style="margin-left:auto;flex-shrink:0;"><i class="fas fa-pen"></i> Edit</a>
        </div>
      </div>

      <div class="ox-case-grid">
        ${client.phone ? `<div class="ox-case-field"><div class="ox-case-field-label">Phone</div><div class="ox-case-field-value"><a href="tel:${client.phone}" style="color:var(--br);">${client.phone}</a></div></div>` : ''}
        ${client.phone_alt ? `<div class="ox-case-field"><div class="ox-case-field-label">Alt Phone</div><div class="ox-case-field-value">${client.phone_alt}</div></div>` : ''}
        ${client.email ? `<div class="ox-case-field"><div class="ox-case-field-label">Email</div><div class="ox-case-field-value"><a href="mailto:${client.email}" style="color:var(--br);">${client.email}</a></div></div>` : ''}
        ${client.gender ? `<div class="ox-case-field"><div class="ox-case-field-label">Gender</div><div class="ox-case-field-value">${client.gender}</div></div>` : ''}
        ${client.dob ? `<div class="ox-case-field"><div class="ox-case-field-label">Date of Birth</div><div class="ox-case-field-value">${client.dob}</div></div>` : ''}
        ${client.occupation ? `<div class="ox-case-field"><div class="ox-case-field-label">Occupation</div><div class="ox-case-field-value">${client.occupation}</div></div>` : ''}
        ${client.district ? `<div class="ox-case-field"><div class="ox-case-field-label">District</div><div class="ox-case-field-value">${client.district}</div></div>` : ''}
        ${client.id_type ? `<div class="ox-case-field"><div class="ox-case-field-label">ID Type</div><div class="ox-case-field-value">${client.id_type} ${client.id_number ? '· ' + client.id_number : ''}</div></div>` : ''}
        <div class="ox-case-field" style="grid-column:1/-1;"><div class="ox-case-field-label">Address</div><div class="ox-case-field-value">${client.address}</div></div>
        <div class="ox-case-field" style="grid-column:1/-1;"><div class="ox-case-field-label">Registered</div><div class="ox-case-field-value">${client.registered}</div></div>
      </div>

      ${client.notes ? `<div class="ox-section"><div class="ox-section-title"><i class="fas fa-sticky-note"></i> Notes</div><p style="font-size:.75rem;line-height:1.6;">${client.notes}</p></div>` : ''}

      <div class="ox-section">
        <div class="ox-section-title"><i class="fas fa-scale-balanced"></i> Cases (${client.cases.length})</div>
        ${casesHtml}
      </div>
    `;
  }

  /* ================================================================
     QUICK-CREATE CASE MODAL
  ================================================================ */
  const quickCaseForm = `
    <form id="oxQuickCaseForm">
      <div class="ad-form-grid" style="margin-bottom:0;">
        <div class="ad-form-group span-2">
          <label class="ad-form-group">Case Title <span class="req">*</span></label>
          <input type="text" name="title" class="ad-input" required placeholder="e.g. Smith v. Jones – Land Dispute">
        </div>
        <div class="ad-form-group">
          <label>Category <span class="req">*</span></label>
          <select name="category" class="ad-select" required>
            <option value="">— Category —</option>
            <option value="civil_litigation">Civil Litigation</option>
            <option value="criminal_defense">Criminal Defence</option>
            <option value="family_law">Family Law</option>
            <option value="land_property">Land &amp; Property</option>
            <option value="commercial_corporate">Commercial</option>
            <option value="employment_labour">Employment</option>
            <option value="human_rights">Human Rights</option>
            <option value="constitutional">Constitutional</option>
            <option value="succession_probate">Succession</option>
            <option value="debt_recovery">Debt Recovery</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="ad-form-group">
          <label>Priority <span class="req">*</span></label>
          <select name="priority" class="ad-select" required>
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
        <div class="ad-form-group span-2">
          <label>Client <span class="req">*</span></label>
          <select name="client_id" class="ad-select ox-client-select" required style="width:100%;"></select>
        </div>
        <div class="ad-form-group">
          <label>Filing Date <span class="req">*</span></label>
          <input type="date" name="filing_date" class="ad-input" required value="${new Date().toISOString().slice(0,10)}">
        </div>
        <div class="ad-form-group">
          <label>Lead Officer</label>
          <select name="main_officer_id" class="ad-select ox-officer-select" style="width:100%;"></select>
        </div>
        <div class="ad-form-group span-2">
          <label>Brief Description</label>
          <textarea name="description" class="ad-input ad-textarea" rows="2" placeholder="Optional brief…"></textarea>
        </div>
      </div>
      <div class="ox-modal-foot" style="margin-top:14px;padding:0;">
        <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">Cancel</button>
        <button type="submit" class="btn-ad btn-ad-primary" id="oxQuickCaseSave">
          <i class="fas fa-scale-balanced"></i> Create Case
        </button>
      </div>
    </form>
  `;

  /* ================================================================
     QUICK-CREATE CLIENT MODAL
  ================================================================ */
  const quickClientForm = `
    <form id="oxQuickClientForm">
      <div class="ad-form-grid" style="margin-bottom:0;">
        <div class="ad-form-group">
          <label>First Name <span class="req">*</span></label>
          <input type="text" name="first_name" class="ad-input" required>
        </div>
        <div class="ad-form-group">
          <label>Last Name <span class="req">*</span></label>
          <input type="text" name="last_name" class="ad-input" required>
        </div>
        <div class="ad-form-group">
          <label>Phone <span class="req">*</span></label>
          <input type="text" name="phone" class="ad-input" required placeholder="+256…">
        </div>
        <div class="ad-form-group">
          <label>Email</label>
          <input type="email" name="email" class="ad-input">
        </div>
        <div class="ad-form-group span-2">
          <label>Address <span class="req">*</span></label>
          <input type="text" name="address" class="ad-input" required placeholder="Physical address">
        </div>
        <div class="ad-form-group">
          <label>District</label>
          <input type="text" name="district" class="ad-input">
        </div>
      </div>
      <div class="ox-modal-foot" style="margin-top:14px;padding:0;">
        <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">Cancel</button>
        <button type="submit" class="btn-ad btn-ad-primary" id="oxQuickClientSave">
          <i class="fas fa-user-plus"></i> Create Client
        </button>
      </div>
    </form>
  `;

  /* ================================================================
     QUICK TRANSACTION MODAL
  ================================================================ */
  const quickTxnForm = `
    <form id="oxQuickTxnForm">
      <div class="ad-form-grid" style="margin-bottom:0;">
        <div class="ad-form-group">
          <label>Type <span class="req">*</span></label>
          <select name="type" class="ad-select" required>
            <option value="income">Income</option>
            <option value="expense">Expense</option>
          </select>
        </div>
        <div class="ad-form-group">
          <label>Amount (UGX) <span class="req">*</span></label>
          <input type="number" name="amount" class="ad-input" required min="1" step="1" placeholder="0">
        </div>
        <div class="ad-form-group span-2">
          <label>Description <span class="req">*</span></label>
          <input type="text" name="description" class="ad-input" required placeholder="e.g. Legal consultation fee">
        </div>
        <div class="ad-form-group">
          <label>Account <span class="req">*</span></label>
          <select name="account_id" class="ad-select ox-account-select" required></select>
        </div>
        <div class="ad-form-group">
          <label>Payment Method <span class="req">*</span></label>
          <select name="payment_method" class="ad-select" required>
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cheque">Cheque</option>
            <option value="mobile_money">Mobile Money</option>
          </select>
        </div>
        <div class="ad-form-group">
          <label>Date <span class="req">*</span></label>
          <input type="date" name="transaction_date" class="ad-input" required value="${new Date().toISOString().slice(0,10)}">
        </div>
        <div class="ad-form-group">
          <label>Reference No.</label>
          <input type="text" name="reference_number" class="ad-input" placeholder="Optional">
        </div>
        <div class="ad-form-group span-2">
          <label>Linked Case (optional)</label>
          <select name="case_id" class="ad-select ox-active-cases-select"></select>
        </div>
      </div>
      <div class="ox-modal-foot" style="margin-top:14px;padding:0;">
        <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">Cancel</button>
        <button type="submit" class="btn-ad btn-ad-primary" id="oxQuickTxnSave">
          <i class="fas fa-money-bill-transfer"></i> Record Transaction
        </button>
      </div>
    </form>
  `;

  /* ================================================================
     ONYX NAMESPACE (public API)
  ================================================================ */
  window.ONYX = {
    cases: {
      showDetail(id) {
        Drawer.open('Case Details');
        $.get(API + '/cases/' + id)
          .done(c => {
            Drawer.setTitle(c.case_number + ' — ' + c.title);
            Drawer.setContent(renderCaseDrawer(c));
          })
          .fail(() => { Drawer.setContent('<div style="padding:20px;color:#DC2626;">Failed to load case.</div>'); Toast.error('Could not load case details.'); });
      },

      quickCreate() {
        Modal.open('New Case', quickCaseForm, 'md');
        // Load clients select
        $.get(API + '/clients-select')
          .done(data => {
            const $sel = $('.ox-client-select');
            $sel.html('<option value="">— Select Client —</option>' +
              data.results.map(c => `<option value="${c.id}">${c.text}</option>`).join(''));
          });
        // Load officers
        $.get(API + '/officers')
          .done(data => {
            const $sel = $('.ox-officer-select');
            $sel.html('<option value="">— Select Officer —</option>' +
              data.map(o => `<option value="${o.id}">${o.name}</option>`).join(''));
          });
        // Form submit
        $(document).off('submit', '#oxQuickCaseForm').on('submit', '#oxQuickCaseForm', function (e) {
          e.preventDefault();
          const $btn  = $('#oxQuickCaseSave');
          const data  = {};
          $(this).serializeArray().forEach(f => data[f.name] = f.value);
          $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating…');
          $.ajax({ url: API + '/cases', method: 'POST', contentType: 'application/json', data: JSON.stringify(data) })
            .done(res => {
              Modal.close();
              Toast.success('Case ' + res.case_number + ' created!');
              setTimeout(() => location.reload(), 800);
            })
            .fail(xhr => {
              const err = xhr.responseJSON?.message || 'Failed to create case.';
              Toast.error(err);
              $btn.prop('disabled', false).html('<i class="fas fa-scale-balanced"></i> Create Case');
            });
        });
      },

      closePrompt(id) {
        Modal.open('Close Case', `
          <form id="oxCloseCaseForm">
            <div class="ad-form-group">
              <label>Outcome <span class="req">*</span></label>
              <select name="score" class="ad-select" required>
                <option value="1">Win</option>
                <option value="0">Neutral / Settled</option>
                <option value="-1">Loss</option>
              </select>
            </div>
            <div class="ad-form-group">
              <label>Closing Remarks</label>
              <textarea name="closing_remarks" class="ad-input ad-textarea" rows="3" placeholder="Summary of how the case was resolved…"></textarea>
            </div>
            <div class="ox-modal-foot" style="margin-top:14px;padding:0;">
              <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">Cancel</button>
              <button type="submit" class="btn-ad btn-ad-danger"><i class="fas fa-lock"></i> Close Case</button>
            </div>
          </form>
        `, 'sm');
        $(document).off('submit', '#oxCloseCaseForm').on('submit', '#oxCloseCaseForm', function (e) {
          e.preventDefault();
          const data = {};
          $(this).serializeArray().forEach(f => data[f.name] = f.value);
          $.ajax({ url: API + '/cases/' + id + '/close', method: 'POST', contentType: 'application/json', data: JSON.stringify(data) })
            .done(() => { Modal.close(); Drawer.close(); Toast.success('Case closed.'); setTimeout(() => location.reload(), 700); })
            .fail(() => Toast.error('Failed to close case.'));
        });
      },
    },

    clients: {
      showDetail(id) {
        Drawer.open('Client Profile');
        $.get(API + '/clients/' + id)
          .done(client => {
            Drawer.setTitle(client.full_name + ' · ' + client.client_number);
            Drawer.setContent(renderClientDrawer(client));
          })
          .fail(() => { Drawer.setContent('<div style="padding:20px;color:#DC2626;">Failed to load client.</div>'); Toast.error('Could not load client.'); });
      },

      quickCreate() {
        Modal.open('New Client', quickClientForm, 'sm');
        $(document).off('submit', '#oxQuickClientForm').on('submit', '#oxQuickClientForm', function (e) {
          e.preventDefault();
          const $btn = $('#oxQuickClientSave');
          const data = {};
          $(this).serializeArray().forEach(f => data[f.name] = f.value);
          $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating…');
          $.ajax({ url: API + '/clients', method: 'POST', contentType: 'application/json', data: JSON.stringify(data) })
            .done(res => {
              Modal.close();
              Toast.success('Client ' + res.client_number + ' — ' + res.full_name + ' created!');
              setTimeout(() => location.reload(), 700);
            })
            .fail(xhr => {
              Toast.error(xhr.responseJSON?.message || 'Failed to create client.');
              $btn.prop('disabled', false).html('<i class="fas fa-user-plus"></i> Create Client');
            });
        });
      },

      /* ── quickAddToSelect ──────────────────────────────────────
         Opens a rich quick-add modal. On success, injects the new
         client into the Select2 with `selectId` and auto-selects it.
         Does NOT reload the page — stays on the current form.
      ─────────────────────────────────────────────────────────── */
      quickAddToSelect(selectId) {
        const form = `
          <div class="ox-modal-hint">
            <i class="fas fa-lightbulb"></i>
            Fill in the basics below. The client number is auto-generated.
            On success, the client will be <strong>selected automatically</strong>.
          </div>
          <div id="oxClientFormErrors" style="display:none;"></div>
          <form id="oxQuickClientForm" autocomplete="off">
            <div class="ad-form-grid" style="margin-bottom:0;">
              <div class="ad-form-group">
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name" id="cf_first_name" class="ad-input"
                       required placeholder="e.g. John" autofocus>
              </div>
              <div class="ad-form-group">
                <label>Last Name <span class="req">*</span></label>
                <input type="text" name="last_name" id="cf_last_name" class="ad-input"
                       required placeholder="e.g. Ssemakula">
              </div>
              <div class="ad-form-group">
                <label>Phone <span class="req">*</span></label>
                <input type="text" name="phone" id="cf_phone" class="ad-input"
                       required placeholder="+256 7XX XXX XXX">
              </div>
              <div class="ad-form-group">
                <label>Email</label>
                <input type="email" name="email" id="cf_email" class="ad-input"
                       placeholder="optional">
              </div>
              <div class="ad-form-group span-2">
                <label>Physical Address <span class="req">*</span></label>
                <input type="text" name="address" id="cf_address" class="ad-input"
                       required placeholder="e.g. Plot 12, Kampala Road">
              </div>
              <div class="ad-form-group">
                <label>District</label>
                <input type="text" name="district" id="cf_district" class="ad-input"
                       placeholder="e.g. Kampala">
              </div>
              <div class="ad-form-group">
                <label>Occupation</label>
                <input type="text" name="occupation" id="cf_occupation" class="ad-input"
                       placeholder="optional">
              </div>
              <div class="ad-form-group">
                <label>Company / Organisation</label>
                <input type="text" name="company" id="cf_company" class="ad-input"
                       placeholder="optional">
              </div>
            </div>
            <div class="ox-modal-foot" style="margin-top:16px;padding:0;">
              <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">
                <i class="fas fa-times"></i> Cancel
              </button>
              <button type="submit" class="btn-ad btn-ad-primary" id="oxQuickClientSave">
                <i class="fas fa-user-plus"></i> Add Client &amp; Select
              </button>
            </div>
          </form>
        `;

        Modal.open('Add New Client', form, 'sm');

        /* Focus first field after modal animation */
        setTimeout(() => { $('#cf_first_name').focus(); }, 180);

        /* Handle form submit */
        $(document).off('submit', '#oxQuickClientForm').on('submit', '#oxQuickClientForm', function (e) {
          e.preventDefault();
          const $btn  = $('#oxQuickClientSave');
          const $errs = $('#oxClientFormErrors');
          const data  = {};
          $(this).serializeArray().forEach(f => { if (f.value.trim()) data[f.name] = f.value.trim(); });

          /* Clear previous error state */
          $errs.hide().empty();
          $(this).find('.ad-input').removeClass('ox-field-error');

          /* Button loading */
          const origHtml = $btn.html();
          $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');

          $.ajax({
            url: API + '/clients',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
          })
          .done(function (res) {
            Modal.close();

            /* ── Inject into Select2 and auto-select ── */
            const $sel  = $('#' + selectId);
            const label = res.full_name + ' (' + res.client_number + ')';

            /* Remove duplicate if exists */
            $sel.find('option[value="' + res.id + '"]').remove();

            /* Create option, mark as selected */
            const opt = new Option(label, res.id, true, true);
            $sel.append(opt);

            /* Tell Select2 to reflect the change */
            $sel.trigger('change');

            /* Visual confirmation flash below the select */
            const $grp  = $sel.closest('.ad-form-group, .ox-select-with-add').closest('.ad-form-group');
            const $prev = $grp.find('.ox-client-confirmed');
            $prev.remove();

            const $confirm = $(
              '<div class="ox-client-confirmed">' +
              '<i class="fas fa-check-circle"></i>' +
              '<span><strong>' + res.full_name + '</strong> (' + res.client_number + ') — auto-selected</span>' +
              '</div>'
            );
            $grp.append($confirm);
            setTimeout(() => $confirm.fadeOut(500, function () { $(this).remove(); }), 4000);

            Toast.success(res.full_name + ' added and selected!');
          })
          .fail(function (xhr) {
            const resp = xhr.responseJSON || {};
            $btn.prop('disabled', false).html(origHtml);

            if (xhr.status === 422 && resp.errors) {
              /* Build inline error summary */
              const lines = Object.values(resp.errors).flat()
                .map(m => '<div class="err-line">' + m + '</div>').join('');
              $errs.html(
                '<div class="ox-form-err-box">' +
                '<strong><i class="fas fa-circle-exclamation"></i> Please fix the following:</strong>' +
                lines + '</div>'
              ).show();

              /* Highlight each broken field */
              Object.keys(resp.errors).forEach(function (field) {
                $('#cf_' + field).addClass('ox-field-error');
              });

              /* Scroll error into view */
              $errs[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            } else {
              Toast.error(resp.message || 'Failed to create client. Try again.');
            }
          });
        });

        /* Clear field error highlight on input */
        $(document).on('input', '#oxQuickClientForm .ad-input', function () {
          $(this).removeClass('ox-field-error');
        });
      },
    },

    transactions: {
      quickRecord() {
        Modal.open('Record Transaction', quickTxnForm, 'md');
        // Load accounts
        $.get(API + '/accounts').done(data => {
          $('.ox-account-select').html('<option value="">— Select Account —</option>' +
            data.map(a => `<option value="${a.id}">${a.name} (${a.type})</option>`).join(''));
        });
        // Load active cases
        $.get(API + '/active-cases').done(data => {
          $('.ox-active-cases-select').html('<option value="">— No Case —</option>' +
            data.map(c => `<option value="${c.id}">${c.text}</option>`).join(''));
        });
        // Load active period
        $.get(API + '/active-period').done(period => {
          if (period) {
            $('[name="financial_period_id"]').val(period.id);
          }
        });
        // Form submit
        $(document).off('submit', '#oxQuickTxnForm').on('submit', '#oxQuickTxnForm', function (e) {
          e.preventDefault();
          const $btn = $('#oxQuickTxnSave');
          const data = {};
          $(this).serializeArray().forEach(f => { if (f.value) data[f.name] = f.value; });
          $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
          $.ajax({ url: API + '/transactions', method: 'POST', contentType: 'application/json', data: JSON.stringify(data) })
            .done(res => {
              Modal.close();
              Toast.success('Transaction ' + res.transaction_number + ' recorded!');
              setTimeout(() => location.reload(), 700);
            })
            .fail(xhr => {
              Toast.error(xhr.responseJSON?.message || 'Failed to record transaction.');
              $btn.prop('disabled', false).html('<i class="fas fa-money-bill-transfer"></i> Record Transaction');
            });
        });
      },
    },
  };

  /* ================================================================
     ADD CASE NOTE (from drawer)
  ================================================================ */
  $(document).on('click', '#oxAddNoteBtn', function () {
    const $btn    = $(this);
    const caseId  = $btn.data('case');
    const noteText = $('#oxNoteText').val().trim();
    const isPriv  = $('#oxNotePrivate').is(':checked');
    if (!noteText) { Toast.warning('Please type a note first.'); return; }
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.ajax({ url: API + '/cases/' + caseId + '/notes', method: 'POST', contentType: 'application/json', data: JSON.stringify({ note: noteText, is_private: isPriv }) })
      .done(note => {
        $('#oxNoteText').val('');
        $('#oxNotePrivate').prop('checked', false);
        const html = `
          <div class="ox-note-item" id="note-${note.id}">
            ${note.avatar_url
              ? `<img src="${note.avatar_url}" class="ox-note-avatar" style="border-radius:50%;object-fit:cover;width:28px;height:28px;">`
              : `<div class="ox-note-avatar">${note.initials}</div>`
            }
            <div class="ox-note-body">
              <div class="ox-note-meta">${note.author} · just now</div>
              <div class="ox-note-text">${note.note}</div>
            </div>
            <button class="btn-ad btn-ad-ghost btn-ad-sm" style="color:#DC2626;flex-shrink:0;" data-delete-note="${note.id}"><i class="fas fa-times"></i></button>
          </div>
        `;
        $('#oxCaseNotes').prepend(html);
        Toast.success('Note added.');
      })
      .fail(() => Toast.error('Failed to add note.'))
      .always(() => $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add Note'));
  });

  /* ================================================================
     DELETE NOTE (from drawer)
  ================================================================ */
  $(document).on('click', '[data-delete-note]', function () {
    const noteId = $(this).data('delete-note');
    const $item  = $(this).closest('.ox-note-item');
    $.ajax({ url: API + '/notes/' + noteId, method: 'DELETE' })
      .done(() => { $item.fadeOut(200, function () { $(this).remove(); }); Toast.success('Note deleted.'); })
      .fail(() => Toast.error('Failed to delete note.'));
  });

  /* ================================================================
     PROFILE NAMESPACE — My Profile & Change Password
  ================================================================ */
  ONYX.profile = {

    /* ── helpers to sync avatar across all instances in the DOM ── */
    _syncAvatar(url, initials) {
      var $img = $('#topbarAvatarImg,#sidebarAvatarImg,#dropdownAvatarImg');
      var $ini = $('#topbarAvatarInitials,#sidebarAvatarInitials,#dropdownAvatarInitials');
      if (url) {
        $img.attr('src', url).show();
        $ini.hide();
      } else {
        $img.hide();
        $ini.text(initials).show();
      }
    },

    _syncName(name) {
      $('#topbarUserName, #sidebarUserName, #dropdownUserName').text(name);
    },

    /* ── My Profile modal ── */
    open() {
      var u = ONYX_CONFIG.user;
      var avatarHtml = u.avatar_url
        ? '<img src="' + u.avatar_url + '" id="pfAvatarImg" ' +
          'style="width:80px;height:80px;border-radius:50%;object-fit:cover;' +
          'border:3px solid var(--bd);display:block;margin:0 auto 12px;">'
        : '<div id="pfAvatarInitials" style="width:80px;height:80px;border-radius:50%;' +
          'background:linear-gradient(135deg,var(--br),var(--ac));' +
          'display:flex;align-items:center;justify-content:center;' +
          'font-size:1.5rem;font-weight:700;color:#fff;margin:0 auto 12px;">' +
          u.initials + '</div>' +
          '<img src="" id="pfAvatarImg" style="width:80px;height:80px;border-radius:50%;' +
          'object-fit:cover;border:3px solid var(--bd);display:none;margin:0 auto 12px;">';

      Modal.open('My Profile', `
        <div style="text-align:center;margin-bottom:16px;position:relative;display:inline-block;width:100%;">
          ${avatarHtml}
          <label for="pfAvatarInput" title="Change photo"
                 style="position:absolute;bottom:12px;left:50%;margin-left:28px;
                        width:26px;height:26px;border-radius:50%;background:var(--br);
                        color:#fff;display:flex;align-items:center;justify-content:center;
                        cursor:pointer;font-size:.65rem;box-shadow:0 2px 6px rgba(0,0,0,.25);">
            <i class="fas fa-camera"></i>
          </label>
          <input type="file" id="pfAvatarInput" accept="image/*" style="display:none;">
          ${u.avatar_url ? '<button type="button" id="pfRemoveAvatar" class="btn-ad btn-ad-ghost btn-ad-sm" style="display:block;margin:6px auto 0;font-size:.65rem;color:#DC2626;"><i class="fas fa-trash"></i> Remove Photo</button>' : ''}
        </div>
        <div id="pfFormErrors" style="display:none;margin-bottom:10px;"></div>
        <form id="pfForm">
          <div class="ad-form-grid" style="margin-bottom:0;">
            <div class="ad-form-group">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" name="name" class="ad-input" required value="${u.name}" id="pfName">
            </div>
            <div class="ad-form-group">
              <label>Email <span class="req">*</span></label>
              <input type="email" name="email" class="ad-input" required value="${u.email}">
            </div>
            <div class="ad-form-group">
              <label>Phone</label>
              <input type="text" name="phone" class="ad-input" value="${u.phone || ''}">
            </div>
          </div>
          <div class="ad-form-group" style="margin-top:10px;">
            <label>Bio</label>
            <textarea name="bio" class="ad-input ad-textarea" rows="2" placeholder="Short bio…">${u.bio || ''}</textarea>
          </div>
        </form>
        <div class="ox-modal-foot" style="margin-top:14px;padding:0;">
          <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">Cancel</button>
          <button type="button" class="btn-ad btn-ad-primary" id="pfSaveBtn">
            <i class="fas fa-check"></i> Save Profile
          </button>
        </div>
      `, 'sm');

      /* Avatar preview */
      $('#pfAvatarInput').on('change', function() {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
          $('#pfAvatarImg').attr('src', e.target.result).show();
          $('#pfAvatarInitials').hide();
        };
        reader.readAsDataURL(file);
        /* Upload immediately */
        ONYX.profile._uploadAvatar(file);
      });

      /* Remove avatar */
      $(document).off('click', '#pfRemoveAvatar').on('click', '#pfRemoveAvatar', function() {
        $.ajax({ url: API + '/profile/avatar', method: 'DELETE' })
          .done(function(res) {
            $('#pfAvatarImg').hide().attr('src','');
            $('#pfAvatarInitials').text(res.initials).show();
            ONYX.profile._syncAvatar(null, res.initials);
            ONYX_CONFIG.user.avatar_url = '';
            Toast.success('Profile photo removed.');
          })
          .fail(() => Toast.error('Failed to remove photo.'));
      });

      /* Save profile info */
      $('#pfSaveBtn').off('click').on('click', function() {
        var $btn = $(this);
        var $errs = $('#pfFormErrors');
        $errs.hide().empty();
        var data = {};
        $('#pfForm').serializeArray().forEach(function(f) { data[f.name] = f.value; });
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({ url: API + '/profile/update', method: 'POST', contentType: 'application/json', data: JSON.stringify(data) })
          .done(function(res) {
            Modal.close();
            ONYX_CONFIG.user.name = res.name;
            ONYX.profile._syncName(res.name);
            Toast.success('Profile updated successfully!');
          })
          .fail(function(xhr) {
            var resp = xhr.responseJSON || {};
            $btn.prop('disabled', false).html('<i class="fas fa-check"></i> Save Profile');
            if (xhr.status === 422 && resp.errors) {
              var lines = Object.values(resp.errors).flat().map(m => '<div class="err-line">' + m + '</div>').join('');
              $errs.html('<div class="ox-form-err-box"><strong><i class="fas fa-circle-exclamation"></i> Fix errors:</strong>' + lines + '</div>').show();
            } else {
              Toast.error(resp.message || 'Failed to save profile.');
            }
          });
      });
    },

    _uploadAvatar(file) {
      var fd = new FormData();
      fd.append('avatar', file);
      $.ajax({
        url: API + '/profile/avatar',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
      })
      .done(function(res) {
        ONYX_CONFIG.user.avatar_url = res.avatar_url;
        ONYX.profile._syncAvatar(res.avatar_url, res.initials);
        Toast.success('Profile photo updated!');
      })
      .fail(() => Toast.error('Photo upload failed.'));
    },

    /* ── Change Password modal ── */
    changePassword() {
      Modal.open('Change Password', `
        <div id="pwdFormErrors" style="display:none;margin-bottom:10px;"></div>
        <form id="pwdForm">
          <div class="ad-form-group">
            <label>Current Password <span class="req">*</span></label>
            <div style="position:relative;">
              <input type="password" name="current_password" id="cpCurrent" class="ad-input"
                     required placeholder="Your current password" style="padding-right:36px;">
              <button type="button" onclick="pwdToggle('cpCurrent',this)"
                      style="position:absolute;right:9px;top:50%;transform:translateY(-50%);
                             background:none;border:none;cursor:pointer;color:var(--mt);font-size:.8rem;">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="ad-form-group">
            <label>New Password <span class="req">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password" id="cpNew" class="ad-input"
                     required minlength="8" placeholder="min. 8 characters" style="padding-right:36px;">
              <button type="button" onclick="pwdToggle('cpNew',this)"
                      style="position:absolute;right:9px;top:50%;transform:translateY(-50%);
                             background:none;border:none;cursor:pointer;color:var(--mt);font-size:.8rem;">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div id="cpStrengthWrap" style="display:none;margin-top:6px;">
              <div style="height:3px;background:var(--bd);border-radius:2px;overflow:hidden;">
                <div id="cpStrengthBar" style="height:100%;width:0;transition:width .2s,background .2s;border-radius:2px;"></div>
              </div>
              <span id="cpStrengthLabel" style="font-size:.6rem;"></span>
            </div>
          </div>
          <div class="ad-form-group" style="margin-bottom:0;">
            <label>Confirm New Password <span class="req">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password_confirmation" id="cpConfirm" class="ad-input"
                     required placeholder="repeat new password" style="padding-right:36px;">
              <button type="button" onclick="pwdToggle('cpConfirm',this)"
                      style="position:absolute;right:9px;top:50%;transform:translateY(-50%);
                             background:none;border:none;cursor:pointer;color:var(--mt);font-size:.8rem;">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </form>
        <div class="ox-modal-foot" style="margin-top:14px;padding:0;">
          <button type="button" class="btn-ad btn-ad-ghost" onclick="Modal.close()">Cancel</button>
          <button type="button" class="btn-ad btn-ad-primary" id="cpSaveBtn">
            <i class="fas fa-lock"></i> Change Password
          </button>
        </div>
      `, 'sm');

      /* Password toggle helper */
      window.pwdToggle = function(id, btn) {
        var inp = document.getElementById(id);
        var icon = btn.querySelector('i');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
      };

      /* Strength meter */
      $('#cpNew').on('input', function() {
        var v = this.value;
        var $w = $('#cpStrengthWrap'), $b = $('#cpStrengthBar'), $l = $('#cpStrengthLabel');
        if (!v) { $w.hide(); return; }
        $w.show();
        var score = [v.length >= 8, /[A-Z]/.test(v), /[0-9]/.test(v), /[^A-Za-z0-9]/.test(v)].filter(Boolean).length;
        var colors = ['#DC2626','#F59E0B','#3B82F6','#15803D'];
        var labels = ['Weak','Fair','Good','Strong'];
        $b.css({ width: (score * 25) + '%', background: colors[score - 1] });
        $l.text(labels[score - 1] || '').css('color', colors[score - 1]);
      });

      /* Match check */
      $('#cpConfirm').on('input', function() {
        var match = $(this).val() === $('#cpNew').val();
        $(this).css('border-color', match ? '' : '#DC2626');
      });

      /* Save */
      $('#cpSaveBtn').off('click').on('click', function() {
        var $btn = $(this);
        var $errs = $('#pwdFormErrors');
        $errs.hide().empty();
        var data = {};
        $('#pwdForm').serializeArray().forEach(function(f) { data[f.name] = f.value; });
        if (data.password !== data.password_confirmation) {
          $errs.html('<div class="ox-form-err-box">Passwords do not match.</div>').show();
          return;
        }
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({ url: API + '/profile/password', method: 'POST', contentType: 'application/json', data: JSON.stringify(data) })
          .done(function() {
            Modal.close();
            Toast.success('Password changed successfully!');
          })
          .fail(function(xhr) {
            var resp = xhr.responseJSON || {};
            $btn.prop('disabled', false).html('<i class="fas fa-lock"></i> Change Password');
            if (xhr.status === 422 && resp.errors) {
              var lines = Object.values(resp.errors).flat().map(m => '<div class="err-line">' + m + '</div>').join('');
              $errs.html('<div class="ox-form-err-box"><strong><i class="fas fa-circle-exclamation"></i> Error:</strong>' + lines + '</div>').show();
            } else {
              Toast.error(resp.message || 'Failed to change password.');
            }
          });
      });
    },
  };

  /* ================================================================
     EXPOSE modal/drawer to window for templates
  ================================================================ */
  window.Modal  = Modal;
  window.Drawer = Drawer;
  window.Toast  = Toast;

})(jQuery);
