(() => {
    'use strict';

    const initializePortfolioScrollspy = () => {
        const header = document.querySelector('.portfolio-header');
        const links = Array.from(document.querySelectorAll('.portfolio-nav-links a[data-portfolio-section]'));
        const sections = links
            .map((link) => ({ id: link.dataset.portfolioSection, link, element: document.getElementById(link.dataset.portfolioSection) }))
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
        const list = document.querySelector('.portfolio-skills-list');
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
    };

    const initializePortfolio = () => {
        initializePortfolioScrollspy();
        initializePortfolioSkills();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePortfolio, { once: true });
    } else {
        initializePortfolio();
    }
})();
