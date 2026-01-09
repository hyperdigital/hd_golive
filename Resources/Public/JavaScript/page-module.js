import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';

function bindSelect(select) {
  if (!select || select.dataset.hdgoliveBound === '1') {
    return;
  }

  select.dataset.hdgoliveBound = '1';
  select.addEventListener('change', async (event) => {
    const target = event.currentTarget;
    const status = parseInt(target.value, 10);
    const site = target.dataset.site || '';
    const session = target.dataset.session || '';
    const page = target.dataset.page || '';

    if (!site || !session || !page) {
      Notification.error('GO Live', 'Missing session or page.');
      return;
    }

    try {
      const response = await new AjaxRequest(
        TYPO3.settings.ajaxUrls['hd_golive_toggle_page_module']
      ).post({
        site,
        session,
        page,
        status,
      });
      const result = await response.resolve();
      if (!result || !result.success) {
        Notification.error('GO Live', result?.message || 'Request failed.');
        return;
      }

      if (typeof result.status !== 'undefined') {
        target.dataset.status = String(result.status);
      }

      if (result.editUrl) {
        const noteLink = target.closest('[data-hdgolive-page-row]')?.querySelector('[data-hdgolive-note-link]');
        if (noteLink) {
          noteLink.setAttribute('href', result.editUrl);
        }
      }

      if (typeof result.status !== 'undefined') {
        updateRowStatus(target.closest('[data-hdgolive-page-row]'), result.status);
      }
    } catch (error) {
      Notification.error('GO Live', 'Request failed.');
    }
  });
}

function clearBody(body) {
  if (!body) {
    return;
  }
  while (body.firstChild) {
    body.removeChild(body.firstChild);
  }
}

function createCell(className, text) {
  const cell = document.createElement('td');
  if (className) {
    cell.className = className;
  }
  if (text) {
    cell.textContent = text;
  }
  return cell;
}

