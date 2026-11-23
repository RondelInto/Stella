import './bootstrap';

// Bootstrap 5 JavaScript (dropdowns, modals, offcanvas sidebar, alerts, etc.)
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    // Re-open a modal after a failed form submission (e.g. wrong password on "Delete Account").
    document.querySelectorAll('.modal[data-show-on-load="true"]').forEach((el) => {
        bootstrap.Modal.getOrCreateInstance(el).show();
    });

    // Confirmation dialog for important actions:
    // <form data-confirm="Are you sure?"> ... </form>
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
