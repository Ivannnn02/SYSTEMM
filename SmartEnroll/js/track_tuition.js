const tuitionSearch = document.getElementById('tuitionSearch');

if (tuitionSearch) {
  tuitionSearch.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const query = tuitionSearch.value.toLowerCase();
    const rows = document.querySelectorAll('.tuition-table tbody tr');
    rows.forEach((row) => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(query) ? '' : 'none';
    });
  });
}
