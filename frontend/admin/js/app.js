(function () {
    var root = document.documentElement;

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next;
            localStorage.setItem('atrium-theme', next);
        });
    });

    function fill(dialog, button) {
        var title = dialog.querySelector('[data-modal-title]');
        if (title && button.getAttribute('data-title')) {
            title.textContent = button.getAttribute('data-title');
        }
        var form = dialog.querySelector('[data-modal-form]');
        if (!form) {
            return;
        }
        if (button.getAttribute('data-action')) {
            form.action = button.getAttribute('data-action');
        }
        form.querySelectorAll('[data-field]').forEach(function (input) {
            var key = input.getAttribute('data-field');
            var value = button.getAttribute('data-' + key);
            if (value === null) {
                value = '';
            }
            if (input.type === 'checkbox') {
                input.checked = value.split(',').indexOf(input.value) !== -1;
            } else {
                input.value = value;
            }
        });
    }

    function applyLines(form, raw) {
        if (!form) {
            return;
        }
        var lines = [];
        try {
            lines = JSON.parse(raw || '[]');
        } catch (error) {
            lines = [];
        }
        for (var i = 0; i < 6; i++) {
            var line = lines[i] || {};
            ['description', 'qty', 'price', 'tax', 'product_id'].forEach(function (key) {
                var input = form.querySelector('[name="lines[' + i + '][' + key + ']"]');
                if (!input) {
                    return;
                }
                var value = line[key];
                if (value === undefined || value === null || value === '') {
                    value = key === 'qty' ? '1' : (key === 'tax' ? '18' : '');
                }
                input.value = value;
            });
        }
    }

    function renderHistory(dialog, raw) {
        var list = dialog.querySelector('[data-history-list]');
        if (!list) {
            return;
        }
        list.replaceChildren();
        var items = [];
        try {
            items = JSON.parse(raw || '[]');
        } catch (error) {
            items = [];
        }
        if (!items.length) {
            var empty = document.createElement('p');
            empty.textContent = 'No transfers, follow-ups, or visits yet.';
            list.appendChild(empty);
            return;
        }
        items.forEach(function (item) {
            var row = document.createElement('article');
            row.className = 'history-row';
            var heading = document.createElement('strong');
            heading.textContent = item.title || '';
            var meta = document.createElement('em');
            meta.textContent = item.meta || '';
            var body = document.createElement('p');
            body.textContent = item.body || '';
            row.append(heading, meta, body);
            list.appendChild(row);
        });
    }

    function filterPlots(form) {
        if (!form) {
            return;
        }
        var project = form.querySelector('[data-project-filter]');
        var plots = form.querySelector('[data-plot-options]');
        if (!project || !plots) {
            return;
        }
        Array.prototype.forEach.call(plots.options, function (option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            option.hidden = project.value !== '' && option.getAttribute('data-project') !== project.value;
        });
        if (plots.selectedOptions[0] && plots.selectedOptions[0].hidden) {
            plots.value = '';
        }
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-modal]');
        if (!opener) {
            return;
        }
        var dialog = document.getElementById(opener.getAttribute('data-modal'));
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        event.preventDefault();
        fill(dialog, opener);
        var form = dialog.querySelector('[data-modal-form]');
        if (opener.hasAttribute('data-lines')) {
            applyLines(form, opener.getAttribute('data-lines'));
        }
        if (opener.hasAttribute('data-history')) {
            renderHistory(dialog, opener.getAttribute('data-history'));
        }
        filterPlots(form);
        var owner = opener.getAttribute('data-owner_id');
        var staff = dialog.querySelector('[data-hide-owner]');
        if (staff) {
            Array.prototype.forEach.call(staff.options, function (option) {
                option.hidden = owner !== null && owner !== '' && option.value === owner;
            });
            if (staff.selectedOptions[0] && staff.selectedOptions[0].hidden) {
                staff.value = '';
            }
        }
        var pay = opener.getAttribute('data-pay');
        var payForm = document.querySelector('#payment-modal [data-modal-form]');
        var payButton = document.getElementById('record-payment');
        if (payButton) {
            payButton.hidden = !pay;
        }
        if (payForm && pay) {
            payForm.action = pay;
        }
        dialog.showModal();
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-project-filter]')) {
            filterPlots(event.target.form);
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-close]')) {
            return;
        }
        var dialog = event.target.closest('dialog');
        if (dialog) {
            dialog.close();
        }
    });

    document.querySelectorAll('dialog.modal').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
        if (dialog.getAttribute('data-open') === '1' && !dialog.open) {
            dialog.showModal();
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || String(form.method || '').toLowerCase() !== 'post') {
            return;
        }
        if (form.getAttribute('data-sent') === '1') {
            event.preventDefault();
            return;
        }
        form.setAttribute('data-sent', '1');
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            button.disabled = true;
        });
    });

    document.querySelectorAll('[data-line-product]').forEach(function (select) {
        select.addEventListener('change', function () {
            var option = select.selectedOptions[0];
            if (!option || !option.dataset.name) {
                return;
            }
            var index = select.getAttribute('data-line-product');
            var form = select.form;
            var description = form.querySelector('[name="lines[' + index + '][description]"]');
            var price = form.querySelector('[name="lines[' + index + '][price]"]');
            var tax = form.querySelector('[name="lines[' + index + '][tax]"]');
            if (description) description.value = option.dataset.name;
            if (price) price.value = option.dataset.price;
            if (tax) tax.value = option.dataset.tax;
        });
    });
}());
