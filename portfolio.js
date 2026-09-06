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
            const hashId = window.location.hash.slice(1);
            const hashSection = (hashId === 'experience' || hashId === 'contact')
                ? sections.find((entry) => entry.id === hashId) || null
                : null;
            const sharedClosingSection = hashSection === null
                ? null
                : sections.find((entry) => entry.id === (hashId === 'experience' ? 'contact' : 'experience')) || null;

            if (hashSection !== null && sharedClosingSection !== null) {
                const hashBounds = hashSection.element.getBoundingClientRect();
                const siblingBounds = sharedClosingSection.element.getBoundingClientRect();
                if (Math.abs(hashBounds.top - siblingBounds.top) < 2 && hashBounds.bottom > headerHeight && siblingBounds.bottom > headerHeight) {
                    setActive(hashId);
                    return;
                }
            }

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

    const initializeProjectNavigator = () => {
        document.querySelectorAll('[data-project-navigator]').forEach((navigator) => {
            if (!(navigator instanceof HTMLElement)) {
                return;
            }

            const list = navigator.querySelector('.portfolio-project-grid');
            const previous = navigator.querySelector('[data-project-direction="previous"]');
            const next = navigator.querySelector('[data-project-direction="next"]');
            const cards = list instanceof HTMLElement
                ? Array.from(list.querySelectorAll(':scope > .portfolio-project-card'))
                : [];

            if (!(list instanceof HTMLElement)
                || !(previous instanceof HTMLButtonElement)
                || !(next instanceof HTMLButtonElement)
                || cards.length < 2) {
                return;
            }

            let start = 0;
            const visibleCount = () => {
                if (window.matchMedia('(max-width: 620px)').matches) {
                    return 1;
                }
                return window.matchMedia('(max-width: 1100px)').matches ? 2 : 3;
            };
            const renderWindow = () => {
                const count = visibleCount();
                const maximumStart = Math.max(0, cards.length - count);
                start = Math.min(start, maximumStart);

                if (cards.length <= count) {
                    cards.forEach((card) => {
                        card.hidden = false;
                    });
                    navigator.classList.remove('portfolio-project-navigator--active');
                    list.style.removeProperty('--portfolio-project-visible-count');
                    previous.hidden = true;
                    next.hidden = true;
                    return;
                }

                const end = start + count;
                cards.forEach((card, index) => {
                    card.hidden = index < start || index >= end;
                });
                list.style.setProperty('--portfolio-project-visible-count', String(count));
                navigator.classList.add('portfolio-project-navigator--active');
                previous.hidden = start === 0;
                next.hidden = start === maximumStart;
            };

            navigator.addEventListener('click', (event) => {
                const button = event.target instanceof Element ? event.target.closest('[data-project-direction]') : null;
                if (!(button instanceof HTMLButtonElement) || !navigator.contains(button)) {
                    return;
                }

                const maximumStart = Math.max(0, cards.length - visibleCount());
                const nextStart = button.dataset.projectDirection === 'next' ? start + 1 : start - 1;
                if (nextStart < 0 || nextStart > maximumStart) {
                    return;
                }
                start = nextStart;
                renderWindow();
            });
            renderWindow();
            window.addEventListener('resize', renderWindow);
        });
    };

    const initializeExperiencePagination = () => {
        document.querySelectorAll('.portfolio-experience-list[data-experience-page-size]').forEach((list) => {
            if (!(list instanceof HTMLOListElement)) {
                return;
            }

            const pageSize = Number.parseInt(list.dataset.experiencePageSize || '', 10);
            const pagination = list.parentElement?.querySelector('[data-experience-pagination]');
            const range = pagination?.querySelector('.portfolio-experience-range');
            const newer = pagination?.querySelector('[data-experience-page="newer"]');
            const older = pagination?.querySelector('[data-experience-page="older"]');
            const items = Array.from(list.querySelectorAll(':scope > .portfolio-experience-item'));

            if (!Number.isInteger(pageSize) || pageSize < 1
                || !(pagination instanceof HTMLElement)
                || !(range instanceof HTMLElement)
                || !(newer instanceof HTMLButtonElement)
                || !(older instanceof HTMLButtonElement)
                || items.length <= pageSize) {
                return;
            }

            let page = 0;
            const pageCount = Math.ceil(items.length / pageSize);
            const renderPage = () => {
                const start = page * pageSize;
                const end = Math.min(start + pageSize, items.length);
                items.forEach((item, index) => {
                    const visible = index >= start && index < end;
                    item.hidden = !visible;
                    item.classList.toggle('portfolio-experience-item--visible-last', visible && index === end - 1);
                });
                range.textContent = start + 1 === end
                    ? `Showing experience ${end} of ${items.length}`
                    : `Showing experiences ${start + 1}–${end} of ${items.length}`;
                newer.disabled = page === 0;
                older.disabled = page === pageCount - 1;
            };

            renderPage();
            pagination.addEventListener('click', (event) => {
                const button = event.target instanceof Element ? event.target.closest('[data-experience-page]') : null;
                if (!(button instanceof HTMLButtonElement) || !pagination.contains(button) || button.disabled) {
                    return;
                }

                const nextPage = button.dataset.experiencePage === 'older' ? page + 1 : page - 1;
                if (nextPage < 0 || nextPage >= pageCount) {
                    return;
                }
                page = nextPage;
                renderPage();
            });
            pagination.hidden = false;
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
        initializeProjectNavigator();
        initializeExperiencePagination();
        initializePortfolioMobileNavigation();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePortfolio, { once: true });
    } else {
        initializePortfolio();
    }
})();
