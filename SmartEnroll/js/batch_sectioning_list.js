const sectioningForm = document.getElementById('studentSectioningForm');
const saveBatchesBtn = document.getElementById('saveBatchesBtn');
const printListBtn = document.getElementById('printListBtn');
const excelListBtn = document.getElementById('excelListBtn');
const pdfListBtn = document.getElementById('pdfListBtn');
const printedAtValue = document.getElementById('printedAtValue');
const batchSelects = Array.from(document.querySelectorAll('.batch-select'));
const successPopup = document.getElementById('successPopup');
const successIcon = document.getElementById('successIcon');
const closeSuccess = document.getElementById('closeSuccess');

function hasBatchChanges() {
  return batchSelects.some((select) => {
    const original = (select.dataset.original || '').trim();
    const current = (select.value || '').trim();
    return original !== current;
  });
}

function updateSaveState() {
  if (!saveBatchesBtn) return;
  saveBatchesBtn.disabled = !hasBatchChanges();
}

function getExportTableData() {
  const table = document.querySelector('.table-wrap table');
  if (!table) return { headers: [], rows: [] };

  const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
  const rows = [];

  table.querySelectorAll('tbody tr').forEach((tr) => {
    const cells = Array.from(tr.querySelectorAll('td')).map((td, index) => {
      if (index === 5) {
        const batchValue = td.querySelector('.batch-value');
        const batchSelect = td.querySelector('.batch-select');
        if (batchValue && batchValue.textContent.trim() !== '') {
          return batchValue.textContent.trim();
        }
        if (batchSelect && batchSelect.value.trim() !== '') {
          return batchSelect.value.trim();
        }
      }

      return td.textContent.replace(/\s+/g, ' ').trim();
    });

    if (!cells.length) return;
    if (cells.length === 1 && cells[0] === '') return;
    rows.push(cells);
  });

  return { headers, rows };
}

function getExportFilename(extension) {
  const schoolYear = new URLSearchParams(window.location.search).get('school_year') || 'all_school_years';
  const gradeLevel = new URLSearchParams(window.location.search).get('grade_level') || 'all_grades';
  const safeSchoolYear = schoolYear.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');
  const safeGradeLevel = gradeLevel.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '');
  return `batch_sectioning_${safeSchoolYear || 'all_school_years'}_${safeGradeLevel || 'all_grades'}.${extension}`;
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function buildExcelMarkup(headers, rows) {
  const headerMarkup = headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('');
  const rowMarkup = rows
    .map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`)
    .join('');

  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
    th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
    th { background: #e9f2ff; font-weight: 700; }
  </style>
</head>
<body>
  <table>
    <thead><tr>${headerMarkup}</tr></thead>
    <tbody>${rowMarkup}</tbody>
  </table>
</body>
</html>`;
}

function openPrintView() {
  const table = document.querySelector('.table-wrap table');
  if (!table) return;

  const badges = Array.from(document.querySelectorAll('.filter-bar .filter-badge'))
    .map((badge) => badge.textContent.replace(/\s+/g, ' ').trim())
    .filter((badge) => !badge.toLowerCase().startsWith('batch filter:'));
  const printedAt = printedAtValue ? printedAtValue.textContent.trim() : '';
  const printMetaMarkup = badges
    .map((badge) => {
      const parts = badge.split(':');
      if (parts.length < 2) {
        return `<p>${escapeHtml(badge)}</p>`;
      }

      const label = `${parts.shift()}:`;
      const value = parts.join(':').trim();
      return `<p><strong>${escapeHtml(label)}</strong> ${escapeHtml(value)}</p>`;
    })
    .join('');
  const logoSrc = `${window.location.origin}${window.location.pathname.replace(/[^/]+$/, '')}assets/logo.png`;

  const w = window.open('', '_blank');
  if (!w) return;
  w.document.write('<html><head><title>Batch and Sectioning List</title>');
  w.document.write('<style>body{font-family:Poppins,Arial,sans-serif;padding:28px 32px;color:#1b2a41;} .print-header{display:grid;grid-template-columns:1fr auto;align-items:start;gap:20px;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #d9e2ef;} .print-title h2{margin:0 0 6px;font-size:24px;color:#19325a;} .print-title .subtitle{margin:0;font-size:13px;color:#61768f;} .print-meta{margin-top:14px;display:grid;gap:5px;} .print-meta p{margin:0;font-size:13px;color:#23364f;} .print-meta strong{font-weight:700;color:#19325a;} .print-logo{width:110px;height:auto;object-fit:contain;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #d9e2ef;padding:8px;text-align:left;font-size:12px;} th{background:#e9f2ff;color:#19325a;} .batch-edit-btn,.batch-select{display:none !important;}</style>');
  w.document.write('</head><body>');
  w.document.write('<div class="print-header">');
  w.document.write('<div class="print-title">');
  w.document.write('<h2>Batch and Sectioning List</h2>');
  w.document.write('<p class="subtitle">SMARTENROLL student batch assignment report</p>');
  w.document.write(`<div class="print-meta">${printMetaMarkup}<p><strong>Printed At:</strong> ${escapeHtml(printedAt || '-')}</p></div>`);
  w.document.write('</div>');
  w.document.write(`<img src="${escapeHtml(logoSrc)}" alt="SmartEnroll Logo" class="print-logo">`);
  w.document.write('</div>');
  w.document.write(table.outerHTML);
  w.document.write('</body></html>');
  w.document.close();
  w.focus();
  w.print();
  w.close();
}

