const requirementsSearch = document.getElementById('requirementsSearch');
const requirementsFilter = document.getElementById('requirementsFilter');
const requirementsPrintBtn = document.getElementById('requirementsPrintBtn');

if (requirementsSearch) {
  requirementsSearch.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      requirementsSearch.form?.submit();
    }
  });
}

if (requirementsFilter) {
  requirementsFilter.addEventListener('change', () => {
    requirementsFilter.form?.submit();
  });
}

if (requirementsPrintBtn) {
  requirementsPrintBtn.addEventListener('click', (event) => {
    event.preventDefault();
    const printUrl = requirementsPrintBtn.getAttribute('href');

    if (!printUrl) {
      return;
    }

    const printWindow = window.open(printUrl, '_blank');
    printWindow?.focus();
  });
}
