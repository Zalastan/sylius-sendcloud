import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        methodId: Number,
    };

    #observer = null;

    connect() {
        this.#positionPanel();
        this.#watchForSelection();
        this.#syncSyliusRadio();
    }

    disconnect() {
        this.#observer?.disconnect();
        this.#observer = null;
    }

    #positionPanel() {
        const radio = this.#syliusRadio();
        if (!radio) return;

        const staticCard = radio.closest('.card');
        if (!staticCard?.parentNode) return;

        staticCard.style.display = 'none';
        staticCard.parentNode.insertBefore(this.element, staticCard.nextSibling);
    }

    #watchForSelection() {
        const liveRoot = this.element.querySelector('[data-sendcloud-method-id]');
        if (!liveRoot) return;

        this.#observer = new MutationObserver(() => this.#syncSyliusRadio());
        this.#observer.observe(liveRoot, {
            subtree: true,
            attributes: true,
            attributeFilter: ['data-sendcloud-selected'],
        });
    }

    #syncSyliusRadio() {
        const liveRoot = this.element.querySelector('[data-sendcloud-method-id]');
        const selected = liveRoot?.getAttribute('data-sendcloud-selected');
        if (!selected) return;

        const radio = this.#syliusRadio();
        if (radio && !radio.checked) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    #syliusRadio() {
        return document.querySelector(`input[type="radio"][value="${this.methodIdValue}"]`);
    }
}
