// Small optional teaching helpers for the practice pages.
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
  new bootstrap.Tooltip(element);
});

document.querySelectorAll('[data-bs-toggle="popover"]').forEach(element => {
  new bootstrap.Popover(element);
});

const toastButton = document.querySelector('#toastButton');
if (toastButton) {
  toastButton.addEventListener('click', () => {
    const toastElement = document.querySelector('#cartToast');
    if (toastElement) bootstrap.Toast.getOrCreateInstance(toastElement).show();
  });
}

document.querySelectorAll('.needs-validation').forEach(form => {
  form.addEventListener('submit', event => {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.classList.add('was-validated');
  });
});

const themeButton = document.querySelector('#themeToggle');
if (themeButton) {
  themeButton.addEventListener('click', () => {
    const root = document.documentElement;
    const useDark = root.getAttribute('data-bs-theme') !== 'dark';
    root.setAttribute('data-bs-theme', useDark ? 'dark' : 'light');
    themeButton.setAttribute('aria-pressed', String(useDark));
  });
}
