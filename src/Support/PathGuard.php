<?php

namespace Proside\FileManager\Support;

use InvalidArgumentException;

/**
 * Normaliza e valida caminhos relativos, impedindo path traversal
 * e garantindo que tudo fica confinado às pastas raiz/lixo do utilizador.
 *
 * O lixo espelha a árvore de origem: "conteudos/a/x.png" é apagado para
 * "apagados/conteudos/a/x.png". Cada raiz tem por isso o seu próprio ramo
 * no lixo, e o confinamento é uma simples verificação de prefixo.
 */
class PathGuard
{
    /** @var array<int,string> Raízes permitidas (a primeira é a principal). */
    protected array $roots;

    /** @var array<int,string> Ramos do lixo correspondentes às raízes. */
    protected array $trashRoots;

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

        $this->trash = trim(str_replace('\\', '/', $this->trash), '/');
        $this->roots = $roots ?: [''];
        $this->trashRoots = array_map(fn ($r) => $this->trash.'/'.$r, $this->roots);
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
            throw new InvalidArgumentException('Caminho fora das pastas permitidas.');
        }

        return $normalized;
    }

    /** Verifica se o caminho está dentro de alguma raiz permitida ou do lixo dessa raiz. */
    public function withinRoots(string $path): bool
    {
        foreach ([...$this->roots, ...$this->trashRoots] as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed.'/')) {
                return true;
            }
        }

        return false;
    }

    public function isTrash(string $path): bool
    {
        return $path === $this->trash || str_starts_with($path, $this->trash.'/');
    }

    /** Caminho no lixo correspondente a um caminho de origem. */
    public function toTrash(string $path): string
    {
        return $this->trash.'/'.$path;
    }

    /** Caminho de origem correspondente a um caminho no lixo. */
    public function fromTrash(string $path): string
    {
        return ltrim(substr($path, strlen($this->trash)), '/');
    }

    /** Sanitiza um nome de ficheiro/pasta (sem separadores nem chars proibidos). */
    public function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('#[\x00-\x1F\x7F]#u', '', $name);
        $name = preg_replace('#[/\\\\?%*:|"<>]#', '_', $name);
        $name = rtrim($name, ". \t");

        if ($name === '' || preg_match('#^\.+$#', $name)) {
            return 'sem_nome';
        }

        return mb_substr($name, 0, 200);
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

    /** Ramo principal do lixo (o do utilizador). */
    public function trashRoot(): string
    {
        return $this->trashRoots[0];
    }

    /** @return array<int,string> Todos os ramos do lixo permitidos. */
    public function trashRoots(): array
    {
        return $this->trashRoots;
    }

    public function trash(): string
    {
        return $this->trash;
    }
}
