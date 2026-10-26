'use strict';
document.querySelectorAll('.decision-form, .host-form').forEach(form => {
  form.addEventListener('submit', event => {
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    const button = event.submitter;
    if (!button) { event.preventDefault(); return; }
    // Downloads leave the page in place and must remain usable afterward.
    if (['file_download', 'receipt', 'csv', 'draft_download', 'calendar_export'].includes(button.value)) return;
    // Disabled submit buttons are omitted from native form data; preserve the choice.
    const decision = document.createElement('input');
    decision.type = 'hidden';
    decision.name = button.name;
    decision.value = button.value;
    form.appendChild(decision);
    form.setAttribute('aria-busy', 'true');
    if (form.dataset.saving) button.textContent = form.dataset.saving;
    form.querySelectorAll('button').forEach(control => { control.disabled = true; });
  });
});
// Restore a fresh revision if the browser returns to a submitted page from history.
window.addEventListener('pageshow', event => { if (event.persisted) { window.location.reload(); } });

// A download always uses saved wording. Make local edits visible before export.
const messageForm = document.querySelector('.message-form');
if (messageForm) messageForm.addEventListener('input', () => {
  const download = document.querySelector('.draft-export button');
  const hint = document.getElementById('draft-save-hint');
  if (download) download.disabled = true;
  if (hint) hint.textContent = hint.dataset.unsaved;
});
