'use strict';
const form = document.querySelector('#stay-form');
const result = document.querySelector('#request-result');
if (result) result.focus({preventScroll: true});
if (form) {
  const arrival = form.elements.arrival;
  const departure = form.elements.departure;
  arrival.addEventListener('change', () => {
    if (arrival.value) {
      const day = new Date(arrival.value + 'T12:00:00Z');
      day.setUTCDate(day.getUTCDate() + 1);
      departure.min = day.toISOString().slice(0, 10);
    }
  });
  form.addEventListener('submit', () => {
    form.querySelector('button[type=submit]').disabled = true;
    document.querySelector('#submit-status').textContent = form.dataset.saving;
    form.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', () => {
    form.querySelector('button[type=submit]').disabled = false;
    form.removeAttribute('aria-busy');
    document.querySelector('#submit-status').textContent = '';
  });
}
