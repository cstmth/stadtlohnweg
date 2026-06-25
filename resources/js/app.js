// Speichert die in *diesem* Browser angelegten (Gast-)Reservierungen lokal,
// damit sie ohne Konto grün markiert und ohne PIN storniert werden können.
const STORAGE_KEY = 'washing.mine';

function loadItems() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch (e) {
        return {};
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('mine', {
        items: loadItems(),

        has(id) {
            return Object.prototype.hasOwnProperty.call(this.items, String(id));
        },

        get(id) {
            return this.items[String(id)] ?? null;
        },

        ids() {
            return Object.keys(this.items).map((id) => parseInt(id, 10));
        },

        map() {
            return { ...this.items };
        },

        add(id, pin) {
            this.items[String(id)] = String(pin);
            this.save();
        },

        remove(id) {
            delete this.items[String(id)];
            this.save();
        },

        save() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items));
        },
    });
});
