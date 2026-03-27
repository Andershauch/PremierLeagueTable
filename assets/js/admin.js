(function () {
  if (typeof jQuery === 'undefined') {
    return;
  }

  jQuery(function ($) {
    if (typeof $.fn.wpColorPicker === 'function') {
      $('.plt-color-field').wpColorPicker();
    }
  });
})();
