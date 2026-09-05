(() => {
    'use strict';

    const initializePortfolioScrollspy = () => {
        const header = document.querySelector('.portfolio-header');
        const links = Array.from(document.querySelectorAll('[data-portfolio-section]'));
        const sections = Array.from(new Set(links.map((link) => link.dataset.portfolioSection)))
            .map((id) => ({ id, element: document.getElementById(id) }))
            .filter((entry) => entry.element instanceof HTMLElement);

        if (links.length === 0 || sections.length === 0) {
            return;
        }

        const setActive = (id) => {
            links.forEach((link) => {
                const isActive = link.dataset.portfolioSection === id;
                link.classList.toggle('is-current', isActive);
                if (isActive) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };

        const selectDominantSection = () => {
            const headerHeight = header instanceof HTMLElement ? header.offsetHeight : 0;
            const focusLine = headerHeight + ((window.innerHeight - headerHeight) * 0.28);
            let selected = sections[0];
            let closestDistance = Number.POSITIVE_INFINITY;

            sections.forEach((entry) => {
                const bounds = entry.element.getBoundingClientRect();
                if (bounds.bottom <= headerHeight || bounds.top >= window.innerHeight) {
                    return;
                }

                const nearestPoint = Math.min(Math.max(focusLine, bounds.top), bounds.bottom);
                const distance = Math.abs(nearestPoint - focusLine);
                if (distance < closestDistance) {
                    selected = entry;
                    closestDistance = distance;
                }
            });

            setActive(selected.id);
        };

        links.forEach((link) => {
            link.addEventListener('click', () => setActive(link.dataset.portfolioSection || 'top'));
        });

        const observer = new IntersectionObserver(() => selectDominantSection(), {
            rootMargin: '-8% 0px -42% 0px',
            threshold: [0, 0.12, 0.5],
        });
        sections.forEach((entry) => observer.observe(entry.element));

        const initialId = window.location.hash.slice(1);
        if (links.some((link) => link.dataset.portfolioSection === initialId)) {
            setActive(initialId);
        } else {
            selectDominantSection();
        }

        window.addEventListener('hashchange', () => {
            const id = window.location.hash.slice(1);
            if (links.some((link) => link.dataset.portfolioSection === id)) {
                setActive(id);
            }
        });
    };

    const initializePortfolioSkills = () => {
        document.querySelectorAll('.portfolio-skills-list').forEach((list) => {
            if (!(list instanceof HTMLElement)) {
                return;
            }

            list.addEventListener('click', (event) => {
                const button = event.target instanceof Element ? event.target.closest('.portfolio-skill-control') : null;
                if (!(button instanceof HTMLButtonElement) || !list.contains(button)) {
                    return;
                }

                const wasSelected = button.getAttribute('aria-pressed') === 'true';
                list.querySelectorAll('.portfolio-skill-control[aria-pressed="true"]').forEach((selected) => {
                    selected.setAttribute('aria-pressed', 'false');
                });
                if (!wasSelected) {
                    button.setAttribute('aria-pressed', 'true');
                }
            });
        });
    };

    const initializePortfolioMobileNavigation = () => {
        const toggle = document.querySelector('.portfolio-menu-toggle');
        const layer = document.querySelector('.portfolio-mobile-nav-layer');
        const close = document.querySelector('.portfolio-mobile-nav-close');
        const backdrop = document.querySelector('.portfolio-mobile-nav-backdrop');
        const navigation = document.querySelector('.portfolio-mobile-nav-links');

        if (!(toggle instanceof HTMLButtonElement)
            || !(layer instanceof HTMLElement)
            || !(close instanceof HTMLButtonElement)
            || !(backdrop instanceof HTMLButtonElement)
            || !(navigation instanceof HTMLElement)) {
            return;
        }

        const isOpen = () => layer.classList.contains('is-open');
        const closeNavigation = (restoreFocus = false) => {
            layer.classList.remove('is-open');
            layer.setAttribute('aria-hidden', 'true');
            layer.setAttribute('inert', '');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('portfolio-mobile-nav-open');
            if (restoreFocus) {
                toggle.focus();
            }
        };
        const openNavigation = () => {
            layer.classList.add('is-open');
            layer.setAttribute('aria-hidden', 'false');
            layer.removeAttribute('inert');
            toggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('portfolio-mobile-nav-open');
            close.focus();
        };

        toggle.addEventListener('click', () => {
            if (isOpen()) {
                closeNavigation(true);
            } else {
                openNavigation();
            }
        });
        close.addEventListener('click', () => closeNavigation(true));
        backdrop.addEventListener('click', () => closeNavigation(true));
        navigation.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[data-portfolio-section]') : null;
            if (link instanceof HTMLAnchorElement && navigation.contains(link)) {
                closeNavigation();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (!isOpen()) {
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                closeNavigation(true);
                return;
            }
            if (event.key !== 'Tab') {
                return;
            }
            const focusable = Array.from(layer.querySelectorAll('a[href], button:not([disabled])')).filter((element) => element instanceof HTMLElement && element.tabIndex >= 0);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!(first instanceof HTMLElement) || !(last instanceof HTMLElement)) {
                return;
            }
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
        const narrowLayout = window.matchMedia('(max-width: 820px)');
        narrowLayout.addEventListener('change', (event) => {
            if (!event.matches && isOpen()) {
                closeNavigation();
            }
        });
    };

    const initializePortfolio = () => {
        initializePortfolioScrollspy();
        initializePortfolioSkills();
        initializePortfolioMobileNavigation();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePortfolio, { once: true });
    } else {
        initializePortfolio();
    }
})();
