<?php

namespace Proside\FileManager\Support;

use InvalidArgumentException;

/**
 * Normaliza e valida caminhos relativos, impedindo path traversal
 * e garantindo que tudo fica confinado às pastas raiz/lixo configuradas.
 */
class PathGuard
{
    /** @var array<int,string> Raízes permitidas (a primeira é a principal). */
    protected array $roots;

    /**
     * @param  array<int,string>|string  $roots  Uma ou várias raízes permitidas.
     */
    public function __construct(
        array|string $roots,
        protected string $trash,
    ) {
        $roots = array_values(array_unique(array_filter(
            array_map(fn ($r) => trim(str_replace('\\', '/', (string) $r), '/'), (array) $roots),
            fn ($r) => $r !== '',
        )));

        $this->roots = $roots ?: [''];
    }

    /**
     * Normaliza um caminho relativo ao disco (remove ./, ../, barras duplas,
     * barras à esquerda/direita) e valida que pertence a uma raiz permitida.
     */
    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');

        if ($path === '') {
            return $this->roots[0];
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new InvalidArgumentException('Caminho inválido (path traversal).');
            }
            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        if (! $this->withinRoots($normalized)) {
            throw new InvalidArgumentException("Caminho fora das pastas permitidas: {$normalized}");
        }

        return $normalized;
    }

    /** Verifica se o caminho está dentro de alguma raiz permitida ou do lixo. */
    public function withinRoots(string $path): bool
    {
        if ($this->isTrash($path)) {
            return true;
        }

        foreach ($this->roots as $root) {
            if ($path === $root || str_starts_with($path, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    public function isTrash(string $path): bool
    {
        return $path === $this->trash || str_starts_with($path, $this->trash.'/');
    }

    /** Sanitiza um nome de ficheiro/pasta (sem separadores nem chars proibidos). */
    public function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('#[/\\\\?%*:|"<>]#', '_', $name);

        return trim($name) ?: 'sem_nome';
    }

    /** Raiz principal (a primeira). */
    public function root(): string
    {
        return $this->roots[0];
    }

    /** @return array<int,string> Todas as raízes permitidas. */
    public function roots(): array
    {
        return $this->roots;
    }

    public function trash(): string
    {
        return $this->trash;
    }
}
