<?php

namespace Proside\FileManager\Tests;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Proside\FileManager\Livewire\FileManager;
use Proside\FileManager\Support\FileManagerService;

/**
 * Regressões de confinamento entre utilizadores e de exposição pública.
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fm-test');
        config(['file-manager.root_resolver' => null]);
    }

    /** Cria um item no lixo pertencente a outro tenant e devolve o atacante. */
    private function foreignTrashAndAttacker(string $name = 'secret.png'): array
    {
        $victim = new FileManagerService();
        Storage::disk('fm-test')->put("conteudos/vitima/{$name}", 'DADOS DA VITIMA');
        $victim->trash(["conteudos/vitima/{$name}"]);
        $trashed = "apagados/conteudos/vitima/{$name}";
        $this->assertTrue($victim->exists($trashed));

        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);

        return [new FileManagerService(), $trashed];
    }

    public function test_scoped_user_cannot_address_foreign_trash(): void
    {
        [$attacker, $trashed] = $this->foreignTrashAndAttacker();

        $this->expectException(InvalidArgumentException::class);
        $attacker->guard()->normalize($trashed);
    }

    public function test_scoped_user_cannot_delete_foreign_trash(): void
    {
        [$attacker, $trashed] = $this->foreignTrashAndAttacker();

        $results = $attacker->deleteForever([$trashed]);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue(Storage::disk('fm-test')->exists($trashed));
    }

    public function test_scoped_user_cannot_restore_foreign_trash(): void
    {
        [$attacker, $trashed] = $this->foreignTrashAndAttacker();

        $results = $attacker->restore([$trashed]);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue(Storage::disk('fm-test')->exists($trashed));
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/atacante/secret.png'));
    }

    public function test_orphan_trash_item_cannot_be_restored_into_another_root(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);
        $attacker = new FileManagerService();

        // Item no lixo sem sidecar e fora do ramo do atacante.
        Storage::disk('fm-test')->put('apagados/conteudos/vitima/orphan.txt', 'DADOS');

        $results = $attacker->restore(['apagados/conteudos/vitima/orphan.txt']);

        $this->assertFalse($results[0]['success']);
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/atacante/orphan.txt'));
    }

    public function test_scoped_user_reaches_only_own_trash_branch(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);
        $s = new FileManagerService();

        $this->assertSame('apagados/conteudos/atacante', $s->trashRoot());

        Storage::disk('fm-test')->put('conteudos/atacante/meu.txt', 'x');
        $s->trash(['conteudos/atacante/meu.txt']);

        $names = array_column($s->listing($s->trashRoot()), 'name');
        $this->assertSame(['meu.txt'], $names);
    }

    public function test_media_route_does_not_serve_trash(): void
    {
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/apagado.txt', 'SECRETO');
        $s->trash(['conteudos/apagado.txt']);

        $this->get('/file-manager/media/apagados/conteudos/apagado.txt')->assertNotFound();
    }

    public function test_media_route_does_not_serve_meta_sidecars(): void
    {
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/d.txt', 'x');
        $s->trash(['conteudos/d.txt']);

        $this->get('/file-manager/media/apagados/conteudos/d.txt.meta.json')->assertNotFound();
    }

    public function test_media_route_still_serves_normal_content(): void
    {
        new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/publico.txt', 'ok');

        $this->get('/file-manager/media/conteudos/publico.txt')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_svg_is_forced_to_download_not_rendered_inline(): void
    {
        new FileManagerService();
        Storage::disk('fm-test')->put(
            'conteudos/x.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $r = $this->get('/file-manager/media/conteudos/x.svg')->assertOk();

        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    }

    public function test_images_are_still_served_inline(): void
    {
        new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/foto.png', 'binario');

        $r = $this->get('/file-manager/media/conteudos/foto.png')->assertOk();

        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
    }

    // ---------- A pasta principal é intocável ----------

    public function test_scoped_user_cannot_trash_own_root(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/optivisao/dados.txt', 'x');

        $results = $s->trash(['conteudos/optivisao']);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($s->exists('conteudos/optivisao/dados.txt'));
    }

    public function test_full_access_user_cannot_trash_config_root(): void
    {
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/a.txt', 'x');

        $results = $s->trash(['conteudos']);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($s->exists('conteudos/a.txt'));
    }

    public function test_renaming_own_root_is_refused(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/optivisao/dados.txt', 'x');

        try {
            $s->rename('conteudos/optivisao', 'outra');
            $this->fail('devia ter recusado');
        } catch (\InvalidArgumentException $e) {
            // esperado
        }

        $this->assertTrue($s->exists('conteudos/optivisao/dados.txt'));
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/outra'));
    }

    public function test_rename_with_traversal_in_the_name_stays_inside_the_root(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/optivisao/a.txt', 'x');

        // sanitizeName() neutraliza as barras: vira um nome literal, não um salto.
        $target = $s->rename('conteudos/optivisao/a.txt', '../../fuga');

        $this->assertStringStartsWith('conteudos/optivisao/', $target);
        $this->assertTrue($s->exists($target));
        $this->assertFalse(Storage::disk('fm-test')->exists('fuga.txt'));
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/fuga.txt'));
    }

    public function test_moving_own_root_is_refused(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = new FileManagerService();
        $s->createFolder('conteudos/optivisao', 'destino');

        $results = $s->move(['conteudos/optivisao'], 'conteudos/optivisao/destino');

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($s->exists('conteudos/optivisao'));
    }

    public function test_duplicating_root_does_not_write_outside_scope(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = new FileManagerService();
        Storage::disk('fm-test')->put('conteudos/optivisao/a.txt', 'x');

        $out = $s->duplicate(['conteudos/optivisao']);

        $this->assertSame([], $out);
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/optivisao (1)'));
    }

    public function test_isRoot_identifies_every_effective_root(): void
    {
        config(['file-manager.root_resolver' => fn () => ['conteudos/a', 'conteudos/b']]);
        $s = new FileManagerService();

        $this->assertTrue($s->isRoot('conteudos/a'));
        $this->assertTrue($s->isRoot('conteudos/b'));
        $this->assertTrue($s->isRoot('conteudos'));
        $this->assertFalse($s->isRoot('conteudos/a/sub'));
    }
}
