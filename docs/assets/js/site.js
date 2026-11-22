/* MylibSQLAdmin docs — progressive enhancement only. */

(function () {
    'use strict';

    /* ---------------------------------------------------------------- theme */
    var root = document.documentElement;

    function syncThemeButton() {
        var button = document.querySelector('[data-theme-toggle]');
        if (!button) return;
        var isDark = root.getAttribute('data-theme') === 'dark';
        button.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
    }

    function syncFavicon() {
        var isDark = root.getAttribute('data-theme') === 'dark';
        var icon = document.getElementById('site-favicon');
        var shortcut = document.querySelector('link[rel="shortcut icon"]');
        var base = root.getAttribute('data-baseurl') || '';
        if (icon) icon.href = isDark ? base + '/favicon-dark-32.png' : base + '/favicon-light-32.png';
        if (shortcut) shortcut.href = isDark ? base + '/favicon-dark.ico' : base + '/favicon-light.ico';
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('docs-theme', next); } catch (e) { /* private mode */ }
            syncThemeButton();
            syncFavicon();
        });
    });

    syncThemeButton();
    syncFavicon();

    /* -------------------------------------------------------------- sidebar */
    var sidebar = document.querySelector('[data-sidebar]');
    var sidebarToggle = document.querySelector('[data-sidebar-toggle]');

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ------------------------------------------------------------------ toc */
    var toc = document.querySelector('[data-toc]');

    if (toc) {
        var list = toc.querySelector('ul');
        var headings = Array.prototype.slice.call(
            document.querySelectorAll('.prose h2[id], .prose h3[id]')
        );

        if (headings.length) {
            headings.forEach(function (heading) {
                var li = document.createElement('li');
                li.className = heading.tagName === 'H3' ? 'toc-h3' : 'toc-h2';

                var link = document.createElement('a');
                link.href = '#' + heading.id;
                link.textContent = heading.textContent.replace(/^#\s*/, '');

                li.appendChild(link);
                list.appendChild(li);
            });

            var links = Array.prototype.slice.call(list.querySelectorAll('a'));

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        var id = entry.target.id;
                        links.forEach(function (link) {
                            link.classList.toggle('is-active', link.getAttribute('href') === '#' + id);
                        });
                    });
                }, { rootMargin: '-80px 0px -70% 0px', threshold: 0 });

                headings.forEach(function (heading) { observer.observe(heading); });
            }
        } else {
            toc.remove();
        }
    }

    /* -------------------------------------------------------------- tables */
    // Permission matrices are wide by nature. Wrap them so the page itself
    // never scrolls sideways on a phone — only the table does.
    document.querySelectorAll('.prose table').forEach(function (table) {
        if (table.parentElement && table.parentElement.classList.contains('table-scroll')) return;
        var wrap = document.createElement('div');
        wrap.className = 'table-scroll';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });

    /* ----------------------------------------------------------- copy code */
    document.querySelectorAll('.prose pre').forEach(function (pre) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'copy-button';
        button.textContent = 'Copy';

        button.addEventListener('click', function () {
            var code = pre.querySelector('code');
            var text = code ? code.innerText : pre.innerText;

            function done() {
                button.textContent = 'Copied';
                window.setTimeout(function () { button.textContent = 'Copy'; }, 1600);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () { /* noop */ });
            } else {
                var area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
                document.body.removeChild(area);
            }
        });

        pre.appendChild(button);
    });
})();
