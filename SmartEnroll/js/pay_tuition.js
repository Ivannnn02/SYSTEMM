const studentSearch = document.getElementById('studentSearch');
if (studentSearch) {
  const searchForm = studentSearch.closest('.search-form');
  if (!searchForm) {
    studentSearch.addEventListener('input', () => {
      const query = studentSearch.value.toLowerCase();
      document.querySelectorAll('#payStudentList .student-pick-card').forEach((card) => {
        const haystack = (card.getAttribute('data-search') || '').toLowerCase();
        card.style.display = haystack.includes(query) ? '' : 'none';
      });
    });
  }
}

const paymentCatalog = document.getElementById('paymentCatalog');
const selectedPaymentTable = document.getElementById('selectedPaymentTable');
const selectedPaymentEmpty = document.getElementById('selectedPaymentEmpty');
const selectedPaymentRowTemplate = document.getElementById('selectedPaymentRowTemplate');
const paymentItemsJson = document.getElementById('paymentItemsJson');
const previewEmailItemsInput = document.getElementById('previewEmailItemsJson');
const paymentSubmitModeInput = document.getElementById('paymentSubmitMode');
const paymentForm = document.getElementById('paymentBuilderForm');
const paymentDateInput = document.getElementById('paymentDateInput');
const receiptNumberInput = document.getElementById('receiptNumberInput');
const saveInvoiceButton = document.getElementById('saveInvoiceButton');
const paymentPreview = document.getElementById('paymentPreview');
const totalPaidPreview = document.getElementById('totalPaidPreview');
const lessAmountPaidPreview = document.getElementById('lessAmountPaidPreview');
const balanceAfterPreview = document.getElementById('balanceAfterPreview');
const remainingBalanceDisplay = document.getElementById('remainingBalanceDisplay');
const receiptAddTrigger = document.getElementById('receiptAddTrigger');
const receiptAddRow = document.querySelector('.receipt-add-row');
const invoiceEmailTotal = document.getElementById('invoiceEmailTotal');
const invoiceEmailDueDate = document.getElementById('invoiceEmailDueDate');
const invoiceEmailNumber = document.getElementById('invoiceEmailNumber');
const invoiceEmailBodyNumber = document.getElementById('invoiceEmailBodyNumber');
const invoiceEmailBodyAmount = document.getElementById('invoiceEmailBodyAmount');
const invoiceEmailBodyOutstanding = document.getElementById('invoiceEmailBodyOutstanding');
const invoiceEmailBodyDueDate = document.getElementById('invoiceEmailBodyDueDate');
const invoiceEmailItems = document.getElementById('invoiceEmailItems');
const invoiceEmailPrintTrigger = document.getElementById('invoiceEmailPrintTrigger');
const invoiceEmailSendTrigger = document.getElementById('invoiceEmailSendTrigger');
const selectedInvoicePrintTrigger = document.getElementById('selectedInvoicePrintTrigger');
const gmailHistoryRows = () => Array.from(document.querySelectorAll('#gmail-send-history [data-history-fill]'));

function buildInvoiceEmailCatalogMarkup() {
  const invoiceEmailCatalogElement = document.getElementById('invoiceEmailCatalog');
  if (invoiceEmailCatalogElement) {
    return invoiceEmailCatalogElement.outerHTML;
  }

  if (!paymentCatalog) {
    return '';
  }

  const rows = Array.from(paymentCatalog.querySelectorAll('.catalog-row[data-option]'));
  if (!rows.length) {
    return '';
  }

  return '<div class="payment-catalog-card invoice-email-catalog-menu" id="invoiceEmailCatalog">'
    + rows.map((row) => {
      const option = String(row.dataset.option || '');
      const dataLabel = String(row.dataset.displayLabel || '').trim();
      const strongLabel = String(row.querySelector('.receipt-catalog-copy strong')?.textContent || '').trim();
      const displayLabel = dataLabel || strongLabel || option;
      const defaultAmount = String(row.dataset.default || '0');
      const disabled = row.dataset.disabled === '1';
      return '<div class="catalog-row invoice-email-catalog-row' + (disabled ? ' is-disabled' : '') + '"'
        + ' data-option="' + escapeHtml(option) + '"'
        + ' data-display-label="' + escapeHtml(displayLabel) + '"'
        + ' data-default="' + escapeHtml(defaultAmount) + '"'
        + ' data-disabled="' + (disabled ? '1' : '0') + '">'
        + '<button type="button" class="catalog-add-btn" aria-label="Add ' + escapeHtml(option) + '"' + (disabled ? ' disabled' : '') + '>'
        + '<i class="fa-solid ' + (disabled ? 'fa-check' : 'fa-plus') + '"></i>'
        + '</button>'
        + '<div class="receipt-catalog-copy">'
        + '<strong>' + escapeHtml(displayLabel) + '</strong>'
        + '<span>' + escapeHtml(defaultAmount) + '</span>'
        + '</div>'
        + '</div>';
    }).join('')
    + '</div>';
}

