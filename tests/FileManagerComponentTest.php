<?php

namespace Proside\FileManager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Proside\FileManager\Livewire\FileManager;

class FileManagerComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fm-test');
    }

    public function test_component_renders(): void
    {
        Livewire::test(FileManager::class)
            ->assertOk()
            ->assertSee(__('file-manager::file-manager.empty'));
    }

    public function test_create_folder_and_list(): void
    {
        Livewire::test(FileManager::class)
            ->call('createFolder', 'Fotos', 'conteudos')
            ->assertSet('path', 'conteudos')
            ->assertSee('Fotos');
    }

    public function test_navigation_and_breadcrumbs(): void
    {
        $component = Livewire::test(FileManager::class);
        $component->call('createFolder', 'Sub', 'conteudos')
            ->call('open', 'conteudos/Sub')
            ->assertSet('path', 'conteudos/Sub')
            ->assertSee('Sub');
    }

    public function test_renders_multiple_roots_in_sidebar(): void
    {
        config(['file-manager.root_resolver' => fn () => ['conteudos/alpha', 'conteudos/beta']]);

        Livewire::test(FileManager::class)
            ->assertSet('path', 'conteudos/alpha') // abre na primeira raiz
            ->assertSee('alpha')
            ->assertSee('beta');
    }

    public function test_picker_dispatches_selection(): void
    {
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');

        Livewire::test(FileManager::class, ['pickerMode' => true, 'multiple' => false])
            ->call('choose', ['conteudos/a.png'])
            ->assertDispatched('file-manager-selected', paths: ['conteudos/a.png']);
    }

    public function test_upload_stores_file(): void
    {
        Livewire::test(FileManager::class)
            ->set('uploads', [UploadedFile::fake()->image('photo.png')])
            ->assertSee('photo.png');

        $this->assertTrue(Storage::disk('fm-test')->exists('conteudos/photo.png'));
    }

    public function test_list_view_renders(): void
    {
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');

        Livewire::test(FileManager::class)
            ->call('setView', 'list')
            ->assertSet('viewMode', 'list')
            ->assertSee('a.png');
    }

    public function test_delete_moves_to_trash(): void
    {
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');

        Livewire::test(FileManager::class)
            ->call('delete', ['conteudos/a.png']);

        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/a.png'));
        $this->assertTrue(Storage::disk('fm-test')->exists('apagados/a.png'));
    }
}
