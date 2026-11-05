import Alpine from '@alpinejs/csp';
import './pwa';

// CSP build: templates can only name these properties and methods, because the
// Content-Security-Policy forbids the eval that inline Alpine expressions need.
Alpine.data('sidebar', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
    get panelClass() {
        return this.open ? 'translate-x-0' : '-translate-x-full';
    },
}));

Alpine.data('menu', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
}));

Alpine.data('toast', () => ({
    visible: true,
    init() {
        setTimeout(() => this.dismiss(), 6000);
    },
    dismiss() {
        this.visible = false;
    },
}));

Alpine.data('registerType', () => ({
    type: 'solo_practice',
    ownerIsDoctor: false,
    init() {
        this.type = this.$el.dataset.initial || 'solo_practice';
        this.ownerIsDoctor = this.$el.dataset.ownerDoctor === '1';
    },
    toggleOwnerDoctor(event) {
        this.ownerIsDoctor = event.target.checked;
    },
    get isDoctorType() {
        return this.type !== 'clinic';
    },
    get needsDoctorDetails() {
        return this.type !== 'clinic' || this.ownerIsDoctor;
    },
    pickSolo() {
        this.type = 'solo_practice';
    },
    pickClinic() {
        this.type = 'clinic';
    },
    get isClinic() {
        return this.type === 'clinic';
    },
}));

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

Alpine.data('patientPicker', () => ({
    results: [],
    url: '',
    target: '',
    timer: null,
    init() {
        this.url = this.$el.dataset.url;
        this.target = this.$el.dataset.target;
    },
    search(event) {
        clearTimeout(this.timer);
        const term = event.target.value;
        this.timer = setTimeout(async () => {
            const response = await fetch(`${this.url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
            const rows = response.ok ? await response.json() : [];
            this.results = rows.map((row) => ({ ...row, url: `${this.target}?patient=${row.id}` }));
        }, 200);
    },
}));

Alpine.data('rxEditor', () => ({
    items: [],
    results: [],
    url: '',
    timer: null,
    nextKey: 1,
    init() {
        this.url = this.$el.dataset.searchUrl;
        this.items = JSON.parse(this.$el.dataset.items || '[]').map((item) => ({ ...item, key: this.nextKey++ }));
    },
    get hasResults() {
        return this.results.length > 0;
    },
    get isEmpty() {
        return this.items.length === 0;
    },
    search(event) {
        clearTimeout(this.timer);
        const term = event.target.value;
        this.timer = setTimeout(async () => {
            const response = await fetch(`${this.url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
            const rows = response.ok ? await response.json() : [];
            this.results = rows.map((row) => ({ ...row, label: `${row.favourite ? '★ ' : ''}${row.name} · ${row.generic} · ${row.form ?? ''}` }));
        }, 200);
    },
    add(id, name) {
        this.items.push({ key: this.nextKey++, medicine_id: id, medicine_name: name, dosage: '', timing: '', duration_days: '', notes: '' });
        this.results = [];
        this.$refs.search.value = '';
        this.$refs.search.focus();
    },
    pick(event) {
        const id = Number(event.currentTarget.dataset.id);
        const result = this.results.find((row) => row.id === id);
        this.add(id, result.name);
    },
    addTyped(event) {
        const name = event.target.value.trim();
        if (name !== '') {
            this.add(null, name);
        }
    },
    remove(event) {
        const key = Number(event.currentTarget.dataset.key);
        this.items = this.items.filter((item) => item.key !== key);
    },
    update(event) {
        const { key, field } = event.target.dataset;
        const item = this.items.find((row) => row.key === Number(key));
        item[field] = event.target.value;
    },
    serialize() {
        this.$refs.itemsJson.value = JSON.stringify(this.items.map(({ key, ...item }) => ({
            ...item,
            duration_days: item.duration_days === '' ? null : Number(item.duration_days),
        })));
    },
}));

Alpine.data('billEditor', () => ({
    items: [],
    nextKey: 1,
    feeNote: '',
    feeUrl: '',
    patient: '',
    init() {
        this.feeUrl = this.$el.dataset.feeUrl;
        this.patient = this.$el.dataset.patient;
        this.items = [{ key: this.nextKey++, service_item_id: null, description: 'Consultation', quantity: 1, unit: '' }];
        this.loadFee();
    },
    get hasFeeNote() {
        return this.feeNote !== '';
    },
    get subtotal() {
        const paise = this.items.reduce((sum, item) => sum + Math.round(Number(item.unit || 0) * 100) * Number(item.quantity || 0), 0);

        return (paise / 100).toFixed(2);
    },
    async loadFee() {
        const query = new URLSearchParams({
            visit_type: this.$refs.visitType.value,
            doctor_user_id: this.$refs.doctor.value,
            patient_id: this.patient,
        });
        const response = await fetch(`${this.feeUrl}?${query}`, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            return;
        }

        const fee = await response.json();
        const consultation = this.items.find((item) => item.service_item_id === null);

        if (consultation) {
            consultation.unit = fee.amount;
        }

        this.feeNote = fee.note ?? '';
    },
    addService(event) {
        const option = event.target.selectedOptions[0];

        if (option.value !== '') {
            this.items.push({ key: this.nextKey++, service_item_id: Number(option.value), description: option.dataset.name, quantity: 1, unit: option.dataset.price });
        }

        event.target.value = '';
    },
    remove(event) {
        const key = Number(event.currentTarget.dataset.key);
        this.items = this.items.filter((item) => item.key !== key);
    },
    update(event) {
        const { key, field } = event.target.dataset;
        const item = this.items.find((row) => row.key === Number(key));
        item[field] = event.target.value;
    },
    serialize() {
        this.$refs.itemsJson.value = JSON.stringify(this.items.map(({ key, ...item }) => ({ ...item, quantity: Number(item.quantity) })));
    },
}));

const shade = (hex, amount) => {
    const value = (offset) => Math.round(parseInt(hex.slice(offset, offset + 2), 16) * (1 - amount)).toString(16).padStart(2, '0');

    return `#${value(1)}${value(3)}${value(5)}`;
};

/**
 * Redraws the chosen picture in the browser and puts the result back in the file input.
 * Phone photos are large, and some servers cannot read JPG: what is uploaded is always a
 * small PNG or JPG the server accepts. The server still checks and re-encodes it.
 */
const shrinkFile = async (file, maxSide, type) => {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext('2d');

    if (type === 'image/jpeg') {
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.85));
    return new File([blob], type === 'image/jpeg' ? 'upload.jpg' : 'upload.png', { type });
};

const shrinkForUpload = async (input, maxSide, type) => {
    const transfer = new DataTransfer();
    const shrunk = await Promise.all([...input.files].map((file) => shrinkFile(file, maxSide, type)));
    shrunk.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.readAsDataURL(shrunk[0]);
    });
};

