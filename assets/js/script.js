// Small helper script: fills modals for Add / Edit / View and confirms actions.
document.addEventListener('DOMContentLoaded', function () {

  // "Add" buttons: clear the form and set the modal title
  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var modal = document.querySelector(btn.dataset.bsTarget);
      var form = modal.querySelector('form');
      form.reset();
      form.elements['id'].value = '';
      form.querySelectorAll('[data-lock-on-edit]').forEach(function (f) { f.readOnly = false; });
      modal.querySelector('.modal-title').textContent = btn.dataset.title;
    });
  });

  // "Edit" buttons: copy the row data (JSON) into the form fields
  document.querySelectorAll('[data-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var data = JSON.parse(btn.dataset.edit);
      var modal = document.querySelector(btn.dataset.bsTarget);
      var form = modal.querySelector('form');
      form.reset();
      Object.keys(data).forEach(function (key) {
        if (form.elements[key]) form.elements[key].value = data[key] === null ? '' : data[key];
      });
      form.querySelectorAll('[data-lock-on-edit]').forEach(function (f) { f.readOnly = true; });
      modal.querySelector('.modal-title').textContent = btn.dataset.title;
    });
  });

  // "View" buttons: show row data inside <span data-field="name">
  document.querySelectorAll('[data-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var data = JSON.parse(btn.dataset.view);
      var modal = document.querySelector(btn.dataset.bsTarget);
      modal.querySelectorAll('[data-field]').forEach(function (el) {
        var v = data[el.dataset.field];
        el.textContent = (v === null || v === undefined || v === '') ? '-' : v;
      });
    });
  });

  // Confirm before deactivate / activate
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });
});
