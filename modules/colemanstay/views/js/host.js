'use strict';
document.addEventListener('DOMContentLoaded', () => {
  // The native template embeds icon-font glyphs in placeholder text.
  document.querySelectorAll('#login-panel input[placeholder]').forEach(input => {
    input.placeholder = input.placeholder.replace(/^[\uE000-\uF8FF]\s*/, '');
  });
});