Alpine.data('photoInput', () => ({
    preview: '',
    error: '',
    busy: false,
    get hasPreview() {
        return this.preview !== '';
    },
    async shrink(event) {
        const input = event.target;

        if (!input.files.length) {
            return;
        }

        this.busy = true;
        this.error = '';

        try {
            this.preview = await shrinkForUpload(input, 640, 'image/png');
        } catch {
            input.value = '';
            this.error = 'That file could not be opened as a picture. Please try a PNG or JPG.';
        } finally {
            this.busy = false;
        }
    },
}));

Alpine.data('brandDesigner', () => ({
    logo: '',
    cover: '',
    preparing: false,
    imageError: '',
    canReadJpeg: false,
    get hasLogo() {
        return this.logo !== '';
    },
    get noLogo() {
        return this.logo === '';
    },
    get hasCover() {
        return this.cover !== '';
    },
    async prepareImage(input, maxSide, type, field) {
        if (!input.files.length) {
            return;
        }

        this.preparing = true;
        this.imageError = '';

        try {
            this[field] = await shrinkForUpload(input, maxSide, type);
        } catch {
            input.value = '';
            this.imageError = 'That file could not be opened as a picture. Please try a PNG or JPG.';
        } finally {
            this.preparing = false;
        }
    },
    prepareLogo(event) {
        return this.prepareImage(event.target, 512, 'image/png', 'logo');
    },
    async prepareGallery(event) {
        const input = event.target;

        if (!input.files.length) {
            return;
        }

        this.preparing = true;
        this.imageError = '';

        try {
            await (this.canReadJpeg ? shrinkForUpload(input, 1200, 'image/jpeg') : shrinkForUpload(input, 800, 'image/png'));
        } catch {
            input.value = '';
            this.imageError = 'One of those files could not be opened as a picture. Please try PNG or JPG.';
        } finally {
            this.preparing = false;
        }
    },
    prepareCover(event) {
        return this.canReadJpeg
            ? this.prepareImage(event.target, 1600, 'image/jpeg', 'cover')
            : this.prepareImage(event.target, 900, 'image/png', 'cover');
    },
    primary: '#088575',
    accent: '#f59e0b',
    tagline: '',
    name: '',
    init() {
        this.primary = this.$el.dataset.primary;
        this.accent = this.$el.dataset.accent;
        this.tagline = this.$el.dataset.tagline;
        this.name = this.$el.dataset.name;
        this.logo = this.$el.dataset.logo;
        this.cover = this.$el.dataset.cover;
        this.canReadJpeg = this.$el.dataset.canReadJpeg === '1';
        this.$nextTick(() => this.paint());
    },
    get initial() {
        return this.name.slice(0, 1).toUpperCase();
    },
    get previewTagline() {
        return this.tagline || 'Book your appointment';
    },
    paint() {
        if (!/^#[0-9a-fA-F]{6}$/.test(this.primary)) {
            return;
        }

        // Custom properties set here are inherited, so only the preview changes until saved.
        const preview = this.$refs.preview.style;
        preview.setProperty('--color-brand-600', this.primary);
        preview.setProperty('--color-brand-700', shade(this.primary, 0.15));
        preview.setProperty('--color-brand-800', shade(this.primary, 0.3));
        preview.setProperty('--color-brand-100', `${this.primary}26`);

        if (/^#[0-9a-fA-F]{6}$/.test(this.accent)) {
            preview.setProperty('--color-accent-500', this.accent);
        }
    },
    setPrimary(event) {
        this.primary = event.target.value;
        this.paint();
    },
    setAccent(event) {
        this.accent = event.target.value;
        this.paint();
    },
    setTagline(event) {
        this.tagline = event.target.value;
    },
    applyPreset(event) {
        this.primary = event.currentTarget.dataset.primary;
        this.accent = event.currentTarget.dataset.accent;
        this.paint();
    },
}));

