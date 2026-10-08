class AdminInvoiceCustomerSelector {
    constructor(root = document) {
        this.root = root;
        this.searchTimer = null;
        this.onInput = this.onInput.bind(this);
        this.onClick = this.onClick.bind(this);
        this.root.addEventListener('input', this.onInput);
        this.root.addEventListener('click', this.onClick);
    }

    onInput(event) {
        const search = this.closest(event.target, '[data-admin-invoice-customer-search] input[name="q"]');
        if (search) {
            window.clearTimeout(this.searchTimer);
            if (search.value.trim().length < 2) {
                return;
            }
            this.searchTimer = window.setTimeout(() => this.submit(search.form), 350);
            return;
        }

        const customerField = this.closest(event.target, '[data-admin-invoice-customer-form] [name^="customer["], [data-admin-invoice-customer-form] [name^="address["]');
        if (customerField && customerField.name !== 'customer[source_public_id]') {
            this.clearLink(customerField.form, false);
        }
    }

    onClick(event) {
        const result = this.closest(event.target, '[data-admin-invoice-customer-result]');
        if (result) {
            event.preventDefault();
            this.select(result);
            return;
        }
        const clear = this.closest(event.target, '[data-admin-invoice-customer-clear]');
        if (clear) {
            event.preventDefault();
            const form = this.root.querySelector('[data-admin-invoice-customer-form]');
            this.clearLink(form, true);
        }
    }

    select(result) {
        const form = this.root.querySelector('[data-admin-invoice-customer-form]');
        if (!form) {
            return;
        }
        const values = {
            'customer[source_public_id]': result.dataset.customerPublicId,
            'customer[name]': result.dataset.customerName,
            'customer[contact_name]': result.dataset.customerContactName,
            'customer[email]': result.dataset.customerEmail,
            'customer[phone]': result.dataset.customerPhone,
            'customer[registration_number]': result.dataset.customerRegistrationNumber,
            'customer[tax_number]': result.dataset.customerTaxNumber,
            'address[line_1]': result.dataset.addressLine1,
            'address[line_2]': result.dataset.addressLine2,
            'address[postal_code]': result.dataset.addressPostalCode,
            'address[city]': result.dataset.addressCity,
            'address[region]': result.dataset.addressRegion,
            'address[country_code]': result.dataset.addressCountryCode
        };
        Object.keys(values).forEach((name) => this.setValue(form, name, values[name]));
        this.markChanged(form);
        this.setSelectionLabel(result.dataset.customerName || '', true);
        const results = result.closest('[data-admin-invoice-customer-results]');
        if (results) {
            results.innerHTML = '';
        }
    }

    clearLink(form, announce) {
        if (!form) {
            return;
        }
        const field = form.elements.namedItem('customer[source_public_id]');
        if (!field || field.value === '') {
            return;
        }
        field.value = '';
        this.markChanged(form);
        const selection = this.root.querySelector('[data-admin-invoice-customer-selected]');
        this.setSelectionLabel(selection && selection.dataset.emptyLabel || 'Handmatige klantgegevens', false);
        if (announce) {
            const search = this.root.querySelector('[data-admin-invoice-customer-search] input[name="q"]');
            if (search) {
                search.focus();
            }
        }
    }

    setSelectionLabel(label, selected) {
        const container = this.root.querySelector('[data-admin-invoice-customer-selected]');
        const text = this.root.querySelector('[data-admin-invoice-customer-selected-label]');
        const clear = this.root.querySelector('[data-admin-invoice-customer-clear]');
        if (container) {
            container.classList.toggle('is-selected', selected);
        }
        if (text) {
            const prefix = container && container.dataset.selectedPrefix || 'Gekoppeld aan';
            text.textContent = selected ? `${prefix} ${label}` : label;
        }
        if (clear) {
            clear.hidden = !selected;
        }
    }

    setValue(form, name, value) {
        const field = form.elements.namedItem(name);
        if (field) {
            field.value = value == null ? '' : String(value);
        }
    }

    markChanged(form) {
        const field = form.elements.namedItem('customer[source_public_id]');
        if (field) {
            field.dispatchEvent(new Event('change', {bubbles: true}));
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
        form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
    }

    closest(target, selector) {
        return target instanceof Element ? target.closest(selector) : null;
    }
}

if (!window.adminInvoiceCustomerSelector) {
    window.adminInvoiceCustomerSelector = new AdminInvoiceCustomerSelector();
}
