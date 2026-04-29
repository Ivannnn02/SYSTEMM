if (document.body?.dataset.printMode === '1') {
  let printWindowClosed = false;
  const closePrintWindow = () => {
    if (printWindowClosed) return;
    printWindowClosed = true;
    window.setTimeout(() => window.close(), 120);
  };

  window.addEventListener('afterprint', () => closePrintWindow(), { once: true });
  window.addEventListener('focus', () => window.setTimeout(() => closePrintWindow(), 350), { once: true });
  window.setTimeout(() => window.print(), 150);
}

const studentPrintButton = document.querySelector('.student-print-btn');
if (studentPrintButton) {
  studentPrintButton.addEventListener('click', async (event) => {
    event.preventDefault();
    const printUrl = studentPrintButton.dataset.printUrl || studentPrintButton.getAttribute('href');
    if (!printUrl) return;

    studentPrintButton.classList.add('loading');
    try {
      const resp = await fetch(printUrl, { credentials: 'same-origin' });
      if (!resp.ok) throw new Error('Failed to load print view');
      const html = await resp.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const printCard = doc.querySelector('.student-list-card') || doc.body;

      const oldCard = document.querySelector('.student-list-card');
      if (!oldCard) {
        window.location.href = printUrl; // fallback
        return;
      }

      const originalInner = oldCard.innerHTML;
      const originalBodyClass = document.body.className;

      document.body.classList.add('student-list-print-page');
      oldCard.innerHTML = printCard.innerHTML;
      window.scrollTo(0, 0);

      const restore = () => {
        try { oldCard.innerHTML = originalInner; } catch (e) { console.error(e); }
        document.body.className = originalBodyClass;
        studentPrintButton.classList.remove('loading');
        window.removeEventListener('afterprint', afterPrintHandler);
        clearTimeout(restoreTimeout);
      };

      const afterPrintHandler = () => restore();
      window.addEventListener('afterprint', afterPrintHandler);

      const restoreTimeout = setTimeout(restore, 10000);
      window.print();
    } catch (err) {
      console.error(err);
      window.location.href = printUrl; // fallback
    } finally {
      studentPrintButton.classList.remove('loading');
    }
  });
}

const studentStatusFilter = document.getElementById('studentStatusFilter');
if (studentStatusFilter) {
  studentStatusFilter.addEventListener('change', () => {
    studentStatusFilter.form?.requestSubmit();
  });
}

document.querySelectorAll('.action-btn.delete').forEach((btn) => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const href = btn.getAttribute('href');
    const modal = document.getElementById('deleteModal');
    const confirmBtn = document.getElementById('confirmDelete');
    if (modal && confirmBtn) {
      confirmBtn.setAttribute('href', href);
      modal.classList.add('active');
      const iconBox = document.getElementById('deleteIconBox');
      if (iconBox) {
        iconBox.classList.remove('show-alert');
        setTimeout(() => {
          iconBox.classList.add('show-alert');
        }, 400);
      }
    }
  });
});

const deleteModal = document.getElementById('deleteModal');
const cancelDelete = document.getElementById('cancelDelete');
if (deleteModal && cancelDelete) {
  cancelDelete.addEventListener('click', () => {
    deleteModal.classList.remove('active');
  });
}
