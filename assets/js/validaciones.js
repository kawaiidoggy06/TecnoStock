// assets/js/validaciones.js
// Validaciones del lado del CLIENTE (se repiten en PHP, porque JS se puede saltar).

// 1) Todo formulario con la clase "needs-validation" se revisa antes de enviarse.
document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        if (!form.checkValidity()) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        form.classList.add('was-validated'); // Bootstrap marca en rojo/verde
    });
});

// 2) Botones o formularios con data-confirmar piden confirmación antes de actuar.
document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        if (!confirm(form.dataset.confirmar)) {
            ev.preventDefault();
        }
    });
});
