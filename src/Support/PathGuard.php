<?php

namespace Proside\FileManager\Support;

use InvalidArgumentException;

/**
 * Normaliza e valida caminhos relativos, impedindo path traversal
 * e garantindo que tudo fica confinado às pastas raiz/lixo configuradas.
 */
class PathGuard
{
    public function __construct(
        protected string $root,
        protected string $trash,
    ) {
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
            return $this->root;
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

    /** Verifica se o caminho está dentro da raiz ou do lixo. */
    public function withinRoots(string $path): bool
    {
        return $path === $this->root
            || $path === $this->trash
            || str_starts_with($path, $this->root.'/')
            || str_starts_with($path, $this->trash.'/');
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

    public function root(): string
    {
        return $this->root;
    }

    public function trash(): string
    {
        return $this->trash;
    }
}