function renderEntries(container, body, entries, debugInfo, debugEnabled) {
  clearBody(body);
  if (!entries.length) {
    if (debugEnabled) {
      container.hidden = false;
      const row = document.createElement('tr');
      const cell = createCell('text-muted', '');
      cell.style.whiteSpace = 'pre-wrap';
      cell.textContent = debugInfo ? JSON.stringify(debugInfo, null, 2) : 'No entries (debug payload missing).';
      row.appendChild(cell);
      body.appendChild(row);
    } else {
      container.hidden = true;
    }
    return;
  }

  container.hidden = false;
  entries.forEach((entry) => {
    const row = document.createElement('tr');
    row.dataset.hdgolivePageRow = '1';
    updateRowStatus(row, entry.status);

    const labelCell = createCell('align-middle', '');
    labelCell.style.width = '1%';
    labelCell.style.whiteSpace = 'nowrap';
    const label = document.createElement('strong');
    label.textContent = 'GO Live';
    labelCell.appendChild(label);
    row.appendChild(labelCell);

    const langCell = createCell('align-middle', '');
    langCell.style.whiteSpace = 'nowrap';
    const flag = document.createElement('span');
    flag.innerHTML = entry.languageFlag || '';
    langCell.appendChild(flag);
    const title = document.createElement('span');
    title.textContent = ` ${entry.languageTitle}`;
    langCell.appendChild(title);
    row.appendChild(langCell);

    const noteCell = createCell('align-middle', '');
    noteCell.style.width = '1%';
    noteCell.style.whiteSpace = 'nowrap';
    const noteLink = document.createElement('a');
    noteLink.className = 'btn btn-default btn-sm';
    noteLink.href = entry.editUrl || '#';
    noteLink.dataset.hdgoliveNoteLink = '1';
    noteLink.textContent = TYPO3.lang?.['LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:pageModule.note'] || 'Add note';
    noteCell.appendChild(noteLink);
    row.appendChild(noteCell);

    const statusLabelCell = createCell(
      'align-middle',
      TYPO3.lang?.['LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:pageModule.status'] || 'Status'
    );
    statusLabelCell.style.whiteSpace = 'nowrap';
    row.appendChild(statusLabelCell);

    const selectCell = createCell('align-middle', '');
    selectCell.style.width = '1%';
    selectCell.style.whiteSpace = 'nowrap';
    const select = document.createElement('select');
    select.className = 'form-select form-select-sm w-auto';
    select.dataset.hdgolivePageStatus = '1';
    select.dataset.site = entry.siteIdentifier;
    select.dataset.session = entry.sessionId;
    select.dataset.page = entry.pageId;
    select.dataset.status = entry.status;
    [
      { value: 0, label: TYPO3.lang?.['LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:status.pending'] || 'To be checked' },
      { value: 1, label: TYPO3.lang?.['LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:status.pass'] || 'Pass' },
      { value: 2, label: TYPO3.lang?.['LLL:EXT:hd_golive/Resources/Private/Language/locallang.xlf:status.failed'] || 'Failed' },
    ].forEach((option) => {
      const opt = document.createElement('option');
      opt.value = String(option.value);
      opt.textContent = option.label;
      if (entry.status === option.value) {
        opt.selected = true;
      }
      select.appendChild(opt);
    });
    selectCell.appendChild(select);
    row.appendChild(selectCell);

    body.appendChild(row);
    const noteRows = Array.isArray(entry.notesPreview) ? entry.notesPreview : [];
    if (noteRows.length > 0) {
      const notesRow = document.createElement('tr');
      notesRow.className = row.className;
      const notesCell = createCell('text-muted', '');
      notesCell.colSpan = 5;
      notesCell.style.paddingLeft = '2rem';
      notesCell.style.whiteSpace = 'normal';
      noteRows.forEach((note) => {
        const status = typeof note === 'object' && note !== null ? note.status : 0;
        const text = typeof note === 'object' && note !== null ? note.text : String(note ?? '');
        const line = document.createElement('div');
        const icon = document.createElement('typo3-backend-icon');
        icon.setAttribute('size', 'small');
        if (status === 1) {
          icon.setAttribute('identifier', 'status-dialog-ok');
        } else if (status === 2) {
          icon.setAttribute('identifier', 'status-dialog-error');
        } else {
          icon.setAttribute('identifier', 'status-dialog-warning');
        }
        icon.style.marginRight = '6px';
        line.appendChild(icon);
        const textNode = document.createElement('span');
        textNode.textContent = text;
        line.appendChild(textNode);
        notesCell.appendChild(line);
      });
      notesRow.appendChild(notesCell);
      body.appendChild(notesRow);
    }

    const separatorRow = document.createElement('tr');
    const separatorCell = createCell('', '');
    separatorCell.colSpan = 5;
    separatorCell.style.borderTop = '2px solid #ccc';
    separatorCell.style.padding = '0';
    separatorRow.appendChild(separatorCell);
    body.appendChild(separatorRow);

    bindSelect(select);
  });
}

function updateRowStatus(row, status) {
  if (!row) {
    return;
  }
  row.classList.toggle('table-success', status === 1);
  row.classList.toggle('table-danger', status === 2);
}

function initPageModule() {
  const container = document.querySelector('[data-hdgolive-page-module]');
  if (!container) {
    return;
  }

  const inlineSettings = TYPO3.settings?.hdgolivePageModule || {};
  const pageId = inlineSettings.pageId
    || container.dataset.pageId
    || document.querySelector('[data-page]')?.dataset.page
    || new URLSearchParams(window.location.search).get('id');
  const language = inlineSettings.language || container.dataset.language || '0';
  const body = container.querySelector('[data-hdgolive-page-body]');
  if (!pageId) {
    return;
  }
  const debugEnabled = new URLSearchParams(window.location.search).get('hdgoliveDebug') === '1';

  new AjaxRequest(TYPO3.settings.ajaxUrls['hd_golive_page_module_entries'])
    .post({
      page: pageId,
      language,
      returnUrl: window.location.href,
      debug: debugEnabled ? 1 : 0,
    })
    .then((response) => response.resolve())
    .then((result) => {
      if (!result || !result.success) {
        const debugPayload = debugEnabled ? (result?.debug || result || null) : null;
        renderEntries(container, body, [], debugPayload, debugEnabled);
        return;
      }
      const debugPayload = debugEnabled ? (result.debug || result || null) : null;
      renderEntries(container, body, result.entries || [], debugPayload, debugEnabled);
    })
    .catch(() => {
      renderEntries(container, body, [], null, debugEnabled);
      Notification.error('GO Live', 'Failed to load GO Live status.');
    });
}

initPageModule();
