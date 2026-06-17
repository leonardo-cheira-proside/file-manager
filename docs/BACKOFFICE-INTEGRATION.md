# Integração no Backoffice Proside (Laravel 10.49)

Guia passo-a-passo para substituir o File Manager Next.js (iframe + JWT + proxy) pelo
package `proside/file-manager` (Livewire v4 nativo). Aplicar do lado do Backoffice.

---

## 0. Pré-requisito: Livewire v4

O Backoffice tem **Livewire v3.7.15**, travado por `spatie/laravel-livewire-wizard`
(`^2.11|^3.5.20`). Esse package **não é usado** (os "WizardControllers" são custom; o Livewire
só aparece como `@livewireScripts`/`@livewireStyles`). Passos:

```bash
# 1) Remover a dependência não usada que trava o Livewire em v3
composer remove spatie/laravel-livewire-wizard

# 2) Subir o Livewire para v4
composer require livewire/livewire:^4.0
```

Livewire v4 suporta Laravel 10+ / PHP 8.1+, logo é compatível com o Laravel 10.49.
Confirme que `@livewireStyles` (head) e `@livewireScripts` (antes de `</body>`) continuam em
`resources/views/layouts/app.blade.php` — já estão. Limpe caches:

```bash
php artisan optimize:clear
```

> Se mais tarde precisar de wizards Livewire, use uma versão de `spatie/laravel-livewire-wizard`
> compatível com Livewire 4, ou mantenha os wizards custom atuais (recomendado, já que funcionam).

---

## 1. Instalar o package

Enquanto não está publicado num repositório/Packagist, use um *path repository* no
`composer.json` do Backoffice:

```json
"repositories": [
    { "type": "path", "url": "../../NOVO-FILE-MANAGER" }
],
```

```bash
composer require proside/file-manager:@dev
php artisan storage:link
```

(Após publicar em Git/Packagist: `composer require proside/file-manager`.)

---

## 2. Tailwind

No `tailwind.config.js` do Backoffice, adicione ao `content`:

```js
'./vendor/proside/file-manager/resources/views/**/*.blade.php',
```

A paleta usada é a `teal` padrão (igual ao antigo `proximo`), por isso não é preciso config extra.
Recompile: `npm run build`.

---

## 3. Migrar os formulários (picker)

Os 15 ficheiros que usam `<x-file-manager-modal ...>` passam a usar `<x-file-manager::picker ...>`
— **os props `input-name` e `:value` mantêm-se iguais**:

```
resources/views/kioskscreens/form.blade.php
resources/views/terminals/form.blade.php
resources/views/terminals/formCreateStep.blade.php
resources/views/terminals/wizard.blade.php
resources/views/alerts/alert-terminal/form.blade.php
resources/views/alerts/alert-terminal/form-wizard.blade.php
resources/views/alerts/alert-channel/form.blade.php
resources/views/sites/formCreateStep.blade.php
resources/views/components/wizard/add/queue.blade.php
resources/views/components/wizard/add/kioskscreen.blade.php
resources/views/links/form.blade.php
resources/views/parent/form.blade.php
resources/views/queues/formCreateStep.blade.php
resources/views/applications/wizard.blade.php
resources/views/channels/stepMedia.blade.php
```

**Opção A — substituição direta (recomendada):**

```blade
{{-- antes --}}
<x-file-manager-modal input-name="gfqueue_image" :value="$value" />
{{-- depois --}}
<x-file-manager::picker input-name="gfqueue_image" :value="$value" />
```

**Opção B — zero alterações nos formulários:** transforme o componente existente
`resources/views/components/file-manager-modal.blade.php` num *wrapper* (assim os 15 ficheiros não
mudam):

```blade
@props(['inputName' => 'selected_file_path', 'value' => ''])
<x-file-manager::picker :input-name="$inputName" :value="$value" />
```

Para campos com várias imagens, acrescente `multiple` (ou use `input-name="campo[]"`).

---

