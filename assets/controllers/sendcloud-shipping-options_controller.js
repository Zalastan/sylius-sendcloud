import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        methodId: Number,
        methodCode: String,
    };

    #handleOptionSelected = null;

    connect() {
        this.#handleOptionSelected = (event) => this.#onOptionSelected(event);
        this.element.addEventListener('sendcloud:option:selected', this.#handleOptionSelected);
        // Sync on connect in case the page was reloaded with a pre-selected option
        this.#syncSyliusRadioFromAttribute();
    }

    disconnect() {
        if (this.#handleOptionSelected) {
            this.element.removeEventListener('sendcloud:option:selected', this.#handleOptionSelected);
            this.#handleOptionSelected = null;
        }
    }

    #onOptionSelected(event) {
        const { priceCents } = event.detail ?? {};
        this.#selectSyliusRadio();
        if (priceCents > 0) {
            this.#updateSummary(priceCents);
        }
    }

    // Called on connect to handle pre-selected options (e.g. back-button navigation)
    #syncSyliusRadioFromAttribute() {
        const liveRoot = this.element.querySelector('[data-sendcloud-method-id]');
        const selected = liveRoot?.getAttribute('data-sendcloud-selected');
        if (selected) {
            this.#selectSyliusRadio();
        }
    }

    #selectSyliusRadio() {
        const radio = this.#syliusRadio();
        if (radio && !radio.checked) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    #syliusRadio() {
        return document.querySelector(`input[type="radio"][name*="[method]"][value="${this.methodCodeValue}"]`);
    }

    #updateSummary(newShippingCents) {
        const shippingEl = document.getElementById('sylius-shop-checkout-summary-shipping-total');
        const orderTotalEl = document.getElementById('sylius-shop-checkout-summary-order-total');

        if (!shippingEl) return;

        // Compute new order total by replacing the shipping component (handles promotions transparently)
        if (orderTotalEl) {
            const currentShippingCents = this.#parseCents(shippingEl.textContent);
            const currentTotalCents = this.#parseCents(orderTotalEl.textContent);
            const newTotalCents = currentTotalCents - currentShippingCents + newShippingCents;
            orderTotalEl.textContent = this.#formatCurrency(newTotalCents);
        }

        shippingEl.textContent = this.#formatCurrency(newShippingCents);
    }

    #parseCents(text) {
        const cleaned = text.replace(/[\s  ]+/g, '').replace('€', '').replace(',', '.');
        return Math.round(parseFloat(cleaned) * 100) || 0;
    }

    #formatCurrency(cents) {
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);
    }
}
