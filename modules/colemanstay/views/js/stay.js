'use strict';
const form = document.querySelector('#stay-form');
const result = document.querySelector('#request-result');
if (result) result.focus({preventScroll: true});
if (form) {
  const arrival = form.elements.arrival;
  const departure = form.elements.departure;
  const datesLabel = document.querySelector('#trip-dates');
  const guestsLabel = document.querySelector('#trip-guests');
  const dateFormat = new Intl.DateTimeFormat(document.documentElement.lang, {month: 'short', day: 'numeric'});
  const updateTrip = () => {
    datesLabel.textContent = arrival.value && departure.value
      ? [arrival.value, departure.value].map(value => dateFormat.format(new Date(value + 'T12:00:00'))).join(' – ')
      : datesLabel.dataset.empty;
    const guests = Number(form.elements.guests.value);
    guestsLabel.textContent = guests > 0
      ? (guests === 1 ? guestsLabel.dataset.one : guestsLabel.dataset.many.replace('%s', guests))
      : guestsLabel.dataset.empty;
  };
  form.addEventListener('input', updateTrip);
  updateTrip();
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

// The compact trip bar takes guests to the corresponding request field.
document.querySelectorAll('[data-focus]').forEach(link => {
  const field = document.getElementById(link.dataset.focus);
  if (!field) {
    link.href = '#request';
    return;
  }
  link.addEventListener('click', event => {
    event.preventDefault();
    field.scrollIntoView({block: 'center'});
    field.focus({preventScroll: true});
  });
});