function buildInvoiceEmailAddControl() {
  return '<button type="button" class="invoice-email-add-trigger" aria-label="Add payment item" aria-haspopup="true" aria-expanded="false">'
    + '<i class="fa-solid fa-plus"></i>'
    + '<span>Add payment item</span>'
    + '</button>'
    + buildInvoiceEmailCatalogMarkup();
}

function renderInvoiceEmailEmptyState() {
  return '<div class="invoice-email-line-item is-empty">'
    + '<div class="invoice-email-empty-action">'
    + '<span>No billing item added yet</span>'
    + buildInvoiceEmailAddControl()
    + '</div>'
    + '<strong>0.00</strong>'
    + '</div>';
}

function renderInvoiceEmailAddRow() {
  return '<div class="invoice-email-line-item invoice-email-line-item-add">'
    + '<div class="invoice-email-empty-action">'
    + buildInvoiceEmailAddControl()
    + '</div>'
    + '<strong></strong>'
    + '</div>';
}

function renderInvoiceEmailPreviewRow(item, isRemovable = false) {
  const option = String(item.option || item.label || item.displayLabel || 'Payment Item');
  const label = String(item.label || item.displayLabel || option || 'Payment Item');
  const amount = parseAmount(item.amount);

  return '<div class="invoice-email-line-item' + (isRemovable ? ' is-removable' : '') + '">'
    + '<div class="invoice-email-line-copy">'
    + (isRemovable
      ? '<button type="button" class="invoice-email-remove-btn" data-option="' + escapeHtml(option) + '" aria-label="Remove ' + escapeHtml(label) + ' from top preview">'
        + '<i class="fa-solid fa-xmark"></i>'
        + '</button>'
      : '')
    + '<span>' + escapeHtml(label) + '</span>'
    + '</div>'
    + '<strong>' + escapeHtml(formatInvoiceNumber(amount)) + '</strong>'
    + '</div>';
}

