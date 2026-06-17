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

    public function test_filter_images_keeps_folders(): void
    {
        $s = $this->service();
        $s->createFolder('conteudos', 'Sub');
        $s->upload(UploadedFile::fake()->image('pic.png'), 'conteudos');
        $s->upload(UploadedFile::fake()->create('doc.pdf', 10), 'conteudos');

        $types = array_column($s->listing('conteudos', 'images'), 'type');

        $this->assertContains('folder', $types);
        $this->assertContains('image', $types);
        $this->assertNotContains('other', $types);
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

    public function test_rename_preserves_extension(): void
    {
        $s = $this->service();
        $s->upload(UploadedFile::fake()->image('old.png'), 'conteudos');

        $new = $s->rename('conteudos/old.png', 'novo');

        $this->assertSame('conteudos/novo.png', $new);
        $this->assertTrue($s->exists('conteudos/novo.png'));
    }
}
