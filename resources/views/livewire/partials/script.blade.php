@assets
{{-- Estilos do componente injetados via @assets: carregam sempre que o
     componente é renderizado (página inteira, embebido ou picker), sem
     depender de `vendor:publish`. --}}
<style>
    [x-cloak] { display: none !important; }

    .fm-root { font-family: ui-sans-serif, system-ui, sans-serif; }

    /* Scrollbar discreta nas áreas roláveis */
    .fm-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    .fm-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
    .fm-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
    .fm-scroll::-webkit-scrollbar-track { background: transparent; }

    /* Evita seleção de texto durante drag */
    .fm-root [draggable="true"] { -webkit-user-drag: element; }

    /* Checkbox de seleção usa a cor da marca (independente do plugin forms) */
    .fm-check { accent-color: currentColor; }

    /* Tooltip ao passar o rato — mostra o aria-label (que os leitores de ecrã
       também anunciam). Aplicado só aos botões com a classe .fm-tip. */
    .fm-tip { position: relative; }
    .fm-tip::after {
        content: attr(aria-label);
        position: absolute;
        top: calc(100% + 6px);
        left: 50%;
        transform: translateX(-50%);
        background: #1f2937;
        color: #fff;
        font-size: 11px;
        line-height: 1.1;
        white-space: nowrap;
        padding: 5px 8px;
        border-radius: 6px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .12s ease;
        z-index: 50;
    }
    .fm-tip:hover::after,
    .fm-tip:focus-visible::after { opacity: 1; visibility: visible; }
    /* Variante: tooltip alinhado pela direita (termina por baixo do botão),
       para botões junto ao limite direito do ecrã não saírem da tela. */
    .fm-tip-end::after { left: auto; right: 0; transform: none; }
    /* Variante: tooltip à esquerda do botão (ex.: FAB encostado à direita). */
    .fm-tip-left::after {
        top: 50%;
        left: auto;
        right: calc(100% + 8px);
        transform: translateY(-50%);
    }
</style>
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

    window.prosideFileManager = function ({ picker, multiple, view }) {
        return {
            picker, multiple,
            // Estado de UI puramente no cliente (Alpine persiste entre re-renders
            // do Livewire). Nada de entangle: em Livewire v4 devolve um wrapper,
            // não um array nativo.
            view: view || 'grid',
            selected: [],
            filterOpen: false,
            fabOpen: false,
            uploadHover: false,
            menu: { open: false, x: 0, y: 0, file: null, files: [] },
            modal: { open: false, action: null, type: null, text: '', path: '', file: null },
            moveModal: { open: false, target: '' },
            light: { open: false, url: '', type: '' },

            init() {
                // Abertura de modal a partir do FAB / outros emissores.
                this.$root.addEventListener('fm-modal', (e) => this.openModal(e.detail));
            },

            // ---------- Seleção (cliente) ----------
            isSelected(path) { return Array.isArray(this.selected) && this.selected.includes(path); },
            toggleSelect(path, shift) {
                if (!Array.isArray(this.selected)) this.selected = [];
                if (!shift) { this.selected = [path]; return; }
                const i = this.selected.indexOf(path);
                if (i > -1) this.selected.splice(i, 1); else this.selected.push(path);
            },
            selectAllVisible() {
                this.selected = [...new Set(this.allVisiblePaths())];
                this.menu.open = false;
            },
            targetPaths() {
                if (this.menu.files && this.menu.files.length) return [...this.menu.files];
                return this.menu.file ? [this.menu.file.path] : [];
            },

            // ---------- Checkboxes / barra de seleção ----------
            allVisiblePaths() {
                return [...this.$root.querySelectorAll('[data-fm-path]')].map((el) => el.dataset.fmPath);
            },
            // Alterna um item na seleção (semântica de checkbox, sempre aditiva).
            toggleCheck(path) {
                if (!Array.isArray(this.selected)) this.selected = [];
                const i = this.selected.indexOf(path);
                if (i > -1) this.selected.splice(i, 1); else this.selected.push(path);
            },
            allChecked() {
                const all = this.allVisiblePaths();
                return all.length > 0 && all.every((p) => this.selected.includes(p));
            },
            toggleCheckAll() {
                const all = this.allVisiblePaths();
                if (this.allChecked()) this.selected = this.selected.filter((p) => !all.includes(p));
                else this.selected = [...new Set([...this.selected, ...all])];
            },
            // Caminhos sobre os quais agir: seleção atual ou, em falha, o alvo do menu.
            effectivePaths() {
                if (this.selected && this.selected.length) return [...this.selected];
                return this.targetPaths();
            },
            selectionFiles() { return this.itemsFor(this.selected); },
            // Item único selecionado (para ações dependentes do tipo); null se 0 ou >1.
            selectedItem() {
                const items = this.selectionFiles();
                return items.length === 1 ? items[0] : null;
            },
            chooseSelected() {
                if (!this.selected.length) return;
                this.$wire.choose([...this.selected]);
            },
            allSelectedAreFiles() {
                const items = this.selectionFiles();
                return items.length > 0 && items.every((f) => f && ['image', 'video', 'other'].includes(f.type));
            },
            downloadSelected() {
                this.selectionFiles().forEach((f) => {
                    if (!f.url) return;
                    const a = document.createElement('a');
                    a.href = f.url; a.download = f.name || ''; a.target = '_blank';
                    document.body.appendChild(a); a.click(); a.remove();
                });
            },
            deleteSelected() {
                if (!this.selected.length) return;
                this.openModal({ action: 'delete', file: { type: 'multi', name: '' } });
            },
            openMoveModal() {
                if (!this.selected.length) return;
                this.menu.open = false;
                this.moveModal = { open: true, target: '' };
            },
            confirmMove() {
                if (this.moveModal.target === '' || !this.selected.length) return;
                this.$wire.moveItems([...this.selected], this.moveModal.target);
                this.selected = [];
                this.moveModal.open = false;
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
                this.menu = { open: true, x: e.clientX, y: e.clientY, file: { type: 'background', name: 'Opções' }, files: [] };
            },
            // Abre o menu de contexto para a seleção atual (botão "⋯" da barra).
            openSelectionMenu(e) {
                e.preventDefault(); e.stopPropagation();
                if (!this.selected.length) return;
                const items = this.selectionFiles();
                this.menu = { open: true, x: e.clientX, y: e.clientY, file: items[0] || null, files: [...this.selected] };
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
                    return el ? {
                        path: p,
                        type: el.dataset.fmType,
                        url: el.dataset.fmUrl,
                        name: el.dataset.fmName,
                        extension: el.dataset.fmExt,
                        sizeFormatted: el.dataset.fmSize,
                        modified: el.dataset.fmModified,
                    } : { path: p };
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
                else if (m.action === 'delete') {
                    const paths = this.targetPaths().length ? this.targetPaths() : this.effectivePaths();
                    if (paths.length) this.$wire.delete(paths);
                    this.selected = [];
                }
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
                this.selected = [];
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
                this.moveModal.open = false; this.filterOpen = false; this.fabOpen = false;
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
