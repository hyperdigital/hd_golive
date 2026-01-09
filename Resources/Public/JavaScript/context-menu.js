import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';

class GoLiveContextMenu {
  static setGoLivePageStatus(_table, uid, data) {
    const session = parseInt(data.session ?? '0', 10);
    const page = parseInt(uid, 10);
    const status = parseInt(data.status ?? '0', 10);

    if (!session || !page) {
      Notification.error('GO Live', 'Missing session or page.');
      return;
    }

    const formData = new FormData();
    formData.append('session', String(session));
    formData.append('page', String(page));
    formData.append('status', String(status));

    new AjaxRequest(TYPO3.settings.ajaxUrls.hd_golive_toggle_page)
      .post(formData)
      .then(async (response) => {
        const result = await response.resolve();
        if (result?.success) {
          document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'));
        } else {
          Notification.error('GO Live', result?.message || 'Failed to update checklist.');
        }
      })
      .catch(() => {
        Notification.error('GO Live', 'Failed to update checklist.');
      });
  }
}

export default GoLiveContextMenu;
