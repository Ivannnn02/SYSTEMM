document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const emailInput = document.getElementById('loginEmail');
  const rememberInput = document.getElementById('rememberLogin');
  const rememberedEmailKey = 'smartenrollRememberedEmail';
  const authCard = document.querySelector('.login-card');
  const switchButtons = document.querySelectorAll('.auth-switch-btn');
  const authPanels = document.querySelectorAll('.auth-panel');

  if (!loginForm) {
    return;
  }

  function setActiveTab(target) {
    switchButtons.forEach((button) => {
      button.classList.toggle('active', button.dataset.authTarget === target);
    });

    authPanels.forEach((panel) => {
      panel.classList.toggle('active', panel.dataset.authPanel === target);
    });
  }

  const initialTab = authCard?.dataset.activeTab || 'login';
  setActiveTab(initialTab);

  const savedEmail = window.localStorage.getItem(rememberedEmailKey) || '';
  if (savedEmail && emailInput) {
    emailInput.value = savedEmail;
    if (rememberInput) {
      rememberInput.checked = true;
    }
  }

  loginForm.addEventListener('submit', (event) => {
    const email = (emailInput?.value || '').trim();
    const shouldRemember = Boolean(rememberInput?.checked);

    if (!email) {
      window.localStorage.removeItem(rememberedEmailKey);
      return;
    }

    if (shouldRemember) {
      window.localStorage.setItem(rememberedEmailKey, email);
    } else {
      window.localStorage.removeItem(rememberedEmailKey);
    }
  });

  switchButtons.forEach((button) => {
    button.addEventListener('click', () => {
      setActiveTab(button.dataset.authTarget || 'login');
    });
  });
});
