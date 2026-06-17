<?php

namespace Proside\FileManager\Console;

use Illuminate\Console\Command;
use Proside\FileManager\Support\FileManagerService;

class PruneTrashCommand extends Command
{
    protected $signature = 'file-manager:prune-trash';

    protected $description = 'Elimina definitivamente os itens do lixo cujo prazo de retenção expirou.';

    public function handle(FileManagerService $service): int
    {
        $removed = $service->pruneTrash();

        $this->info("File Manager: {$removed} item(s) removido(s) do lixo.");

        return self::SUCCESS;
    }
}