## 4. Migrar os controllers

4 controllers usavam o `FileManagerController` para ler/guardar conteúdo via HTTP+JWT. Agora os
ficheiros estão no **disco local** do Backoffice, por isso lê-se diretamente com o `Storage`:

```
app/Http/Controllers/Web/TerminalAlertsController.php
app/Http/Controllers/Web/QueueCreateWizardController.php
app/Http/Controllers/Web/ChannelAlertsController.php
app/Http/Controllers/Web/TerminalCreateWizardController.php
```

Substituições:

```php
use Illuminate\Support\Facades\Storage;

$disk = config('file-manager.disk'); // 'public'

// Antes: app(FileManagerController::class)->getFileContent($path)
$content = Storage::disk($disk)->get($path);

// Antes: app(FileManagerController::class)->uploadAndGetPath($request)
$path = $request->file('campo')->store(config('file-manager.root'), $disk);
// $path já é tipo "conteudos/abc.png" — guarde-o na coluna como antes.
```

Os caminhos guardados na BD (`conteudos/...`) **continuam válidos**. Para mostrar imagens use
`Storage::disk('public')->url($path)` (→ `/storage/conteudos/...`) ou a rota `file-manager.media`.

---

## 5. Remover o que ficou obsoleto

Pode eliminar (depois de confirmar que nada mais usa):

- `app/Http/Controllers/Web/FileManagerController.php`
- `resources/views/components/file-manager-modal.blade.php` *(a menos que use a Opção B do passo 3)*
- Rotas em `routes/web.php` do grupo `file-manager.` (`upload`, `delete`, `file`, `token`)
- `config/services.php` → bloco `file_manager`
- `.env`: `FILE_MANAGER_URL`, `FILE_MANAGER_WORKFLOWS_PATH`
- (Opcional) `config/jwt.php`, `storage/app/keys/*.pem` se o JWT só servia o File Manager

> Verifique `WorkflowsController` / `KioskScreenController`: usam `services.file_manager.workflows_path`
> e o proxy de imagens. Atualize-os para ler do disco local (passo 4).

---

## 6. Migrar os ficheiros existentes

Copie o conteúdo atual do serviço Next.js para o disco do Backoffice (uma vez):

```bash
# do antigo public/ do file manager para o storage público do backoffice
rsync -av /caminho/file-manager-proside/public/conteudos/  storage/app/public/conteudos/
rsync -av /caminho/file-manager-proside/public/apagados/   storage/app/public/apagados/
```

---

## 7. Agendar limpeza do lixo

`app/Console/Kernel.php`:

```php
$schedule->command('file-manager:prune-trash')->daily();
```

---

## 8. Permissões por utilizador (gflevel_ctvdir)

Cada utilizador é confinado à pasta do seu perfil. Já está implementado:

- `app/FileManager/LevelRootResolver.php` — lê os perfis (`gflevel`) ligados ao
  utilizador (`gfleveluser`) e o campo `gflevel_ctvdir`. Devolve a raiz efetiva
  (ex.: `conteudos/optivisao`) ou `null` para acesso total.
- `config/file-manager.php` (publicado) — `'root_resolver' => \App\FileManager\LevelRootResolver::class`.

Regras: `ctvdir` vazio / `root` / igual à raiz da config (`conteudos`) → **vê tudo**;
caso contrário só vê `conteudos/<ctvdir>` para baixo. Se o utilizador tiver vários
perfis, é usada a 1ª `ctvdir` (a menos que algum seja "acesso total"). O lixo mostra
apenas o que esse utilizador apagou.

> Para alterar a política (ex.: suportar várias raízes em simultâneo) basta editar
> `LevelRootResolver`. O package é agnóstico ao modelo de dados.

## Resultado

- O File Manager corre dentro do Backoffice, com a sessão/autenticação existentes.
- Sem iframe, sem JWT, sem serviço Node a manter.
- Os formulários e os caminhos guardados na BD continuam a funcionar.
