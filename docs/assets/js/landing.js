/* Turso Client PHP landing page — progressive enhancement only. */
(function () {
    "use strict";

    var REPO = "tursodatabase/turso-client-php";

    /* ----------------------------------------------------------- theme */
    // Same storage key and attribute as the user manual, so a toggle here
    // carries over when a reader opens the guide (and back again).
    var root = document.documentElement;
    var toggle = document.getElementById("theme-toggle");

    function applyTheme(theme) {
        root.setAttribute("data-theme", theme);
        if (toggle) {
            toggle.setAttribute(
                "aria-label",
                theme === "dark" ? "Switch to light theme" : "Switch to dark theme"
            );
        }
        var icon = document.getElementById("site-favicon");
        var shortcut = document.querySelector('link[rel="shortcut icon"]');
        var base = root.getAttribute("data-baseurl") || "";
        if (icon) icon.href = base + "/favicon-32.png";
        if (shortcut) shortcut.href = base + "/favicon.ico";
    }

    if (toggle) {
        toggle.addEventListener("click", function () {
            var next = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
            applyTheme(next);
            try {
                localStorage.setItem("docs-theme", next);
            } catch (e) {
                /* storage can be disabled; the toggle still works for this page */
            }
        });
    }

    // The inline head script already set the attribute before paint; this
    // first pass only aligns the icon and the button label with that choice.
    applyTheme(root.getAttribute("data-theme") || "dark");

    /* ------------------------------------------------------ mobile nav */
    var navToggle = document.getElementById("nav-toggle");
    var panel = document.getElementById("mobile-panel");

    function closePanel() {
        if (!panel || !navToggle) return;
        panel.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
    }

    if (navToggle && panel) {
        navToggle.addEventListener("click", function () {
            var open = panel.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", String(open));
        });
        panel.addEventListener("click", function (e) {
            if (e.target.closest("a")) closePanel();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closePanel();
        });
    }

    /* -------------------------------------------------- install command */
    var copyBtn = document.getElementById("copy-install");
    var command = document.getElementById("install-command");

    if (copyBtn && command) {
        copyBtn.addEventListener("click", async function () {
            try {
                await navigator.clipboard.writeText(command.textContent.trim());
                copyBtn.classList.add("is-copied");
                copyBtn.setAttribute("aria-label", "Copied");
                setTimeout(function () {
                    copyBtn.classList.remove("is-copied");
                    copyBtn.setAttribute("aria-label", "Copy install command");
                }, 2000);
            } catch (e) {
                copyBtn.setAttribute("aria-label", "Copy failed — select the command");
            }
        });
    }

    /* ---------------------------------------------------- GitHub stats */
    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    async function loadStats() {
        try {
            var headers = { Accept: "application/vnd.github+json" };
            var repoRes = await fetch("https://api.github.com/repos/" + REPO, {
                headers: headers,
            });
            if (!repoRes.ok) return;
            var repo = await repoRes.json();

            setText("stars", (repo.stargazers_count || 0).toLocaleString("en-US"));
            setText("header-stars", String(repo.stargazers_count || 0));

            var results = await Promise.allSettled([
                fetch("https://api.github.com/repos/" + REPO + "/contributors", {
                    headers: headers,
                }),
                fetch("https://api.github.com/repos/" + REPO + "/releases/latest", {
                    headers: headers,
                }),
            ]);

            if (results[0].status === "fulfilled" && results[0].value.ok) {
                var people = await results[0].value.json();
                if (Array.isArray(people)) setText("contributors", String(people.length));
            }
            if (results[1].status === "fulfilled" && results[1].value.ok) {
                var release = await results[1].value.json();
                if (release && release.tag_name) setText("release-tag", release.tag_name);
            }

        } catch (e) {
            /* The static fallback copy stays on the page. */
        }
    }

    var year = document.getElementById("year");
    if (year) year.textContent = String(new Date().getFullYear());

    loadStats();
})();
