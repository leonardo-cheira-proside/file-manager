# Proside File Manager (Livewire v4)

Gestor de ficheiros reutilizável para **Laravel 10/11/12** construído integralmente em **Livewire v4**.
Substitui o antigo File Manager em Next.js/React, mantendo **paridade funcional** e melhorando a
arquitetura: sem iframe, sem JWT, sem serviço externo — corre **dentro** da própria aplicação Laravel
e usa a autenticação/sessão existente.

> Compatível com Laravel 10.49 e PHP 8.1+. Requer Livewire **^4.0**.

---

## Funcionalidades

- Árvore de diretórios lateral com lazy-load e expandir/colapsar
- Vistas em **grelha** e **lista**
- Filtros (Tudo / Pastas / Imagens / Vídeos) e ordenação (A–Z / Z–A)
- Breadcrumbs de navegação
- Seleção simples e múltipla (Shift)
- Menu de contexto: criar subpasta, renomear, eliminar, visualizar, descarregar, **escolher**
- Upload por botão flutuante (FAB) **e** por arrastar ficheiros do sistema
- Mover por drag & drop (incluindo múltiplos itens) para pastas ou para a árvore
- **Lixo** (`apagados`) com retenção configurável e expiração automática
- **Restaurar** itens do lixo _(novo)_ e eliminação definitiva
- Pré-visualização (lightbox) de imagens e vídeos
- Pesquisa funcional por nome _(o FM antigo não pesquisava)_
- Pronto para **picker** de ficheiros em formulários (`<x-file-manager::picker>`)
- Multi-disco (local/public, S3, …) via Filesystem do Laravel
- Traduções PT/EN, totalmente publicáveis

---

## Instalação

```bash
composer require proside/file-manager
```

O Service Provider, rotas, componente Livewire e componentes Blade são **registados automaticamente**
(package discovery).

### 1. Garantir o disco e o symlink

Por omissão usa o disco `public`. Crie o symlink uma vez:

```bash
php artisan storage:link
```

### 2. (Opcional) Publicar recursos

```bash
php artisan vendor:publish --tag=file-manager-config    # config/file-manager.php
php artisan vendor:publish --tag=file-manager-views     # resources/views/vendor/file-manager
php artisan vendor:publish --tag=file-manager-lang      # lang/vendor/file-manager
php artisan vendor:publish --tag=file-manager-assets    # public/vendor/file-manager/file-manager.css
```

### 3. Tailwind (importante)

O package usa classes utilitárias Tailwind (paleta `proximo`, equivalente ao antigo `proximo`).
Se a sua app compila Tailwind, adicione o caminho das vistas do package ao `content` do
`tailwind.config.js` para que as classes sejam geradas:

```js
content: [
    // ...
    './vendor/proside/file-manager/resources/views/**/*.blade.php',
],
```

### 4. Agendar a limpeza do lixo

Em `app/Console/Kernel.php`:

```php
$schedule->command('file-manager:prune-trash')->daily();
```

---

## Utilização

### Página inteira

Já existe a rota `GET /file-manager` (configurável). Ou embeba o componente onde quiser:

```blade
<div style="height: 80vh">
    <livewire:file-manager />
</div>
```

### Picker em formulários (drop-in)

Substitui o antigo `<x-file-manager-modal>` com a **mesma API de props**:

```blade
{{-- Um ficheiro --}}
<x-file-manager::picker input-name="gfqueue_image" :value="$queue->image ?? ''" />

{{-- Vários ficheiros --}}
<x-file-manager::picker input-name="imagens[]" :value="$existing" multiple />

{{-- Forçar apenas imagens (ou 'videos') --}}
<x-file-manager::picker input-name="icon" filter="images" />
```

O ficheiro escolhido fica num `<input type="hidden" name="...">` — o `submit` do formulário
funciona exatamente como antes. Ao escolher, o componente emite o evento Livewire
`file-manager-selected` (`{ paths: [...] }`).

---

## Permissões por utilizador (scoping)

O File Manager pode confinar cada utilizador a uma ou mais pastas-raiz próprias.
Define um **resolver** que devolve a(s) raiz(es) efetiva(s) do utilizador atual
(relativas ao disco), ou `null` para acesso total:

```php
// config/file-manager.php
'root_resolver' => \App\FileManager\LevelRootResolver::class, // invocável: __invoke(): null|string|array
```

Regras aplicadas pelo package quando há resolver:

- devolve `null`, `''`, ou um valor igual à raiz da config → **acesso total**;
- devolve `"conteudos/optivisao"` → o utilizador só vê **essa pasta para baixo**
  (árvore, breadcrumbs, navegação e operações ficam confinadas; aceder acima é
  bloqueado por `PathGuard`); o **lixo** mostra apenas o que esse utilizador apagou
  (via `originalPath`).
