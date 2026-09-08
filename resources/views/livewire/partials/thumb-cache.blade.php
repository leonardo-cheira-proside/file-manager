{{-- Estado de carregamento das miniaturas, partilhado pelo gestor e pelo picker.

     Resolve dois problemas:

     1. O <img> da grelha é renderizado pelo servidor. Se vier da cache do
        browser, o evento "load" dispara ANTES de o Alpine ligar o x-on:load,
        e o spinner ficava para sempre — visível sobretudo ao recarregar a
        página. O init verifica img.complete/video.readyState para apanhar
        esse caso.

     2. Miniaturas já carregadas ficam registadas (sessionStorage), para que
        voltar a uma pasta não volte a piscar o loader.

     3. Os vídeos só pedem metadados quando entram no ecrã, e no máximo dois
        de cada vez. Uma grelha cheia de <video preload="metadata"> abre
        dezenas de ligações com Range em paralelo; num servidor de
        desenvolvimento single-threaded (php artisan serve) isso satura o
        único worker e os pedidos começam a devolver 503. Há ainda um
        tempo-limite: um vídeo que nunca responda esconde o loader na mesma,
        em vez de o deixar a girar para sempre.

     Definido de forma idempotente porque o picker carrega o componente com
     "lazy" — este script pode chegar por qualquer um dos dois lados. --}}
<script>
    if (!window.fmLoadedThumbs) {
        window.fmLoadedThumbs = (function () {
            var KEY = 'fmThumbs', MAX = 500, set, timer;

            try {
                set = new Set(JSON.parse(sessionStorage.getItem(KEY) || '[]'));
            } catch (e) {
                set = new Set();
            }

            function persist() {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    try {
                        sessionStorage.setItem(KEY, JSON.stringify(Array.from(set).slice(-MAX)));
                    } catch (e) {}
                }, 500);
            }

            return {
                has: function (u) { return !!u && set.has(u); },
                add: function (u) { if (u && !set.has(u)) { set.add(u); persist(); } },
            };
        })();
    }

    if (!window.fmVideoQueue) {
        window.fmVideoQueue = (function () {
            var MAX = 2, active = 0, queue = [];

            function pump() {
                while (active < MAX && queue.length) {
                    active++;
                    (queue.shift())();
                }
            }

            return {
                add: function (start) { queue.push(start); pump(); },
                release: function () { if (active > 0) active--; pump(); },
            };
        })();
    }

    if (!window.fmThumb) {
        window.fmThumb = function (url) {
            return {
                l: false,
                fmUrl: function () { return typeof url === 'function' ? url() : url; },
                markLoaded: function () {
                    this.l = true;
                    window.fmLoadedThumbs.add(this.fmUrl());
                },
                syncThumb: function () {
                    if (window.fmLoadedThumbs.has(this.fmUrl())) {
                        this.l = true;
                        return;
                    }

                    this.l = false;

                    var self = this;
                    this.$nextTick(function () {
                        var el = self.$el.querySelector('img, video');
                        if (!el) return;
                        // Um erro anterior ao Alpine também escapava ao
                        // x-on:error e deixava o loader preso para sempre.
                        if (el.error) {
                            self.l = true;
                            return;
                        }
                        if (el.tagName === 'VIDEO') {
                            if (el.readyState >= 1) {
                                self.markLoaded();
                            } else {
                                self.watchVideo(el);
                            }

                            return;
                        }
                        if (el.complete && el.naturalWidth > 0) {
                            self.markLoaded();
                        }
                    });
                },
                watchVideo: function (v) {
                    var self = this, released = false, graceTimer;

                    function release() {
                        if (released) return;
                        released = true;
                        window.fmVideoQueue.release();
                    }
                    function finish() {
                        clearTimeout(graceTimer);
                        self.markLoaded();
                        release();
                    }
                    function giveUp() {
                        clearTimeout(graceTimer);
                        self.l = true;
                        release();
                    }

                    v.addEventListener('loadeddata', finish, { once: true });
                    v.addEventListener('seeked', finish, { once: true });
                    v.addEventListener('error', giveUp, { once: true });
                    v.addEventListener('loadedmetadata', function () {
                        // Metadados chegaram mas o frame pode não vir; não
                        // deixa o loader eterno à espera dele.
                        graceTimer = setTimeout(finish, 3000);
                    }, { once: true });

                    function start() {
                        if (v.readyState >= 1) { finish(); return; }
                        try {
                            v.preload = 'metadata';
                            v.load();
                        } catch (e) {
                            giveUp();

                            return;
                        }
                        setTimeout(function () { if (!self.l) giveUp(); }, 20000);
                    }

                    if (typeof IntersectionObserver === 'function') {
                        var io = new IntersectionObserver(function (entries) {
                            for (var i = 0; i < entries.length; i++) {
                                if (entries[i].isIntersecting) {
                                    io.disconnect();
                                    window.fmVideoQueue.add(start);

                                    return;
                                }
                            }
                        }, { rootMargin: '300px' });
                        io.observe(v);
                    } else {
                        window.fmVideoQueue.add(start);
                    }
                },
                init: function () { this.syncThumb(); },
            };
        };
    }
</script>
