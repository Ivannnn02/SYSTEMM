document.addEventListener('DOMContentLoaded', () => {
  const profileMenu = document.querySelector('.dashboard-profile-menu');
  const profileToggle = document.getElementById('dashboardProfileToggle');

  if (!profileMenu || !profileToggle) {
    return;
  }

  function setMenuState(isOpen) {
    profileMenu.classList.toggle('open', isOpen);
    profileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  profileToggle.addEventListener('click', (event) => {
    event.stopPropagation();
    setMenuState(!profileMenu.classList.contains('open'));
  });

  document.addEventListener('click', (event) => {
    if (!profileMenu.contains(event.target)) {
      setMenuState(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setMenuState(false);
    }
  });
});