function showSuccessPopup() {
  if (!successPopup || !successIcon) return;
  successPopup.classList.add('active');
  successIcon.classList.remove('show-check');
  setTimeout(() => {
    successIcon.classList.add('show-check');
  }, 600);
}

function hideSuccessPopup() {
  if (!successPopup || !successIcon) return;
  successPopup.classList.remove('active');
  successIcon.classList.remove('show-check');
}

if (closeSuccess) {
  closeSuccess.addEventListener('click', hideSuccessPopup);
}

if (successPopup) {
  successPopup.addEventListener('click', (e) => {
    if (e.target === successPopup) {
      hideSuccessPopup();
    }
  });
  if (successPopup.dataset.autoShow === '1') {
    showSuccessPopup();
  }
}

document.querySelectorAll('.batch-cell').forEach((cell) => {
  const display = cell.querySelector('.batch-display');
  const valueText = cell.querySelector('.batch-value');
  const editBtn = cell.querySelector('.batch-edit-btn');
  const select = cell.querySelector('.batch-select');

  if (!select) {
    return;
  }

  if (editBtn) {
    editBtn.addEventListener('click', () => {
      cell.classList.remove('is-locked');
      cell.classList.add('is-editing');
      display.classList.add('is-hidden');
      select.classList.remove('is-hidden');
      select.focus();
    });
  }

  select.addEventListener('change', () => {
    if (select.value === '') {
      updateSaveState();
      return;
    }

    valueText.textContent = select.value;
    cell.classList.remove('is-editing');
    cell.classList.add('is-locked');
    display.classList.remove('is-hidden');
    select.classList.add('is-hidden');
    updateSaveState();
  });
});

if (sectioningForm) {
  sectioningForm.addEventListener('submit', () => {
    document.querySelectorAll('.batch-cell').forEach((cell) => {
      const display = cell.querySelector('.batch-display');
      const valueText = cell.querySelector('.batch-value');
      const select = cell.querySelector('.batch-select');

      if (!display || !valueText || !select) {
        return;
      }

      if (select.value !== '') {
        valueText.textContent = select.value;
        cell.classList.remove('is-editing');
        cell.classList.add('is-locked');
        display.classList.remove('is-hidden');
        select.classList.add('is-hidden');
      }
    });
    if (saveBatchesBtn) {
      saveBatchesBtn.disabled = true;
    }
  });
}

if (printListBtn) {
  printListBtn.addEventListener('click', () => {
    const now = new Date();
    if (printedAtValue) {
      printedAtValue.textContent = now.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }
    openPrintView();
  });
}

if (excelListBtn) {
  excelListBtn.addEventListener('click', () => {
    const { headers, rows } = getExportTableData();
    if (!headers.length || !rows.length) return;

    const excelMarkup = buildExcelMarkup(headers, rows);
    const blob = new Blob(['\ufeff', excelMarkup], {
      type: 'application/vnd.ms-excel;charset=utf-8;'
    });

    downloadBlob(blob, getExportFilename('xls'));
  });
}

if (pdfListBtn) {
  pdfListBtn.addEventListener('click', () => {
    const { headers, rows } = getExportTableData();
    if (!headers.length || !rows.length) return;

    if (window.jspdf && window.jspdf.jsPDF) {
      const doc = new window.jspdf.jsPDF('l', 'pt', 'a4');
      doc.setFontSize(14);
      doc.text('Batch and Sectioning List', 40, 40);
      if (typeof doc.autoTable === 'function') {
        doc.autoTable({
          head: [headers],
          body: rows,
          startY: 60,
          styles: { fontSize: 9, cellPadding: 4 }
        });
      }
      doc.save(getExportFilename('pdf'));
    } else {
      openPrintView();
    }
  });
}

updateSaveState();
