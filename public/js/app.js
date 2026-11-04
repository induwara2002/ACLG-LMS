document.addEventListener('DOMContentLoaded', function () {
    const librarySplash = document.querySelector('[data-library-splash]');
    if (librarySplash) {
        let splashAlreadyShown = false;
        try {
            splashAlreadyShown = window.sessionStorage.getItem('librarySplashShown') === 'true';
            if (!splashAlreadyShown) {
                window.sessionStorage.setItem('librarySplashShown', 'true');
            }
        } catch (error) {
            console.warn('The library splash could not save its session state.', error);
        }

        if (!splashAlreadyShown) {
            const splashProgress = librarySplash.querySelector('[data-splash-progress]');
            const splashPercent = librarySplash.querySelector('[data-splash-percent]');
            const progressFill = splashProgress.querySelector('span');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const duration = reducedMotion ? 900 : 3400;
            const fadeDuration = reducedMotion ? 0 : 420;
            let startedAt = null;

            librarySplash.classList.add('is-visible');
            librarySplash.setAttribute('aria-hidden', 'false');
            document.body.classList.add('library-splash-active');

            const finishSplash = function () {
                librarySplash.classList.add('is-exiting');
                librarySplash.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('library-splash-active');
                window.setTimeout(function () {
                    window.location.replace(librarySplash.dataset.loginUrl);
                }, fadeDuration);
            };
            const updateProgress = function (timestamp) {
                if (startedAt === null) {
                    startedAt = timestamp;
                }

                const progress = Math.min((timestamp - startedAt) / duration, 1);
                const percentage = Math.floor(progress * 100);
                progressFill.style.width = percentage + '%';
                splashPercent.textContent = percentage + '%';
                splashProgress.setAttribute('aria-valuenow', String(percentage));

                if (progress < 1) {
                    window.requestAnimationFrame(updateProgress);
                } else {
                    finishSplash();
                }
            };

            window.requestAnimationFrame(updateProgress);
        }
    }

    const pageLoader = document.querySelector('[data-page-loader]');
    if (pageLoader && !window.__libraryPageLoaderInitialized) {
        window.__libraryPageLoaderInitialized = true;

        const hidePageLoader = function () {
            pageLoader.classList.remove('is-visible');
            pageLoader.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('page-loading');
        };
        const showPageLoader = function () {
            if (pageLoader.classList.contains('is-visible')) return;

            pageLoader.classList.add('is-visible');
            pageLoader.setAttribute('aria-hidden', 'false');
            document.body.classList.add('page-loading');
        };

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const link = event.target.closest('a[href]');
            if (!link
                || link.hasAttribute('download')
                || link.getAttribute('rel')?.split(/\s+/).includes('external')
                || (link.target && link.target.toLowerCase() !== '_self')) return;

            const destination = new URL(link.href, window.location.href);
            if (destination.origin !== window.location.origin || !['http:', 'https:'].includes(destination.protocol)) return;

            const current = new URL(window.location.href);
            const sameDocument = destination.pathname === current.pathname
                && destination.search === current.search
                && destination.hash !== '';
            const downloadablePath = /\.(?:7z|aiff?|avi|csv|docx?|eot|gif|gz|jpe?g|m4a|mov|mp3|mp4|pdf|png|pptx?|rar|svg|tar|ttf|txt|wav|webm|webp|xlsx?|zip)$/i.test(destination.pathname);

            if (sameDocument || downloadablePath || destination.href === current.href) return;
            showPageLoader();
        });

        window.addEventListener('pagehide', function (event) {
            if (event.persisted) hidePageLoader();
        });
        window.addEventListener('pageshow', hidePageLoader);
        hidePageLoader();
    }

    document.querySelectorAll('[data-sri-lanka-clock]').forEach(function (clock) {
        const serverTime = Date.parse(clock.dateTime);
        if (!Number.isFinite(serverTime)) {
            console.warn('Sri Lankan clock was not initialized because its server timestamp is invalid.');
            return;
        }

        const startedAt = performance.now();
        const formatter = new Intl.DateTimeFormat('en-LK', {
            timeZone: 'Asia/Colombo',
            dateStyle: 'medium',
            timeStyle: 'medium',
            hour12: false
        });
        const updateClock = function () {
            const currentTime = new Date(serverTime + performance.now() - startedAt);
            clock.dateTime = currentTime.toISOString();
            clock.textContent = formatter.format(currentTime);
        };

        updateClock();
        window.setInterval(updateClock, 1000);
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const adminSidebarBackdrop = document.querySelector('[data-admin-sidebar-backdrop]');

    if (sidebarToggle && sidebar && adminSidebarBackdrop) {
        const setAdminSidebar = function (isOpen) {
            document.body.classList.toggle('admin-sidebar-open', isOpen);
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
            sidebarToggle.setAttribute('aria-label', isOpen ? sidebarToggle.dataset.closeLabel : sidebarToggle.dataset.openLabel);
        };

        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarToggle.addEventListener('click', function () {
            setAdminSidebar(!document.body.classList.contains('admin-sidebar-open'));
        });
        adminSidebarBackdrop.addEventListener('click', function () {
            setAdminSidebar(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setAdminSidebar(false);
            }
        });
    }

    const psSidebarToggle = document.querySelector('[data-ps-sidebar-toggle]');
    const psSidebarBackdrop = document.querySelector('[data-ps-sidebar-backdrop]');

    if (psSidebarToggle && psSidebarBackdrop) {
        const closeSidebar = function () {
            document.body.classList.remove('ps-sidebar-open');
            psSidebarToggle.setAttribute('aria-expanded', 'false');
            psSidebarToggle.setAttribute('aria-label', psSidebarToggle.dataset.openLabel);
        };
        const toggleSidebar = function () {
            const isOpen = document.body.classList.toggle('ps-sidebar-open');
            psSidebarToggle.setAttribute('aria-expanded', String(isOpen));
            psSidebarToggle.setAttribute('aria-label', isOpen ? psSidebarToggle.dataset.closeLabel : psSidebarToggle.dataset.openLabel);
        };

        psSidebarToggle.addEventListener('click', toggleSidebar);
        psSidebarBackdrop.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });
    }

    const librarianToggle = document.querySelector('[data-librarian-toggle]');
    const librarianBackdrop = document.querySelector('[data-librarian-backdrop]');

    if (librarianToggle && librarianBackdrop) {
        const setLibrarianSidebar = function (isOpen) {
            document.body.classList.toggle('librarian-sidebar-open', isOpen);
            librarianToggle.setAttribute('aria-expanded', String(isOpen));
            librarianToggle.setAttribute('aria-label', isOpen ? librarianToggle.dataset.closeLabel : librarianToggle.dataset.openLabel);
        };

        librarianToggle.addEventListener('click', function () {
            setLibrarianSidebar(!document.body.classList.contains('librarian-sidebar-open'));
        });
        librarianBackdrop.addEventListener('click', function () {
            setLibrarianSidebar(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setLibrarianSidebar(false);
            }
        });
    }
});
