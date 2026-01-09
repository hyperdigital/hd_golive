import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
function formatDate(timestamp) {
  if (!timestamp) {
    return '';
  }
  const date = new Date(timestamp * 1000);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  const pad = (value) => String(value).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function updateRow(row, status) {
  if (!row) {
    return;
  }
  row.classList.toggle('table-success', status === 1);
  row.classList.toggle('table-danger', status === 2);
}

function updateCheckedInfo(container, status, checkedBy, checkedTime) {
  if (!container) {
    return;
  }
  const nameEl = container.querySelector('[data-hdgolive-checked-by]');
  const timeEl = container.querySelector('[data-hdgolive-checked-time]');
  if (nameEl) {
    nameEl.textContent = status === 0 ? '' : (checkedBy || '');
  }
  if (timeEl) {
    timeEl.textContent = status === 0 ? '' : formatDate(checkedTime);
  }
}

function updateProgress(oldStatus, newStatus) {
  const progress = document.querySelector('[data-hdgolive-progress]');
  if (!progress) {
    return;
  }
  const passCountEl = progress.querySelector('[data-hdgolive-pass-count]');
  const failCountEl = progress.querySelector('[data-hdgolive-fail-count]');
  const pendingCountEl = progress.querySelector('[data-hdgolive-pending-count]');
  const totalCountEl = progress.querySelector('[data-hdgolive-total-count]');
  const total = parseInt(progress.dataset.totalCount || '0', 10);
  let passCount = parseInt(progress.dataset.passCount || '0', 10);
  let failCount = parseInt(progress.dataset.failCount || '0', 10);
  let pendingCount = parseInt(progress.dataset.pendingCount || '0', 10);

  const adjustCounts = (status, delta) => {
    if (status === 1) {
      passCount += delta;
    } else if (status === 2) {
      failCount += delta;
    } else {
      pendingCount += delta;
    }
  };

  adjustCounts(oldStatus, -1);
  adjustCounts(newStatus, 1);

  passCount = Math.max(0, Math.min(total, passCount));
  failCount = Math.max(0, Math.min(total, failCount));
  pendingCount = Math.max(0, Math.min(total, pendingCount));

  progress.dataset.passCount = String(passCount);
  progress.dataset.failCount = String(failCount);
  progress.dataset.pendingCount = String(pendingCount);

  if (passCountEl) {
    passCountEl.textContent = String(passCount);
  }
  if (failCountEl) {
    failCountEl.textContent = String(failCount);
  }
  if (pendingCountEl) {
    pendingCountEl.textContent = String(pendingCount);
  }
  if (totalCountEl) {
    totalCountEl.textContent = String(total);
  }

  const passBar = document.querySelector('[data-hdgolive-progress-pass]');
  const failBar = document.querySelector('[data-hdgolive-progress-fail]');
  const pendingBar = document.querySelector('[data-hdgolive-progress-pending]');
  if (passBar || failBar || pendingBar) {
    const passPercent = total > 0 ? Math.round((passCount / total) * 100) : 0;
    const failPercent = total > 0 ? Math.round((failCount / total) * 100) : 0;
    const pendingPercent = Math.max(0, 100 - passPercent - failPercent);
    if (passBar) {
      passBar.style.width = `${passPercent}%`;
      passBar.setAttribute('aria-valuenow', String(passPercent));
    }
    if (failBar) {
      failBar.style.width = `${failPercent}%`;
      failBar.setAttribute('aria-valuenow', String(failPercent));
    }
    if (pendingBar) {
      pendingBar.style.width = `${pendingPercent}%`;
      pendingBar.setAttribute('aria-valuenow', String(pendingPercent));
    }
  }
}

function applyFilters() {
  const statusSelect = document.querySelector('[data-hdgolive-status-select]');
  const languageSelect = document.querySelector('[data-hdgolive-language-select]');
  const statusValue = statusSelect ? statusSelect.value : 'all';
  const languageValue = languageSelect ? languageSelect.value : 'all';

  document.querySelectorAll('tr[data-hdgolive-status]').forEach((row) => {
    const statusMatch = statusValue === 'all' || row.dataset.hdgoliveStatus === statusValue;
    const languageMatch = languageValue === 'all'
      || row.dataset.hdgoliveLanguage === languageValue
      || row.dataset.hdgoliveRoot === '1';
    row.hidden = !(statusMatch && languageMatch);
  });
}

function handleToggle(select, url, updateProgressCount = false) {
  const status = select ? parseInt(select.value || '0', 10) : 0;
  const formData = new FormData();
  formData.set('status', String(status));
  Object.entries(select.dataset).forEach(([key, value]) => {
    if (key === 'status' || key === 'hdgoliveBound') {
      return;
    }
    formData.set(key, value);
  });
  const previousStatus = parseInt(select.dataset.status || '0', 10);

  new AjaxRequest(url)
    .post(formData)
    .then(async (response) => {
      const result = await response.resolve();
      if (!result?.success) {
        Notification.error('GO Live', result?.message || 'Failed to update checklist.');
        return;
      }
      updateRow(select.closest('tr'), result.status);
      updateCheckedInfo(select.closest('tr'), result.status, result.checkedBy, result.checkedTime);
      select.dataset.status = String(result.status ?? status);
      const row = select.closest('tr');
      if (row) {
        row.dataset.hdgoliveStatus = String(result.status ?? status);
        if (result.editUrl) {
          const editLink = row.querySelector('[data-hdgolive-edit-link]');
          if (editLink) {
            editLink.setAttribute('href', result.editUrl);
          }
        }
      }
      if (updateProgressCount) {
        updateProgress(previousStatus, result.status ?? status);
      }
      applyFilters();
    })
    .catch(() => {
      Notification.error('GO Live', 'Failed to update checklist.');
    });
}

function initModule() {
  const statusSelect = document.querySelector('[data-hdgolive-status-select]');
  if (statusSelect && statusSelect.dataset.hdgoliveBound !== '1') {
    statusSelect.dataset.hdgoliveBound = '1';
    statusSelect.addEventListener('change', applyFilters);
  }

  const languageSelect = document.querySelector('[data-hdgolive-language-select]');
  if (languageSelect && languageSelect.dataset.hdgoliveBound !== '1') {
    languageSelect.dataset.hdgoliveBound = '1';
    languageSelect.addEventListener('change', applyFilters);
  }

  document.querySelectorAll('.t3js-hdgolive-toggle-item select').forEach((select) => {
    if (select.dataset.status !== undefined) {
      select.value = String(select.dataset.status);
    }
    if (select.dataset.hdgoliveBound === '1') {
      return;
    }
    select.dataset.hdgoliveBound = '1';
    select.addEventListener('change', () => {
      handleToggle(select, TYPO3.settings.ajaxUrls.hd_golive_toggle_item_module);
    });
  });

  document.querySelectorAll('.t3js-hdgolive-toggle-page select').forEach((select) => {
    if (select.dataset.status !== undefined) {
      select.value = String(select.dataset.status);
    }
    if (select.dataset.hdgoliveBound === '1') {
      return;
    }
    select.dataset.hdgoliveBound = '1';
    select.addEventListener('change', () => {
      handleToggle(select, TYPO3.settings.ajaxUrls.hd_golive_toggle_page_module, true);
    });
  });
}

document.addEventListener('DOMContentLoaded', initModule);
document.addEventListener('typo3:module-loaded', initModule);
if (window.top && window.top.document) {
  window.top.document.addEventListener('typo3-module-loaded', initModule);
}
initModule();

export default {
  init: initModule,
};
