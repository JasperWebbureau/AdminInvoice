class AdminInvoiceCreditDialog {
    constructor(root = document) {
        this.root = root;
        this.dialog = root.querySelector('[data-admin-credit-dialog]');
        this.openButton = root.querySelector('[data-admin-credit-open]');

        if (!this.dialog || !this.openButton) {
            return;
        }

        this.onClick = this.onClick.bind(this);
        this.onCancel = this.onCancel.bind(this);
        this.root.addEventListener('click', this.onClick);
        this.dialog.addEventListener('cancel', this.onCancel);
    }

    onClick(event) {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }

        if (target.closest('[data-admin-credit-open]')) {
            this.open();
            return;
        }

        if (target.closest('[data-admin-credit-close]')) {
            this.close();
        }
    }

    onCancel(event) {
        event.preventDefault();
        this.close();
    }

    open() {
        if (typeof this.dialog.showModal === 'function') {
            this.dialog.showModal();
        } else {
            this.dialog.setAttribute('open', 'open');
        }

        const reason = this.dialog.querySelector('[name="reason"]');
        if (reason) {
            reason.focus();
        }
    }

    close() {
        if (typeof this.dialog.close === 'function') {
            this.dialog.close();
        } else {
            this.dialog.removeAttribute('open');
        }
    }
}

if (!window.adminInvoiceCreditDialog) {
    window.adminInvoiceCreditDialog = new AdminInvoiceCreditDialog();
}
