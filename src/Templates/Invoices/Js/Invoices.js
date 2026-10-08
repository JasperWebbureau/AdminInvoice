class AdminInvoiceOverview {
    constructor(root = document) {
        this.root = root;
        this.searchTimer = null;
        this.searchDelay = 400;

        this.onInput = this.onInput.bind(this);
        this.onChange = this.onChange.bind(this);
        this.onClick = this.onClick.bind(this);

        this.bindEvents();
        this.initialMollieSync();
    }

    initialMollieSync() {
        const button = this.root.querySelector('[data-admin-invoice-mollie-sync]');
        if (!button) {
            return;
        }
        const key = 'admin-invoice-mollie-sync:' + window.location.origin;
        try {
            if (window.sessionStorage.getItem(key) === '1') {
                return;
            }
            window.sessionStorage.setItem(key, '1');
        } catch (error) {
            return; // Storage may be disabled; the manual button remains available.
        }
        window.setTimeout(() => {
            if (button.isConnected) {
                button.click();
            }
        }, 100);
    }

    bindEvents() {
        this.root.addEventListener('input', this.onInput);
        this.root.addEventListener('change', this.onChange);
        this.root.addEventListener('click', this.onClick);
    }

    onInput(event) {
        const field = this.closestElement(event.target, '[data-admin-invoice-filters] input[name="q"]');
        if (!field) {
            return;
        }

        window.clearTimeout(this.searchTimer);
        this.searchTimer = window.setTimeout(() => {
            this.submit(this.getForm(field), true);
        }, this.searchDelay);
    }

    onChange(event) {
        const field = this.closestElement(event.target, '[data-admin-invoice-filters] select');
        if (!field) {
            return;
        }

        this.submit(this.getForm(field), true);
    }

    onClick(event) {
        const pageButton = this.closestElement(event.target, '[data-admin-invoice-page]');
        if (pageButton) {
            event.preventDefault();
            if (!pageButton.disabled) {
                const form = this.getForm(pageButton);
                this.setValue(form, 'page', pageButton.dataset.adminInvoicePage);
                this.submit(form, false);
            }
            return;
        }

        const sortButton = this.closestElement(event.target, '[data-admin-invoice-filters] [data-fg-table-sort]');
        if (sortButton) {
            event.preventDefault();
            const form = this.getForm(sortButton);
            const sortField = form ? form.elements.namedItem('sort') : null;
            const directionField = form ? form.elements.namedItem('direction') : null;
            const nextSort = sortButton.dataset.fgTableSort || '';
            const currentSort = sortField ? sortField.value : '';
            const currentDirection = directionField ? directionField.value : 'desc';

            this.setValue(form, 'sort', nextSort);
            this.setValue(form, 'direction', currentSort === nextSort && currentDirection === 'asc' ? 'desc' : 'asc');
            this.submit(form, true);
            return;
        }

        const resetButton = this.closestElement(event.target, '[data-admin-invoice-reset]');
        if (!resetButton) {
            return;
        }

        event.preventDefault();
        const form = this.getForm(resetButton);
        this.setValue(form, 'q', '');
        this.setValue(form, 'document_type', '');
        this.setValue(form, 'status', '');
        this.setValue(form, 'payment_status', '');
        this.setValue(form, 'sort', 'issue_date');
        this.setValue(form, 'direction', 'desc');
        this.submit(form, true);
    }

    submit(form, resetPage) {
        if (!form || !form.isConnected) {
            return;
        }

        window.clearTimeout(this.searchTimer);
        if (resetPage) {
            this.setValue(form, 'page', '1');
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.dispatchEvent(new Event('submit', {
            bubbles: true,
            cancelable: true
        }));
    }

    setValue(form, name, value) {
        if (!form) {
            return;
        }

        const field = form.elements.namedItem(name);
        if (field) {
            field.value = value == null ? '' : String(value);
        }
    }

    getForm(element) {
        return element ? element.closest('[data-admin-invoice-filters]') : null;
    }

    closestElement(target, selector) {
        return target instanceof Element ? target.closest(selector) : null;
    }
}

if (!window.adminInvoiceOverview) {
    window.adminInvoiceOverview = new AdminInvoiceOverview();
}
