<?php

namespace Proside\FileManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Proside\FileManager\Support\FileManagerService;

class FileManagerServiceTest extends TestCase
{
    private function service(): FileManagerService
    {
        Storage::fake('fm-test');

        return new FileManagerService();
    }

    public function test_creates_unique_folders(): void
    {
        $s = $this->service();

        $a = $s->createFolder('conteudos', 'Fotos');
        $b = $s->createFolder('conteudos', 'Fotos');

        $this->assertSame('conteudos/Fotos', $a);
        $this->assertSame('conteudos/Fotos (1)', $b);
    }

    public function test_listing_returns_folders_and_files(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Sub');
        $s->upload(UploadedFile::fake()->image('pic.png'), 'conteudos');

        $names = array_column($s->listing('conteudos'), 'name');

        $this->assertContains('Sub', $names);
        $this->assertContains('pic.png', $names);
    }

    public function test_filter_images_hides_folders_and_other_files(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Sub');
        $s->upload(UploadedFile::fake()->image('pic.png'), 'conteudos');
        $s->upload(UploadedFile::fake()->create('doc.pdf', 10), 'conteudos');

        $types = array_column($s->listing('conteudos', 'images'), 'type');

        $this->assertContains('image', $types);
        $this->assertNotContains('folder', $types);
        $this->assertNotContains('other', $types);
    }

    public function test_no_folder_filter_hides_folders_keeps_files(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Sub');
        $s->upload(UploadedFile::fake()->image('pic.png'), 'conteudos');
        $s->upload(UploadedFile::fake()->create('doc.pdf', 10), 'conteudos');

        $types = array_column($s->listing('conteudos', 'no-folder'), 'type');

        $this->assertNotContains('folder', $types);
        $this->assertContains('image', $types);
        $this->assertContains('other', $types);
    }

    public function test_trash_and_restore_roundtrip(): void
    {
        $s = $this->service();
        $s->upload(UploadedFile::fake()->image('a.png'), 'conteudos');

        $s->trash(['conteudos/a.png']);
        $this->assertFalse($s->exists('conteudos/a.png'));
        $this->assertTrue($s->exists('apagados/a.png'));

        $s->restore(['apagados/a.png']);
        $this->assertTrue($s->exists('conteudos/a.png'));
        $this->assertFalse($s->exists('apagados/a.png'));
    }

    public function test_prune_removes_expired_items(): void
    {
        config(['file-manager.trash_retention_days' => 30]);
        $s = $this->service();
        $s->upload(UploadedFile::fake()->image('a.png'), 'conteudos');
        $s->trash(['conteudos/a.png']);

        $this->travel(31)->days();
        $removed = $s->pruneTrash();

        $this->assertSame(1, $removed);
        $this->assertFalse($s->exists('apagados/a.png'));
    }

    public function test_path_traversal_is_blocked(): void
    {
        $s = $this->service();

        $this->expectException(\InvalidArgumentException::class);
        $s->listing('conteudos/../../etc');
    }

    public function test_root_resolver_confines_user(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = $this->service();

        $this->assertSame('conteudos/optivisao', $s->root());
        $this->assertTrue($s->isScoped());

        $s->createFolder('conteudos/optivisao', 'fotos');
        $this->assertContains(
            'conteudos/optivisao/fotos',
            array_column($s->listing('conteudos/optivisao'), 'path')
        );

        // Aceder acima da raiz efetiva é bloqueado.
        $this->expectException(\InvalidArgumentException::class);
        $s->listing('conteudos');
    }

    public function test_resolver_full_access_when_equals_config_root(): void
    {
        config(['file-manager.root_resolver' => fn () => 'conteudos']);
        $s = $this->service();

        $this->assertSame('conteudos', $s->root());
        $this->assertFalse($s->isScoped());
    }

    public function test_root_resolver_supports_multiple_roots(): void
    {
        config(['file-manager.root_resolver' => fn () => ['conteudos/a', 'conteudos/b']]);
        $s = $this->service();

        $this->assertSame('conteudos/a', $s->root()); // primária = primeira
        $this->assertSame(['conteudos/a', 'conteudos/b'], $s->roots());
        $this->assertTrue($s->isScoped());

        // Pode navegar em ambas as raízes.
        $s->createFolder('conteudos/a', 'x');
        $s->createFolder('conteudos/b', 'y');
        $this->assertContains('conteudos/a/x', array_column($s->listing('conteudos/a'), 'path'));
        $this->assertContains('conteudos/b/y', array_column($s->listing('conteudos/b'), 'path'));
    }

    public function test_multiple_roots_block_outside_access(): void
    {
        config(['file-manager.root_resolver' => fn () => ['conteudos/a', 'conteudos/b']]);
        $s = $this->service();

        // Uma raiz não listada continua bloqueada.
        $this->expectException(\InvalidArgumentException::class);
        $s->listing('conteudos/c');
    }

    public function test_resolver_invalid_root_is_ignored(): void
    {
        // 'fora' está fora do configRoot => ignorado; fica só a raiz válida.
        config(['file-manager.root_resolver' => fn () => ['conteudos/a', 'fora/x']]);
        $s = $this->service();

        $this->assertSame(['conteudos/a'], $s->roots());
    }

    public function test_trash_is_scoped_by_origin(): void
    {
        $full = $this->service();
        $full->upload(UploadedFile::fake()->image('outside.png'), 'conteudos');
        $full->trash(['conteudos/outside.png']);

        config(['file-manager.root_resolver' => fn () => 'conteudos/optivisao']);
        $s = new FileManagerService();
        $s->upload(UploadedFile::fake()->image('mine.png'), 'conteudos/optivisao');
        $s->trash(['conteudos/optivisao/mine.png']);

        $names = array_column($s->listing('apagados'), 'name');
        $this->assertContains('mine.png', $names);
        $this->assertNotContains('outside.png', $names);
    }

    public function test_rename_preserves_extension(): void
    {
        $s = $this->service();
        $s->upload(UploadedFile::fake()->image('old.png'), 'conteudos');

        $new = $s->rename('conteudos/old.png', 'novo');

        $this->assertSame('conteudos/novo.png', $new);
        $this->assertTrue($s->exists('conteudos/novo.png'));
    }
}
