'use strict';
document.querySelectorAll('.decision-form').forEach(form => {
  form.addEventListener('submit', event => {
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    const button = event.submitter;
    if (!button) { event.preventDefault(); return; }
    // Disabled submit buttons are omitted from native form data; preserve the choice.
    const decision = document.createElement('input');
    decision.type = 'hidden';
    decision.name = 'decision';
    decision.value = button.value;
    form.appendChild(decision);
    form.setAttribute('aria-busy', 'true');
    button.textContent = form.dataset.saving;
    form.querySelectorAll('button').forEach(control => { control.disabled = true; });
  });
});
// Restore a fresh revision if the browser returns to a submitted page from history.
window.addEventListener('pageshow', event => { if (event.persisted) { window.location.reload(); } });
