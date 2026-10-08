class AdminInvoiceEditor {
    constructor(root = document) {
        this.root = root;
        this.onChange = this.onChange.bind(this);
        this.onInput = this.onInput.bind(this);
        this.onPreviewClick = this.onPreviewClick.bind(this);
        this.root.addEventListener('change', this.onChange);
        this.root.addEventListener('input', this.onInput);
        this.root.addEventListener('click', this.onPreviewClick, true);
    }

    onInput(event) {
        const changedElement = event.target instanceof Element ? event.target : null;
        if (changedElement && changedElement.form && changedElement.form.id === 'admin-invoice-details-form') {
            this.requireHeaderSave();
        }
    }

    onPreviewClick(event) {
        const preview = this.closestElement(event.target, '[data-admin-invoice-preview]');
        if (preview && preview.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
        }
    }

    onChange(event) {
        const changedElement = event.target instanceof Element ? event.target : null;
        if (changedElement && changedElement.form && changedElement.form.id === 'admin-invoice-details-form') {
            this.requireHeaderSave();
            return;
        }

        const field = this.closestElement(event.target, '[data-admin-invoice-line-field]');
        if (!field || !field.form || !field.form.matches('[data-admin-invoice-line-form]')) {
            return;
        }

        if (!field.form.checkValidity()) {
            field.form.reportValidity();
            return;
        }

        this.submit(field.form);
    }

    requireHeaderSave() {
        const finalizeButton = this.root.querySelector('[data-admin-invoice-finalize]');
        if (finalizeButton) {
            finalizeButton.disabled = true;
            finalizeButton.title = 'Sla de gewijzigde factuurgegevens eerst op';
        }
        const preview = this.root.querySelector('[data-admin-invoice-preview]');
        if (preview) {
            preview.setAttribute('aria-disabled', 'true');
            preview.title = 'Sla de gewijzigde factuurgegevens eerst op om de nieuwe PDF te bekijken';
        }
    }

    submit(form) {
        if (!form || !form.isConnected) {
            return;
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

    closestElement(target, selector) {
        return target instanceof Element ? target.closest(selector) : null;
    }
}

if (!window.adminInvoiceEditor) {
    window.adminInvoiceEditor = new AdminInvoiceEditor();
}