- devolve um **array** (ex.: `['conteudos/a', 'conteudos/b']`) → o utilizador vê
  **várias raízes**: cada uma aparece na sidebar com a sua árvore, abre-se na
  primeira, e a navegação fica confinada a qualquer uma delas. Caminhos fora da
  raiz da config são ignorados (não escalam privilégios). O lixo é partilhado,
  filtrado pela origem de qualquer das raízes.

Exemplo de resolver (Backoffice Proside — perfil `gflevel` ligado por `gfleveluser`,
campo `gflevel_ctvdir`): ver [`docs/BACKOFFICE-INTEGRATION.md`](docs/BACKOFFICE-INTEGRATION.md).
O resolver pode ser um **class-string invocável** (compatível com `config:cache`) ou um `callable`.

## Configuração (`config/file-manager.php`)

| Chave                  | Omissão      | Descrição                                                                        |
| ---------------------- | ------------ | -------------------------------------------------------------------------------- |
| `disk`                 | `public`     | Disco do Filesystem                                                              |
| `root`                 | `conteudos`  | Pasta base navegável                                                             |
| `trash`                | `apagados`   | Pasta do lixo                                                                    |
| `trash_retention_days` | `30`         | Dias até eliminação definitiva                                                   |
| `root_resolver`        | `null`       | Resolver da raiz por utilizador (scoping); `null` = acesso total                 |
| `uploads.max_size`     | `51200` (KB) | Tamanho máximo por ficheiro                                                      |
| `uploads.mimes`        | `null`       | Mimes aceites (null = todos)                                                     |
| `media_url`            | `route`      | `route` (seguro, qualquer disco/nome) / `storage` (direto, mais rápido) / `auto` |
| `route.*`              | —            | Prefixo, middleware e rota full-page                                             |

Variáveis `.env`: `FILE_MANAGER_DISK`, `FILE_MANAGER_ROOT`, `FILE_MANAGER_TRASH`,
`FILE_MANAGER_TRASH_DAYS`, `FILE_MANAGER_MAX_UPLOAD`, `FILE_MANAGER_MEDIA_URL`,
`FILE_MANAGER_ROUTE`, `FILE_MANAGER_ROUTE_PREFIX`.

---

## Correspondência Next.js → Livewire

| Antigo (Next.js/React)                        | Novo (Livewire v4)                                             |
| --------------------------------------------- | -------------------------------------------------------------- |
| `FileManagerContext.js` (estado global React) | Propriedades públicas + `#[Computed]` em `FileManager.php`     |
| `FileManager.jsx` (layout)                    | `resources/views/livewire/file-manager.blade.php`              |
| `Files.jsx` (grid/lista)                      | `partials/grid-item.blade.php`, `partials/list-item.blade.php` |
| `NavItem.jsx` (árvore)                        | `partials/tree-node.blade.php` + `tree()` computed             |
| `ContextMenu.jsx`                             | `partials/context-menu.blade.php` (Alpine)                     |
| `Modal.jsx`                                   | `partials/modal.blade.php` (Alpine)                            |
| `ShowFile.jsx` (lightbox)                     | `partials/lightbox.blade.php`                                  |
| `Filters.jsx`, `BreadCrumbs.js`               | toolbar na view principal + `breadcrumbs()`                    |
| `AddFile.jsx` (FAB + upload)                  | FAB na view + `wire:model="uploads"` + `updatedUploads()`      |
| `api/files`, `api/files/tree`                 | `FileManagerService::listing()` / `tree()`                     |
| `api/folders`, `api/rename`, `api/move`       | `createFolder()` / `rename()` / `move()`                       |
| `api/delete`, `api/delete/cleanup`            | `trash()` + `file-manager:prune-trash`                         |
| `api/upload`, `image/route.js`                | `upload()` + `MediaController` (rota `file-manager.media`)     |
| `verify-token`, `TokenListener.jsx` (JWT)     | **eliminado** — usa auth/sessão do Laravel                     |
| iframe + `postMessage(SELECTED_FILE)`         | `<x-file-manager::picker>` + evento `file-manager-selected`    |

---

## Melhorias face à versão anterior

- **Sem serviço externo**: deixa de ser preciso `FILE_MANAGER_URL`, proxy HTTP, JWT RS256 e chaves `.pem`.
- **Segurança**: auth nativa do Laravel + `PathGuard` contra path traversal.
- **Retenção do lixo correta**: era 60s no código (texto dizia 30 dias); agora é configurável (30 por omissão).
- **Restaurar** itens do lixo (não existia).
- **Pesquisa funcional** (não existia).
- **Multi-disco** via Filesystem (S3-ready), em vez de `fs` direto no `public/`.
- **Navegação preservada** ao filtrar por imagens/vídeos (pastas continuam visíveis).
- Limpeza do lixo por **comando agendado** em vez de polling no cliente.

Integração no Backoffice Proside: ver [`docs/BACKOFFICE-INTEGRATION.md`](docs/BACKOFFICE-INTEGRATION.md).
