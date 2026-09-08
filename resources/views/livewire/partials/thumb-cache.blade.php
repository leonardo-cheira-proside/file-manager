{{-- Estado de carregamento das miniaturas, partilhado pelo gestor e pelo picker.

     Resolve dois problemas:

     1. O <img> da grelha é renderizado pelo servidor. Se vier da cache do
        browser, o evento "load" dispara ANTES de o Alpine ligar o x-on:load,
        e o spinner ficava para sempre — visível sobretudo ao recarregar a
        página. O init verifica img.complete/video.readyState para apanhar
        esse caso.

     2. Miniaturas já carregadas ficam registadas (sessionStorage), para que
        voltar a uma pasta não volte a piscar o loader.

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
                        if ((el.tagName === 'IMG' && el.complete && el.naturalWidth > 0)
                            || (el.tagName === 'VIDEO' && el.readyState >= 1)) {
                            self.markLoaded();
                        }
                    });
                },
                init: function () { this.syncThumb(); },
            };
        };
    }
</script>
