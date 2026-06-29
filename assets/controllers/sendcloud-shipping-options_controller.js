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
            this.#updateShippingDisplay(priceCents);
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

    #updateShippingDisplay(priceCents) {
        const el = document.getElementById('sylius-shop-checkout-summary-shipping-total');
        if (!el) return;
        el.textContent = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
        }).format(priceCents / 100);
    }
}
