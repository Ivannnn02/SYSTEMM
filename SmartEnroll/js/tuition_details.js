(function () {
  const saveBtn = document.querySelector('.tuition-save-btn');
  const overallInput = document.getElementById('overallBalance');
  const paidInput = document.getElementById('paidAmount');
  const balanceInput = document.getElementById('balanceAfterPayment');
  const historyBody = document.querySelector('.tuition-history tbody');

  if (!saveBtn || !overallInput || !paidInput || !balanceInput || !historyBody) return;

  const formatter = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  function parseAmount(value) {
    if (!value) return 0;
    const cleaned = value.replace(/[^0-9.\-]/g, '');
    const num = parseFloat(cleaned);
    return Number.isFinite(num) ? num : 0;
  }

  function formatPHP(value) {
    return `PHP ${formatter.format(value)}`;
  }

  function formatPaidInput() {
    const paid = parseAmount(paidInput.value);
    paidInput.value = formatPHP(paid);
  }

  function clearEmptyHistoryRow() {
    const firstRow = historyBody.querySelector('tr');
    if (!firstRow) return;
    const cells = firstRow.querySelectorAll('td');
    if (cells.length === 3 && cells[2].textContent.trim() === 'No payment history yet.') {
      historyBody.innerHTML = '';
    }
  }

  paidInput.addEventListener('blur', formatPaidInput);

  saveBtn.addEventListener('click', () => {
    const overall = parseAmount(overallInput.value);
    const paid = parseAmount(paidInput.value);
    const balance = Math.max(overall - paid, 0);

    paidInput.value = formatPHP(paid);
    balanceInput.value = formatPHP(balance);

    clearEmptyHistoryRow();

    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    let hour24 = today.getHours();
    const ampm = hour24 >= 12 ? 'PM' : 'AM';
    let hour12 = hour24 % 12;
    if (hour12 === 0) hour12 = 12;
    const hh = String(hour12).padStart(2, '0');
    const min = String(today.getMinutes()).padStart(2, '0');
    const sec = String(today.getSeconds()).padStart(2, '0');
    const dateStr = `${yyyy}-${mm}-${dd} | ${hh}:${min}:${sec} ${ampm}`;

    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${dateStr}</td>
      <td>${formatPHP(overall)}</td>
      <td>${formatPHP(paid)}</td>
    `;
    historyBody.prepend(row);
  });
})();
