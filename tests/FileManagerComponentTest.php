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
            ->call('storeUploads', 'conteudos')
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

    public function test_upload_route_stores_and_returns_path(): void
    {
        $res = $this->actingAs(new \Illuminate\Foundation\Auth\User())
            ->post(route('file-manager.upload'), [
                'file' => UploadedFile::fake()->image('drop.png'),
            ]);

        $res->assertOk();
        $this->assertSame('conteudos/drop.png', $res->json('path'));
        $this->assertTrue(Storage::disk('fm-test')->exists('conteudos/drop.png'));
    }

    public function test_media_route_is_public_and_serves_by_clean_path(): void
    {
        Storage::disk('fm-test')->put('conteudos/sub/pic.png', 'x');

        // Sem autenticação: ver conteúdo é público.
        $res = $this->get(url('file-manager/media/conteudos/sub/pic.png'));

        $res->assertOk();
    }

    public function test_download_route_returns_attachment(): void
    {
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');

        $res = $this->actingAs(new \Illuminate\Foundation\Auth\User())
            ->get(url('file-manager/download/conteudos/a.png'));

        $res->assertOk();
        $res->assertHeader('content-disposition');
    }

    public function test_zip_route_returns_zip(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ext-zip not available');
        }
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');
        Storage::disk('fm-test')->put('conteudos/b.png', 'y');

        $res = $this->actingAs(new \Illuminate\Foundation\Auth\User())
            ->post(url('file-manager/download-zip'), ['paths' => ['conteudos/a.png', 'conteudos/b.png']]);

        $res->assertOk();
    }

    public function test_delete_moves_to_trash(): void
    {
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');

        Livewire::test(FileManager::class)
            ->call('delete', ['conteudos/a.png']);

        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/a.png'));
        $this->assertTrue(Storage::disk('fm-test')->exists('apagados/conteudos/a.png'));
    }

    public function test_trash_view_renders_with_items(): void
    {
        Storage::disk('fm-test')->put('conteudos/a.png', 'x');

        $c = Livewire::test(FileManager::class)->call('delete', ['conteudos/a.png']);
        $c->call('open', 'apagados/conteudos')
            ->assertOk()
            ->assertSet('inTrash', true)
            ->assertSee('a.png');
    }

    public function test_upload_places_file_in_current_folder(): void
    {
        Livewire::test(FileManager::class)
            ->call('createFolder', 'Destino', 'conteudos')
            ->call('open', 'conteudos/Destino')
            ->set('uploads', [UploadedFile::fake()->image('novo.png')])
            ->call('storeUploads', 'conteudos/Destino')
            ->assertOk();

        $this->assertTrue(Storage::disk('fm-test')->exists('conteudos/Destino/novo.png'));
    }

    public function test_listing_exposes_the_media_url_for_copying(): void
    {
        Storage::disk('fm-test')->put('conteudos/foto.png', 'x');

        $files = Livewire::test(FileManager::class)->instance()->allFiles();

        $this->assertCount(1, $files);
        $this->assertStringContainsString('/file-manager/media/conteudos/foto.png', $files[0]['url']);
    }

    public function test_folders_have_no_url_to_copy(): void
    {
        Livewire::test(FileManager::class)->call('createFolder', 'Pasta', 'conteudos');

        $files = Livewire::test(FileManager::class)->instance()->allFiles();

        $this->assertSame('folder', $files[0]['type']);
        $this->assertNull($files[0]['url']);
    }

    public function test_upload_lands_in_the_folder_it_started_in(): void
    {
        $c = Livewire::test(FileManager::class)
            ->call('createFolder', 'Origem', 'conteudos')
            ->call('createFolder', 'Outra', 'conteudos')
            ->call('open', 'conteudos/Origem')
            ->set('uploads', [UploadedFile::fake()->image('foto.png')]);

        // O utilizador troca de pasta antes de o upload ser guardado.
        $c->call('open', 'conteudos/Outra')
            ->call('storeUploads', 'conteudos/Origem');

        $this->assertTrue(Storage::disk('fm-test')->exists('conteudos/Origem/foto.png'));
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/Outra/foto.png'));
    }

    public function test_upload_event_carries_the_originating_folder(): void
    {
        Livewire::test(FileManager::class)
            ->call('createFolder', 'Origem', 'conteudos')
            ->call('open', 'conteudos/Origem')
            ->set('uploads', [UploadedFile::fake()->image('foto.png')])
            ->call('open', 'conteudos')
            ->call('storeUploads', 'conteudos/Origem')
            ->assertDispatched('file-manager-uploaded', path: 'conteudos/Origem', count: 1);
    }

    public function test_upload_to_a_forbidden_folder_falls_back_to_root(): void
    {
        Storage::disk('fm-test')->put('conteudos/vitima/x.txt', 'x');
        config(['file-manager.root_resolver' => fn () => 'conteudos/atacante']);

        Livewire::test(FileManager::class)
            ->set('uploads', [UploadedFile::fake()->image('foto.png')])
            ->call('storeUploads', 'conteudos/vitima');

        $this->assertTrue(Storage::disk('fm-test')->exists('conteudos/atacante/foto.png'));
        $this->assertFalse(Storage::disk('fm-test')->exists('conteudos/vitima/foto.png'));
    }

    public function test_upload_into_trash_is_refused(): void
    {
        $c = Livewire::test(FileManager::class)
            ->set('uploads', [UploadedFile::fake()->image('foto.png')])
            ->call('storeUploads', 'apagados/conteudos');

        $this->assertFalse(Storage::disk('fm-test')->exists('apagados/conteudos/foto.png'));
    }

    public function test_upload_event_reports_how_many_files(): void
    {
        Livewire::test(FileManager::class)
            ->set('uploads', [
                UploadedFile::fake()->image('a.png'),
                UploadedFile::fake()->image('b.png'),
            ])
            ->call('storeUploads', 'conteudos')
            ->assertDispatched('file-manager-uploaded', count: 2);
    }
}
