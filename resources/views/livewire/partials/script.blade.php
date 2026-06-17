@assets
<script>
    // Remove a extensão do nome (para pré-preencher o campo de renomear).
    window.fmStripExt = function (file) {
        if (!file || !file.name) return '';
        if (file.type === 'folder') return file.name;
        const i = file.name.lastIndexOf('.');
        return i > 0 ? file.name.slice(0, i) : file.name;
    };

    // Formata o tempo restante (ms epoch) até à eliminação definitiva.
    window.fmTimeLeft = function (deleteAtMs) {
        const diff = deleteAtMs - Date.now();
        if (diff <= 0) return '@lang('file-manager::file-manager.expired')';
        const days = Math.floor(diff / 86400000);
        const hours = Math.floor((diff / 3600000) % 24);
        return days + 'd ' + hours + 'h';
    };

    window.prosideFileManager = function ({ picker, multiple }) {
        return {
            picker, multiple,
            view: 'grid',
            selected: [],
            filterOpen: false,
            fabOpen: false,
            uploadHover: false,
            menu: { open: false, x: 0, y: 0, file: null, files: [] },
            modal: { open: false, action: null, type: null, text: '', path: '', file: null },
            light: { open: false, url: '', type: '' },

            init() {
                // Estado partilhado com o servidor (instantâneo no cliente).
                this.view = this.$wire.entangle('viewMode');
                this.selected = this.$wire.entangle('selected');

                // Abertura de modal a partir do FAB / outros emissores.
                this.$root.addEventListener('fm-modal', (e) => this.openModal(e.detail));
            },

            // ---------- Seleção ----------
            isSelected(path) { return (this.selected || []).includes(path); },
            toggleSelect(path, shift) {
                if (!shift) { this.selected = [path]; return; }
                const i = this.selected.indexOf(path);
                if (i > -1) this.selected.splice(i, 1); else this.selected.push(path);
            },
            targetPaths() {
                if (this.menu.files && this.menu.files.length) return [...this.menu.files];
                return this.menu.file ? [this.menu.file.path] : [];
            },

            // ---------- Abrir / preview ----------
            openItem(file) {
                if (file.type === 'folder') { this.$wire.open(file.path); return; }
                if (this.picker) { this.$wire.choose([file.path]); return; }
                this.preview(file);
            },
            preview(file) {
                if (file && (file.type === 'image' || file.type === 'video')) {
                    this.light = { open: true, url: file.url, type: file.type };
                }
            },

            // ---------- Menu de contexto ----------
            openMenu(e, file) {
                e.preventDefault(); e.stopPropagation();
                if (!this.isSelected(file.path)) this.selected = [file.path];
                this.menu = { open: true, x: e.clientX, y: e.clientY, file, files: [...this.selected] };
            },
            openBackgroundMenu(e) {
                this.selected = [];
                this.menu = { open: true, x: e.clientX, y: e.clientY, file: { type: 'background', name: '' }, files: [] };
            },
            menuX() { const w = 210; return (this.menu.x + w > window.innerWidth) ? this.menu.x - w : this.menu.x; },
            menuY() { const h = 250; return (this.menu.y + h > window.innerHeight) ? this.menu.y - h : this.menu.y; },
            everySelectedIs(types) {
                const t = this.menu.files.length ? this.itemsFor(this.menu.files) : [this.menu.file];
                return t.length > 0 && t.every((f) => f && types.includes(f.type));
            },
            itemsFor(paths) {
                return paths.map((p) => {
                    const el = this.$root.querySelector(`[data-fm-path="${CSS.escape(p)}"]`);
                    return el ? { path: p, type: el.dataset.fmType, url: el.dataset.fmUrl, name: el.dataset.fmName } : { path: p };
                });
            },

            // ---------- Modal ----------
            openModal(detail) {
                this.menu.open = false;
                this.modal = {
                    open: true,
                    action: detail.action,
                    type: detail.type || (this.menu.file ? this.menu.file.type : null),
                    text: detail.text || '',
                    path: detail.path || this.$wire.path,
                    file: detail.file || this.menu.file,
                };
                this.$nextTick(() => { const i = this.$root.querySelector('[data-fm-modal-input]'); if (i) i.focus(); });
            },
            confirmModal() {
                const m = this.modal;
                if (m.action === 'add') this.$wire.createFolder(m.text, m.path);
                else if (m.action === 'rename') this.$wire.rename(m.file.path, m.text);
                else if (m.action === 'delete') this.$wire.delete(this.targetPaths().length ? this.targetPaths() : [m.file.path]);
                this.modal.open = false;
            },

            // ---------- Drag & drop (mover) ----------
            onDragStart(e, file) {
                const items = this.isSelected(file.path) ? [...this.selected] : [file.path];
                e.dataTransfer.setData('application/x-fm', JSON.stringify(items));
                e.dataTransfer.effectAllowed = 'move';
            },
            onDropMove(e, target) {
                const raw = e.dataTransfer.getData('application/x-fm');
                if (!raw) return;
                const items = JSON.parse(raw);
                if (items.includes(target)) return;
                this.$wire.moveItems(items, target);
            },

            // ---------- Upload (drag de ficheiros do SO) ----------
            onDragOverUpload(e) { if ([...e.dataTransfer.types].includes('Files')) this.uploadHover = true; },
            onDropUpload(e) {
                this.uploadHover = false;
                if (!([...e.dataTransfer.types].includes('Files'))) return;
                const files = [...e.dataTransfer.files];
                if (files.length) this.$wire.uploadMultiple('uploads', files, () => {}, () => {}, () => {});
            },

            // ---------- Download ----------
            download() {
                this.itemsFor(this.targetPaths()).forEach((f) => {
                    if (!f.url) return;
                    const a = document.createElement('a');
                    a.href = f.url; a.download = f.name || ''; a.target = '_blank';
                    document.body.appendChild(a); a.click(); a.remove();
                });
                this.menu.open = false;
            },

            // ---------- Picker ----------
            choose() {
                this.$wire.choose(this.targetPaths());
                this.menu.open = false;
            },

            closeAll() {
                this.menu.open = false; this.modal.open = false; this.light.open = false;
                this.filterOpen = false; this.fabOpen = false;
            },
        };
    };

    const registerFileManager = () => {
        if (window.Alpine && !window.Alpine.__fmRegistered) {
            window.Alpine.data('fileManager', window.prosideFileManager);
            window.Alpine.__fmRegistered = true;
        }
    };
    document.addEventListener('alpine:init', registerFileManager);
    registerFileManager();
</script>
@endassets