function formatPHP(value) {
  return 'PHP ' + Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatInvoiceNumber(value) {
  return Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function parseAmount(value) {
  return parseFloat(String(value || '').replace(/[^0-9.\-]/g, '')) || 0;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatPreviewDate(value) {
  const raw = String(value || '').trim();
  if (!raw) {
    return 'N/A';
  }

  const parsedDate = new Date(raw + 'T00:00:00');
  if (Number.isNaN(parsedDate.getTime())) {
    return raw;
  }

  return parsedDate.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}

function showToast(message) {
  let container = document.getElementById('smartenrollToastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'smartenrollToastContainer';
    container.className = 'sr-toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'sr-toast';
  toast.textContent = message;
  container.appendChild(toast);

  window.setTimeout(() => {
    toast.classList.add('is-hiding');
    window.setTimeout(() => {
      toast.remove();
      if (!container.hasChildNodes()) {
        container.remove();
      }
    }, 240);
  }, 2400);
}

const PRINT_TARGET_ATTRIBUTE = 'data-print-target';
const PRINT_TARGET_SELECTED_INVOICE = 'selected-invoice';
const PRINT_TARGET_EMAIL_PREVIEW = 'invoice-email';

function clearPrintTarget() {
  document.body?.removeAttribute(PRINT_TARGET_ATTRIBUTE);
}

function triggerSectionPrint(target) {
  if (!document.body) {
    return;
  }

  document.body.setAttribute(PRINT_TARGET_ATTRIBUTE, target);
  void document.body.offsetWidth;
  window.print();
}

selectedInvoicePrintTrigger?.addEventListener('click', () => {
  const previewShell = document.querySelector('#receipt-preview .receipt-preview');
  if (!previewShell) {
    window.alert('The saved invoice preview is not ready to print yet.');
    return;
  }

  triggerSectionPrint(PRINT_TARGET_SELECTED_INVOICE);
});

window.addEventListener('afterprint', clearPrintTarget);

if (typeof window.matchMedia === 'function') {
  const printMedia = window.matchMedia('print');
  const handlePrintMediaChange = (event) => {
    if (!event.matches) {
      clearPrintTarget();
    }
  };

  if (typeof printMedia.addEventListener === 'function') {
    printMedia.addEventListener('change', handlePrintMediaChange);
  } else if (typeof printMedia.addListener === 'function') {
    printMedia.addListener(handlePrintMediaChange);
  }
}

if (paymentCatalog && selectedPaymentTable && selectedPaymentRowTemplate) {
  const fullTuition = parseAmount(selectedPaymentTable.dataset.fullTuition || '0');
  const remainingBeforePayment = parseAmount(selectedPaymentTable.dataset.remaining || String(fullTuition));
  const previewEmailItemsState = [];
  const getRows = () => Array.from(selectedPaymentTable.querySelectorAll('.selected-payment-row[data-option]'));
  const getCatalogRows = () => Array.from(paymentCatalog.querySelectorAll('.catalog-row[data-option]'));
  const getCatalogRowByOption = (option) => getCatalogRows().find((row) => row.dataset.option === option) || null;
  const getBuilderTotal = () => getRows().reduce((sum, row) => sum + Math.max(getRowAmount(row), 0), 0);
  const hasPreviewEmailItems = () => previewEmailItemsState.length > 0;
  const getPreviewEmailItemsTotal = () => previewEmailItemsState.reduce((sum, item) => sum + Math.max(parseAmount(item.amount), 0), 0);
  const getPreviewPayloadItems = () => {
    if (hasPreviewEmailItems()) {
      return previewEmailItemsState.map((item) => ({
        option: item.option || '',
        label: item.label || item.displayLabel || item.option || 'Payment Item',
        amount: Number(parseAmount(item.amount).toFixed(2))
      }));
    }

    return getRows().map((row) => ({
      option: row.dataset.option || '',
      label: row.dataset.displayLabel || row.dataset.option || 'Payment Item',
      amount: Number(getRowAmount(row).toFixed(2))
    })).filter((item) => item.option && item.amount > 0);
  };
  const getPreviewEmailDisplayItems = () => {
    if (hasPreviewEmailItems()) {
      return previewEmailItemsState.map((item) => ({
        option: item.option || '',
        label: item.label || item.displayLabel || item.option || 'Payment Item',
        amount: parseAmount(item.amount)
      }));
    }

    return getRows().map((row) => ({
      option: row.dataset.option || '',
      label: row.dataset.displayLabel || row.dataset.option || 'Payment Item',
      amount: getRowAmount(row)
    }));
  };
  const syncPreviewEmailItemsInput = () => {
    if (!previewEmailItemsInput) {
      return;
    }

    const previewItems = getPreviewPayloadItems();
    previewEmailItemsInput.value = previewItems.length ? JSON.stringify(previewItems) : '';
  };
  const getInvoiceEmailCatalog = () => document.getElementById('invoiceEmailCatalog');
  const setInvoiceEmailCatalogOpen = (isOpen) => {
    const catalog = getInvoiceEmailCatalog();
    const trigger = invoiceEmailItems?.querySelector('.invoice-email-add-trigger');
    if (catalog) {
      catalog.classList.toggle('is-open', isOpen);
    }
    if (trigger) {
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
  };
  const syncEmailPreview = (total) => {
    const displayItems = getPreviewEmailDisplayItems();
    const dueDateText = formatPreviewDate(paymentDateInput?.value || '');
    const invoiceNumberText = String(receiptNumberInput?.value || 'N/A').trim() || 'N/A';
    const previewTotal = hasPreviewEmailItems() ? getPreviewEmailItemsTotal() : total;
    const isCustomPreview = hasPreviewEmailItems();
    const formattedTotal = formatPHP(previewTotal);

    if (invoiceEmailTotal) {
      invoiceEmailTotal.textContent = formatInvoiceNumber(previewTotal);
    }
    if (invoiceEmailDueDate) {
      invoiceEmailDueDate.textContent = 'Due ' + dueDateText;
    }
    if (invoiceEmailNumber) {
      invoiceEmailNumber.textContent = invoiceNumberText;
    }
    if (invoiceEmailBodyNumber) {
      invoiceEmailBodyNumber.textContent = invoiceNumberText;
    }
    if (invoiceEmailBodyAmount) {
      invoiceEmailBodyAmount.textContent = formattedTotal;
    }
    if (invoiceEmailBodyOutstanding) {
      invoiceEmailBodyOutstanding.textContent = formattedTotal;
    }
    if (invoiceEmailBodyDueDate) {
      invoiceEmailBodyDueDate.textContent = dueDateText;
    }
    if (invoiceEmailItems) {
      if (!displayItems.length) {
        invoiceEmailItems.innerHTML = renderInvoiceEmailEmptyState();
        syncPreviewEmailItemsInput();
        return;
      }

      const itemRows = displayItems.map((item) => renderInvoiceEmailPreviewRow(item, isCustomPreview));

      if (isCustomPreview) {
        itemRows.push(renderInvoiceEmailAddRow());
      }

      invoiceEmailItems.innerHTML = itemRows.join('');
    }

    syncPreviewEmailItemsInput();
  };
  const setCatalogOpen = (isOpen) => {
    paymentCatalog.classList.toggle('is-open', isOpen);
    if (receiptAddTrigger) {
      receiptAddTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    if (isOpen) {
      setInvoiceEmailCatalogOpen(false);
    }
  };

  const getRowAmount = (row) => {
    return parseAmount(row.dataset.amount || '0');
  };

  const getUnitPriceDisplay = (row) => row.querySelector('.selected-unit-price-display');
  const getTuitionManualWrap = (row) => row.querySelector('.tuition-manual-wrap');
  const getTuitionManualInput = (row) => row.querySelector('.tuition-manual-input');

  const setRowAmount = (row, amount, options = {}) => {
    const { skipInputSync = false } = options;
    const normalizedAmount = Math.max(parseAmount(amount), 0);
    row.dataset.amount = normalizedAmount.toFixed(2);
    const suggested = getUnitPriceDisplay(row);
    const lineAmount = row.querySelector('.selected-row-amount');
    const manualInput = getTuitionManualInput(row);
    if (suggested) {
      suggested.textContent = formatInvoiceNumber(normalizedAmount);
    }
    if (lineAmount) {
      lineAmount.textContent = formatInvoiceNumber(normalizedAmount);
    }
    if (manualInput && !skipInputSync && document.activeElement !== manualInput) {
      manualInput.value = formatInvoiceNumber(normalizedAmount);
    }
  };

  const setRowStatus = (row, message) => {
    const status = row.querySelector('.selected-row-status');
    if (status) {
      status.textContent = message;
    }
  };

  const getMaxTuitionAllowed = (tuitionRow) => {
    const otherRowsTotal = getRows().reduce((sum, row) => {
      if (row === tuitionRow) {
        return sum;
      }
      return sum + Math.max(getRowAmount(row), 0);
    }, 0);

    return Math.max(remainingBeforePayment - otherRowsTotal, 0);
  };

  const enableTuitionManualInput = (row) => {
    const manualWrap = getTuitionManualWrap(row);
    const manualInput = getTuitionManualInput(row);
    const display = getUnitPriceDisplay(row);
    if (!manualWrap || !manualInput || !display) {
      return;
    }

    manualWrap.classList.remove('is-hidden');
    display.classList.add('is-hidden');
    manualInput.value = formatInvoiceNumber(getRowAmount(row));

    manualInput.addEventListener('focus', () => {
      manualInput.select();
    });

    manualInput.addEventListener('input', () => {
      const maxAllowed = getMaxTuitionAllowed(row);
      const typedAmount = parseAmount(manualInput.value);
      const normalizedAmount = Math.min(Math.max(typedAmount, 0), maxAllowed);
      setRowAmount(row, normalizedAmount, { skipInputSync: true });
      syncTotals();
    });

    manualInput.addEventListener('blur', () => {
      const maxAllowed = getMaxTuitionAllowed(row);
      const normalizedAmount = Math.min(Math.max(getRowAmount(row), 0), maxAllowed);
      setRowAmount(row, normalizedAmount);
    });
  };

  const syncEmptyState = () => {
    const hasRows = getRows().length > 0;
    if (selectedPaymentEmpty) {
      selectedPaymentEmpty.style.display = hasRows ? 'none' : 'table-row';
    }
  };

  const syncTotals = () => {
    getRows().forEach((row) => {
      if (row.dataset.option !== 'Tuition Fee') {
        return;
      }

      const maxAllowed = getMaxTuitionAllowed(row);
      const currentAmount = getRowAmount(row);
      if (currentAmount > maxAllowed) {
        setRowAmount(row, maxAllowed);
      }
      setRowStatus(
        row,
        maxAllowed > 0
          ? 'Enter any amount up to the remaining balance'
          : 'No remaining balance left'
      );
    });

    let total = 0;
    getRows().forEach((row) => {
      const amount = getRowAmount(row);
      total += Math.max(amount, 0);
    });

    const remaining = Math.max(remainingBeforePayment - total, 0);
    if (paymentPreview) {
      paymentPreview.textContent = formatInvoiceNumber(total);
    }
    if (totalPaidPreview) {
      totalPaidPreview.textContent = formatInvoiceNumber(total);
    }
    if (lessAmountPaidPreview) {
      lessAmountPaidPreview.textContent = formatInvoiceNumber(total);
    }
    if (remainingBalanceDisplay) {
      remainingBalanceDisplay.textContent = formatPHP(remaining);
    }
    if (balanceAfterPreview) {
      balanceAfterPreview.textContent = formatInvoiceNumber(remaining);
    }

    syncEmailPreview(total);
  };

  const clearBuilderRows = () => {
    getRows().forEach((row) => row.remove());
    previewEmailItemsState.splice(0, previewEmailItemsState.length);
    syncEmptyState();
  };

  const setActiveHistoryRow = (activeRow) => {
    gmailHistoryRows().forEach((row) => {
      row.classList.toggle('active-history-row', row === activeRow);
    });
  };

  const getHistoryItemLabel = (item, option) => {
    const explicitLabel = String(item.display_label || item.label || '').trim();
    if (explicitLabel) {
      return explicitLabel;
    }

    const catalogRow = getCatalogRowByOption(option);
    const catalogLabel = String(catalogRow?.dataset.displayLabel || '').trim();
    return catalogLabel || option;
  };

  const addSelectedRow = (option, defaultAmount, displayLabel) => {
    const existingRow = getRows().find((row) => row.dataset.option === option);
    if (existingRow) {
      return existingRow;
    }

    const hasTuitionFee = getRows().some((row) => row.dataset.option === 'Tuition Fee');
    const hasMonthlyPayment = getRows().some((row) => row.dataset.option === 'Monthly Payment');
    if ((option === 'Tuition Fee' && hasMonthlyPayment) || (option === 'Monthly Payment' && hasTuitionFee)) {
      showToast('Choose either Tuition Fee or Monthly Payment only, not both.');
      return null;
    }

    const fragment = selectedPaymentRowTemplate.content.cloneNode(true);
    const row = fragment.querySelector('.selected-payment-row');
    const name = row.querySelector('.selected-item-name');
    const removeBtn = row.querySelector('.remove-selected-btn');

    row.dataset.option = option;
    const effectiveLabel = displayLabel || option;
    row.dataset.displayLabel = effectiveLabel;
    row.dataset.label = effectiveLabel;
    name.textContent = effectiveLabel;
    if (option === 'Tuition Fee') {
      const maxAllowed = getMaxTuitionAllowed(row);
      if (maxAllowed <= 0) {
        showToast('No remaining balance available for Tuition Fee.');
        return null;
      }

      setRowAmount(row, Math.min(defaultAmount, maxAllowed));
      setRowStatus(row, 'Enter any amount up to the remaining balance');
      enableTuitionManualInput(row);
    } else {
      setRowAmount(row, defaultAmount);
      setRowStatus(row, 'Fixed brochure amount');
    }

    removeBtn.addEventListener('click', () => {
      row.remove();
      syncEmptyState();
      syncTotals();
    });

    if (receiptAddRow) {
      selectedPaymentTable.insertBefore(fragment, receiptAddRow);
    } else if (selectedPaymentEmpty) {
      selectedPaymentTable.insertBefore(fragment, selectedPaymentEmpty);
    } else {
      selectedPaymentTable.appendChild(fragment);
    }
    setCatalogOpen(false);
    setInvoiceEmailCatalogOpen(false);
    syncEmptyState();
    syncTotals();
    return row;
  };

  const loadHistoryIntoBuilder = (payload, activeRow) => {
    if (!payload || typeof payload !== 'object') {
      return;
    }

    const fallbackAmount = Math.max(parseAmount(payload.amount_paid), 0);
    const historyItems = Array.isArray(payload.items)
      ? payload.items.filter((item) => item && typeof item === 'object')
      : [];
    const normalizedItems = historyItems.length
      ? historyItems
      : (fallbackAmount > 0 ? [{
        option: 'Tuition Fee',
        display_label: String(getCatalogRowByOption('Tuition Fee')?.dataset.displayLabel || 'Tuition Fee'),
        amount: fallbackAmount
      }] : []);

    clearBuilderRows();

    const paymentDate = String(payload.payment_date || '').trim();
    if (paymentDateInput && paymentDate) {
      paymentDateInput.value = paymentDate;
    }

    const receiptNo = String(payload.receipt_no || '').trim();
    if (receiptNumberInput && receiptNo) {
      receiptNumberInput.value = receiptNo;
    }

    normalizedItems.forEach((item) => {
      const option = String(item.option || '').trim();
      const amount = Math.max(parseAmount(item.amount), 0);
      if (!option || amount <= 0) {
        return;
      }

      const displayLabel = getHistoryItemLabel(item, option);
      const row = addSelectedRow(option, amount, displayLabel);
      if (!row) {
        return;
      }

      setRowAmount(row, amount);
      setRowStatus(
        row,
        option === 'Tuition Fee'
          ? 'Enter any amount up to the remaining balance'
          : 'Loaded from history'
      );
    });

    syncTotals();
    syncEmptyState();
    setActiveHistoryRow(activeRow);
    paymentForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    showToast('Invoice details loaded into the form.');
  };

  const addPreviewEmailItem = (option, defaultAmount, displayLabel) => {
    const existingPreviewItem = previewEmailItemsState.find((item) => item.option === option);
    if (existingPreviewItem) {
      showToast('This payment item is already added in the top preview.');
      return;
    }

    const hasPreviewTuitionFee = previewEmailItemsState.some((item) => item.option === 'Tuition Fee');
    const hasPreviewMonthlyPayment = previewEmailItemsState.some((item) => item.option === 'Monthly Payment');
    if ((option === 'Tuition Fee' && hasPreviewMonthlyPayment) || (option === 'Monthly Payment' && hasPreviewTuitionFee)) {
      showToast('Choose either Tuition Fee or Monthly Payment only, not both.');
      return;
    }

    let amount = Math.max(defaultAmount, 0);
    if (option === 'Tuition Fee') {
      amount = Math.min(amount, remainingBeforePayment);
    }

    if (amount <= 0) {
      showToast('No valid amount is available for this payment item.');
      return;
    }

    previewEmailItemsState.push({
      option,
      label: displayLabel || option,
      displayLabel: displayLabel || option,
      amount: amount.toFixed(2)
    });

    setInvoiceEmailCatalogOpen(false);
    syncEmailPreview(getBuilderTotal());
  };

  const removePreviewEmailItem = (option) => {
    const targetOption = String(option || '').trim();
    if (!targetOption) {
      return;
    }

    const previewIndex = previewEmailItemsState.findIndex((item) => item.option === targetOption);
    if (previewIndex === -1) {
      return;
    }

    previewEmailItemsState.splice(previewIndex, 1);
    setInvoiceEmailCatalogOpen(false);
    syncEmailPreview(getBuilderTotal());
  };

  receiptAddTrigger?.addEventListener('click', (event) => {
    event.stopPropagation();
    if (receiptAddTrigger.disabled) {
      return;
    }

    setCatalogOpen(!paymentCatalog.classList.contains('is-open'));
  });

  const bindCatalogRows = (catalogRoot) => {
    catalogRoot.querySelectorAll('.catalog-row[data-option]').forEach((catalogRow) => {
      const button = catalogRow.querySelector('.catalog-add-btn');
      const handleCatalogAdd = () => {
        if (catalogRow.dataset.disabled === '1') {
          return;
        }

        const option = catalogRow.dataset.option || '';
        const displayLabel = catalogRow.dataset.displayLabel || option;
        const defaultAmount = parseAmount(catalogRow.dataset.default || '0');
        if (!option) {
          return;
        }
        addSelectedRow(option, defaultAmount, displayLabel);
      };

      button?.addEventListener('click', (event) => {
        event.stopPropagation();
        handleCatalogAdd();
      });

      catalogRow.addEventListener('click', handleCatalogAdd);
    });
  };

  bindCatalogRows(paymentCatalog);

  gmailHistoryRows().forEach((historyRow) => {
    historyRow.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      if (target.closest('.table-send-form, .table-send-btn, button, input, select, textarea')) {
        return;
      }

      const link = target.closest('a.history-link');
      if (link && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) {
        return;
      }

      const rawPayload = historyRow.dataset.historyFill || '';
      if (!rawPayload) {
        return;
      }

      if (link) {
        event.preventDefault();
      }

      let payload = null;
      try {
        payload = JSON.parse(rawPayload);
      } catch (error) {
        payload = null;
      }

      if (!payload) {
        showToast('This history row could not be loaded.');
        return;
      }

      loadHistoryIntoBuilder(payload, historyRow);
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (paymentCatalog.classList.contains('is-open')) {
      if (!paymentCatalog.contains(target) && !receiptAddTrigger?.contains(target)) {
        setCatalogOpen(false);
      }
    }

    const previewCatalog = getInvoiceEmailCatalog();
    const previewTrigger = invoiceEmailItems?.querySelector('.invoice-email-add-trigger');
    if (previewCatalog?.classList.contains('is-open')) {
      if (!previewCatalog.contains(target) && !previewTrigger?.contains(target)) {
        setInvoiceEmailCatalogOpen(false);
      }
    }
  });

  invoiceEmailItems?.addEventListener('click', (event) => {
    const removeButton = event.target.closest('.invoice-email-remove-btn');
    if (removeButton) {
      event.preventDefault();
      removePreviewEmailItem(removeButton.dataset.option || '');
      return;
    }

    const addButton = event.target.closest('.invoice-email-add-trigger');
    if (!addButton) {
      return;
    }

    event.preventDefault();
    const previewCatalog = getInvoiceEmailCatalog();
    if (!previewCatalog) {
      showToast('No payment item is available to add right now.');
      return;
    }

    setCatalogOpen(false);
    setInvoiceEmailCatalogOpen(!previewCatalog.classList.contains('is-open'));
  });

  invoiceEmailItems?.addEventListener('click', (event) => {
    const catalogRow = event.target.closest('.invoice-email-catalog-row[data-option]');
    if (!catalogRow) {
      return;
    }

    if (catalogRow.dataset.disabled === '1') {
      event.preventDefault();
      return;
    }

    const option = catalogRow.dataset.option || '';
    const displayLabel = catalogRow.dataset.displayLabel || option;
    const defaultAmount = parseAmount(catalogRow.dataset.default || '0');
    if (!option) {
      return;
    }

    event.preventDefault();
    addPreviewEmailItem(option, defaultAmount, displayLabel);
  });

  invoiceEmailItems?.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    setInvoiceEmailCatalogOpen(false);
  });

  saveInvoiceButton?.addEventListener('click', () => {
    if (paymentSubmitModeInput) {
      paymentSubmitModeInput.value = 'save';
    }
  });

  invoiceEmailSendTrigger?.addEventListener('click', () => {
    const previewItems = getPreviewPayloadItems();
    if (!previewItems.length) {
      window.alert('Please add at least one payment item in the top preview before sending.');
      return;
    }

    if (paymentSubmitModeInput) {
      paymentSubmitModeInput.value = 'preview_send';
    }
    syncPreviewEmailItemsInput();
    paymentForm?.requestSubmit();
  });

  invoiceEmailPrintTrigger?.addEventListener('click', () => {
    const previewShell = document.querySelector('.invoice-email-preview-panel .invoice-email-shell');
    if (!previewShell) {
      window.alert('The invoice preview is not ready to print yet.');
      return;
    }

    setInvoiceEmailCatalogOpen(false);
    triggerSectionPrint(PRINT_TARGET_EMAIL_PREVIEW);
  });

  const initialPreviewCatalog = getInvoiceEmailCatalog();
  if (initialPreviewCatalog) {
    setInvoiceEmailCatalogOpen(false);
  }

  paymentForm?.addEventListener('submit', (event) => {
    const builderRows = getRows().map((row) => {
      const option = row.dataset.option || '';
      const amount = parseAmount(row.dataset.amount || '0');
      return {
        option,
        amount
      };
    }).filter((row) => row.option && row.amount > 0);
    const previewRows = getPreviewPayloadItems();
    const submitMode = paymentSubmitModeInput?.value === 'preview_send' ? 'preview_send' : 'save';
    const activeRows = submitMode === 'preview_send' ? previewRows : builderRows;

    if (!activeRows.length) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      window.alert(submitMode === 'preview_send'
        ? 'Please add at least one payment item in the top preview before sending.'
        : 'Please add at least one payment row.');
      return;
    }

    const hasTuitionFee = activeRows.some((row) => row.option === 'Tuition Fee');
    const hasMonthlyPayment = activeRows.some((row) => row.option === 'Monthly Payment');
    if (hasTuitionFee && hasMonthlyPayment) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      showToast('Choose either Tuition Fee or Monthly Payment only, not both.');
      return;
    }

    const totalAmount = activeRows.reduce((sum, row) => sum + parseAmount(row.amount), 0);
    if (totalAmount > remainingBeforePayment) {
      event.preventDefault();
      if (paymentSubmitModeInput) {
        paymentSubmitModeInput.value = 'save';
      }
      window.alert('The entered amount exceeds the remaining balance of ' + formatPHP(remainingBeforePayment) + '.');
      return;
    }

    if (paymentItemsJson) {
      paymentItemsJson.value = JSON.stringify(builderRows);
    }
    syncPreviewEmailItemsInput();
  });

  paymentDateInput?.addEventListener('input', () => {
    syncEmailPreview(getBuilderTotal());
  });

  receiptNumberInput?.addEventListener('input', () => {
    syncEmailPreview(getBuilderTotal());
  });

  setCatalogOpen(false);
  syncTotals();
  syncEmptyState();
}
