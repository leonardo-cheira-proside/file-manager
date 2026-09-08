<?php

namespace Proside\FileManager\Tests;

use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Proside\FileManager\Livewire\FileManager;
use Proside\FileManager\Support\FileManagerService;

/**
 * Regressões de correção e robustez: lotes parciais, valores de retorno,
 * ausência de 500 em caminhos inválidos, partilha assinada e auditoria.
 */
class RobustnessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fm-test');
        config(['file-manager.root_resolver' => null]);
    }

    private function service(): FileManagerService
    {
        return new FileManagerService();
    }

    // ---------- Lotes parciais ----------

    public function test_trash_batch_continues_after_an_invalid_path(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/bom.txt', 'g');

        $results = $s->trash(['conteudos/../mau', 'conteudos/bom.txt']);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertTrue($s->exists('apagados/conteudos/bom.txt'));
    }

    public function test_delete_forever_batch_continues_after_an_invalid_path(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/bom.txt', 'g');
        $s->trash(['conteudos/bom.txt']);

        $results = $s->deleteForever(['conteudos/../mau', 'apagados/conteudos/bom.txt']);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertFalse($s->exists('apagados/conteudos/bom.txt'));
    }

    public function test_restore_batch_continues_after_an_invalid_path(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/bom.txt', 'g');
        $s->trash(['conteudos/bom.txt']);

        $results = $s->restore(['conteudos/../mau', 'apagados/conteudos/bom.txt']);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertTrue($s->exists('conteudos/bom.txt'));
    }

    // ---------- Valores de retorno ----------

    public function test_rename_returns_the_path_actually_written(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/a.png', 'A');
        Storage::disk('fm-test')->put('conteudos/b.png', 'B');

        $returned = $s->rename('conteudos/b.png', 'a');

        $this->assertSame('conteudos/a (1).png', $returned);
        $this->assertTrue($s->exists($returned));
        $this->assertSame('B', Storage::disk('fm-test')->get($returned));
        $this->assertSame('A', Storage::disk('fm-test')->get('conteudos/a.png'));
    }

    // ---------- Sem 500 em caminhos inválidos ----------

    public function test_move_onto_a_file_fails_gracefully(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/a.txt', 'A');
        Storage::disk('fm-test')->put('conteudos/sub/b.txt', 'B');

        $results = $s->move(['conteudos/sub/b.txt'], 'conteudos/a.txt');

        $this->assertFalse($results[0]['success']);
        $this->assertSame('A', Storage::disk('fm-test')->get('conteudos/a.txt'));
        $this->assertTrue($s->exists('conteudos/sub/b.txt'));
    }

    public function test_move_into_an_empty_folder_still_works(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Vazia');
        Storage::disk('fm-test')->put('conteudos/sub/b.txt', 'B');

        $results = $s->move(['conteudos/sub/b.txt'], 'conteudos/Vazia');

        $this->assertTrue($results[0]['success']);
        $this->assertSame('B', Storage::disk('fm-test')->get('conteudos/Vazia/b.txt'));
    }

    public function test_copy_into_an_empty_folder_still_works(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Vazia');
        Storage::disk('fm-test')->put('conteudos/sub/b.txt', 'B');

        $results = $s->copy(['conteudos/sub/b.txt'], 'conteudos/Vazia');

        $this->assertTrue($results[0]['success']);
        $this->assertSame('B', Storage::disk('fm-test')->get('conteudos/Vazia/b.txt'));
    }

    public function test_copy_onto_a_file_fails_gracefully(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/a.txt', 'A');
        Storage::disk('fm-test')->put('conteudos/sub/b.txt', 'B');

        $results = $s->copy(['conteudos/sub/b.txt'], 'conteudos/a.txt');

        $this->assertFalse($results[0]['success']);
        $this->assertSame('A', Storage::disk('fm-test')->get('conteudos/a.txt'));
    }

    public function test_open_with_a_forbidden_path_falls_back_to_root(): void
    {
        Storage::disk('fm-test')->put('conteudos/vitima/s.txt', 'x');
        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);

        Livewire::test(FileManager::class)
            ->call('open', 'conteudos/vitima')
            ->assertOk()
            ->assertSet('path', 'conteudos/atacante');
    }

    public function test_tampered_path_property_does_not_break_the_render(): void
    {
        Storage::disk('fm-test')->put('conteudos/vitima/s.txt', 'x');
        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);

        Livewire::test(FileManager::class)
            ->set('path', 'conteudos/vitima')
            ->assertOk()
            ->assertDontSee('s.txt');
    }

    public function test_error_messages_do_not_leak_internal_paths(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);
        $s = new FileManagerService();

        try {
            $s->guard()->normalize('conteudos/vitima');
            $this->fail('devia ter recusado');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringNotContainsString('vitima', $e->getMessage());
        }
    }

    public function test_load_more_is_capped(): void
    {
        $c = Livewire::test(FileManager::class);
        for ($i = 0; $i < 20; $i++) {
            $c->call('loadMore');
        }
        $c->assertSet('limit', 600);
    }

    // ---------- Lixo ----------

    public function test_trashed_folder_exposes_expiry(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Pasta');
        Storage::disk('fm-test')->put('conteudos/Pasta/x.png', 'x');

        $s->trash(['conteudos/Pasta']);
        $entries = $s->listing($s->trashRoot());

        $this->assertCount(1, $entries);
        $this->assertSame('folder', $entries[0]['type']);
        $this->assertArrayHasKey('expiresAt', $entries[0]);
    }

    public function test_same_name_from_different_folders_no_longer_collides(): void
    {
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/a/relatorio.txt', '1');
        Storage::disk('fm-test')->put('conteudos/b/relatorio.txt', '2');

        $s->trash(['conteudos/a/relatorio.txt', 'conteudos/b/relatorio.txt']);

        $this->assertSame('1', Storage::disk('fm-test')->get('apagados/conteudos/a/relatorio.txt'));
        $this->assertSame('2', Storage::disk('fm-test')->get('apagados/conteudos/b/relatorio.txt'));

        $s->restore(['apagados/conteudos/a/relatorio.txt', 'apagados/conteudos/b/relatorio.txt']);
        $this->assertSame('1', Storage::disk('fm-test')->get('conteudos/a/relatorio.txt'));
        $this->assertSame('2', Storage::disk('fm-test')->get('conteudos/b/relatorio.txt'));
    }

    public function test_prune_removes_expired_nested_items(): void
    {
        config(['file-manager.trash_retention_days' => 30]);
        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/sub/a.png', 'x');
        $s->trash(['conteudos/sub/a.png']);

        $this->travel(31)->days();

        $this->assertSame(1, $s->pruneTrash());
        $this->assertFalse($s->exists('apagados/conteudos/sub/a.png'));
    }

    // ---------- Sanitização de nomes ----------

    /** @dataProvider badNames */
    public function test_dangerous_names_are_sanitised(string $input): void
    {
        $name = $this->service()->guard()->sanitizeName($input);

        $this->assertNotSame('.', $name);
        $this->assertNotSame('..', $name);
        $this->assertStringNotContainsString('/', $name);
        $this->assertStringNotContainsString('\\', $name);
        $this->assertLessThanOrEqual(200, mb_strlen($name));
    }

    public static function badNames(): array
    {
        return [
            ['..'], ['.'], ['...'], ['../../etc/passwd'], ['..\\..\\windows'],
            ["nome\x00nulo"], ['  ..  '], [str_repeat('a', 500)], [''],
        ];
    }

    public function test_create_folder_with_dotdot_makes_a_safe_name(): void
    {
        $s = $this->service();

        $path = $s->createFolder('conteudos', '..');

        $this->assertSame('conteudos/sem_nome', $path);
        $this->assertTrue($s->exists('conteudos/sem_nome'));
    }

    public function test_dotfiles_are_preserved(): void
    {
        $this->assertSame('.gitignore', $this->service()->guard()->sanitizeName('.gitignore'));
    }

    // ---------- Auditoria ----------

    public function test_destructive_operations_are_audited(): void
    {
        $log = storage_path('logs/fm-audit-test.log');
        @unlink($log);

        config([
            'logging.channels.fmaudit' => ['driver' => 'single', 'path' => $log],
            'file-manager.audit.enabled' => true,
            'file-manager.audit.channel' => 'fmaudit',
        ]);

        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/a.txt', 'x');
        $s->trash(['conteudos/a.txt']);
        $s->restore(['apagados/conteudos/a.txt']);
        $s->rename('conteudos/a.txt', 'b');

        $contents = file_get_contents($log);

        $this->assertStringContainsString('file-manager.trash', $contents);
        $this->assertStringContainsString('file-manager.restore', $contents);
        $this->assertStringContainsString('file-manager.rename', $contents);

        @unlink($log);
    }

    public function test_audit_can_be_disabled(): void
    {
        $log = storage_path('logs/fm-audit-off.log');
        @unlink($log);

        config([
            'logging.channels.fmaudit' => ['driver' => 'single', 'path' => $log],
            'file-manager.audit.enabled' => false,
            'file-manager.audit.channel' => 'fmaudit',
        ]);

        $s = $this->service();
        Storage::disk('fm-test')->put('conteudos/a.txt', 'x');
        $s->trash(['conteudos/a.txt']);

        $this->assertFalse(file_exists($log) && str_contains((string) file_get_contents($log), 'file-manager.trash'));

        @unlink($log);
    }
}