Alpine.data('queueDisplay', () => ({
    doctors: [],
    offline: false,
    canFullscreen: Boolean(document.documentElement.requestFullscreen),
    init() {
        this.refresh();
        setInterval(() => this.refresh(), 10000);
    },
    get isEmpty() {
        return this.doctors.length === 0 && !this.offline;
    },
    async refresh() {
        try {
            const response = await fetch(this.$el.dataset.url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            this.doctors = data.doctors.map((doctor) => ({
                ...doctor,
                nowLabel: doctor.now ?? '–',
                nextLabel: doctor.next.join(', ') || '–',
            }));
            this.offline = false;
        } catch {
            this.offline = true;
        }
    },
    fullscreen() {
        document.documentElement.requestFullscreen();
    },
}));

window.Alpine = Alpine;
Alpine.start();

const refreshQueueBoard = async () => {
    const board = document.querySelector('[data-queue-board]');

    if (!board) {
        return;
    }

    const response = await fetch(board.dataset.queueBoard, { headers: { Accept: 'text/html' } });

    if (response.ok) {
        board.innerHTML = await response.text();
    }
};

if (document.querySelector('[data-queue-board]')) {
    setInterval(refreshQueueBoard, 20000);
}

// Queue actions respond at once and reconcile with the server in the background.
document.addEventListener('submit', async (event) => {
    if (event.target.dataset?.optimistic === undefined) {
        return;
    }

    event.preventDefault();
    const form = event.target;
    const row = form.closest('.flex-wrap');
    form.querySelector('button').disabled = true;
    row?.classList.add('opacity-50');

    await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } }).catch(() => {});
    await refreshQueueBoard();
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) {
        window.print();
    }
});

document.addEventListener('change', (event) => {
    if (event.target.dataset?.emrTemplate === undefined || event.target.value === '') {
        return;
    }

    const form = event.target.form;
    Object.entries(JSON.parse(event.target.value)).forEach(([field, text]) => {
        const area = form.elements[field];

        // Never overwrite what the doctor has already typed.
        if (area && area.value.trim() === '') {
            area.value = text;
        }
    });
    event.target.value = '';
});

// Single-key shortcuts for reception, ignored while typing in a field.
document.addEventListener('keydown', (event) => {
    if (event.ctrlKey || event.metaKey || event.altKey || event.target.closest('input, textarea, select')) {
        return;
    }

    const link = document.querySelector(`[data-shortcut="${event.key}"]`);

    if (link) {
        event.preventDefault();
        link.click();
    }
});

document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('change', (event) => {
    if (event.target.dataset?.autoSubmit !== undefined) {
        event.target.form?.requestSubmit();
    }
});
