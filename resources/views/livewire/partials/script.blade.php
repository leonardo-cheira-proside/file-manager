@assets
@include('file-manager::livewire.partials.thumb-cache')
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

    window.prosideFileManager = function ({ picker, multiple, view, downloadBase, zipUrl, csrf }) {
        return {
            picker, multiple, downloadBase, zipUrl, csrf,
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
            pending: [],
            onLeave: null,
            sweeper: null,
            toast: '',

            init() {
                // Abertura de modal a partir do FAB / outros emissores.
                this.$root.addEventListener('fm-modal', (e) => this.openModal(e.detail));
                // Ao mudar de pasta, desseleciona tudo e fecha o menu.
                this.$wire.on('fm-navigated', () => { this.selected = []; this.menu.open = false; });
                // Rede de segurança: o servidor confirma que o ficheiro chegou.
                // Sem isto o placeholder podia sobrepor-se ao item já listado.
                this.$wire.on('file-manager-uploaded', (e) => {
                    const folder = (e && e.path) || (e && e[0] && e[0].path);
                    this.pending = folder
                        ? this.pending.filter((p) => p.path !== folder)
                        : [];
                });
                // Sair a meio aborta o upload: o pedido morre com a página.
                this.onLeave = (ev) => {
                    if (!this.pending.length) return;
                    ev.preventDefault();
                    ev.returnValue = '';
                    return '';
                };
                window.addEventListener('beforeunload', this.onLeave);

                // Se o callback do Livewire nunca chegar (upload abortado, 503
                // do servidor), o pendente ficava eterno: placeholder para
                // sempre e, pior, o aviso acima a bloquear a navegação. Cada
                // pendente é descartado se estiver 60s sem dar sinal.
                this.sweeper = setInterval(() => {
                    const cutoff = Date.now() - 60000;
                    if (this.pending.some((p) => (p.at || 0) <= cutoff)) {
                        this.pending = this.pending.filter((p) => (p.at || 0) > cutoff);
                    }
                }, 15000);
            },

            destroy() {
                window.removeEventListener('beforeunload', this.onLeave);
                clearInterval(this.sweeper);
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
                if (!this.selected.length) return;
                const items = this.selectionFiles();
                if (this.selected.length === 1 && items[0] && items[0].type !== 'folder') {
                    window.location = this.downloadBase + '/' + this.selected[0].split('/').map(encodeURIComponent).join('/');
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = this.zipUrl;
                const tok = document.createElement('input');
                tok.type = 'hidden'; tok.name = '_token'; tok.value = this.csrf;
                form.appendChild(tok);
                this.selected.forEach((p) => {
                    const i = document.createElement('input');
                    i.type = 'hidden'; i.name = 'paths[]'; i.value = p;
                    form.appendChild(i);
                });
                document.body.appendChild(form); form.submit(); form.remove();
            },
            deleteSelected() {
                if (!this.selected.length) return;
                this.openModal({ action: 'delete', file: { type: 'multi', name: '' } });
            },
            openMoveModal() {
                if (!this.selected.length) return;
                this.menu.open = false;
                this.moveModal = { open: true, target: '', mode: 'move' };
            },
            openCopyModal() {
                if (!this.selected.length) return;
                this.menu.open = false;
                this.moveModal = { open: true, target: '', mode: 'copy' };
            },
            confirmMove() {
                if (this.moveModal.target === '' || !this.selected.length) return;
                if (this.moveModal.mode === 'copy') {
                    this.$wire.copyItems([...this.selected], this.moveModal.target);
                } else {
                    this.$wire.moveItems([...this.selected], this.moveModal.target);
                }
                this.selected = [];
                this.moveModal.open = false;
            },
            // ---------- Atalhos de teclado ----------
            onShortcut(e) {
                // Ignora quando se está a escrever num campo.
                const t = e.target;
                if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
                if (this.modal.open || this.moveModal.open || this.menu.open) return;
                const meta = e.ctrlKey || e.metaKey;
                if (e.key === 'Delete' && this.selected.length) { e.preventDefault(); this.deleteSelected(); }
                else if (meta && e.key.toLowerCase() === 'a') { e.preventDefault(); this.selectAllVisible(); }
                else if (meta && e.key.toLowerCase() === 'c' && this.selected.length) { e.preventDefault(); this.openCopyModal(); }
                else if (meta && e.key.toLowerCase() === 'x' && this.selected.length) { e.preventDefault(); this.openMoveModal(); }
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
                // A raiz age sobre si própria e nunca entra na seleção múltipla,
                // senão as ações em massa da barra passavam a apontar-lhe.
                if (file.isRoot) {
                    this.selected = [];
                    this.menu = { open: true, x: e.clientX, y: e.clientY, file, files: [] };
                    return;
                }
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
                // A partir da raiz do componente (o $root pode ser um x-data aninhado,
                // ex.: o dropdown "Mais opções", que não contém os itens da grelha).
                const root = (this.$root && this.$root.closest('.fm-root')) || document;
                return paths.map((p) => {
                    const el = root.querySelector(`[data-fm-path="${CSS.escape(p)}"]`);
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
                this.startUpload([...e.dataTransfer.files]);
            },
            // Mostra um placeholder por ficheiro (nome + loader) enquanto sobe.
            // O servidor só sabe do ficheiro no fim, por isso este estado é do cliente.
            startUpload(files) {
                if (!files || !files.length) return;
                const folder = this.$wire.path;
                const batch = files.map((f) => ({
                    id: (crypto.randomUUID ? crypto.randomUUID() : String(Math.random())),
                    name: f.name,
                    path: folder,
                    at: Date.now(),
                }));
                this.pending.push(...batch);
                const done = () => { this.pending = this.pending.filter((p) => !batch.includes(p)); };
                // O progresso já não desenha nada; serve só de sinal de vida
                // para o varredor acima saber que o upload ainda está vivo.
                this.$wire.uploadMultiple('uploads', files, done, done, () => {
                    const now = Date.now();
                    batch.forEach((b) => { b.at = now; });
                });
            },
            // O indicador de upload pertence à pasta de destino: navegar para
            // outra pasta não arrasta os placeholders atrás.
            pendingHere() {
                return this.pending.filter((p) => p.path === this.$wire.path);
            },

            // ---------- Copiar URL ----------
            // O URL de media já vem no próprio item (data-fm-url), por isso
            // isto é puramente do cliente — não há ida ao servidor.
            copyUrl(file) {
                const url = file && file.url;
                if (!url) return;
                this.menu.open = false;
                this.writeClipboard(url);
            },
            writeClipboard(text) {
                const show = () => {
                    this.toast = @js(__('file-manager::file-manager.url_copied'));
                    setTimeout(() => { this.toast = ''; }, 2500);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(show).catch(() => window.prompt('', text));
                    return;
                }
                const ta = document.createElement('textarea');
                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); show(); } catch (err) { window.prompt('', text); }
                ta.remove();
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
