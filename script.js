(function () {
    'use strict';

    var menuToggle = document.getElementById('menu-toggle');
    var siteMenu = document.getElementById('site-menu');
    if (menuToggle && siteMenu) {
        menuToggle.addEventListener('click', function () {
            var isOpen = siteMenu.classList.toggle('open');
            menuToggle.setAttribute('aria-expanded', String(isOpen));
            menuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        });
        siteMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                siteMenu.classList.remove('open');
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.setAttribute('aria-label', 'Open navigation');
            });
        });
    }

    function startClock() {
        var dateElement = document.getElementById('current-date');
        if (dateElement) {
            var now = new Date();
            dateElement.textContent = now.toLocaleDateString() + ' | ' + now.toLocaleTimeString();
        }
    }
    startClock();
    window.setInterval(startClock, 1000);

    var contactForm = document.querySelector('.contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function () {
            var submit = contactForm.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.classList.add('is-loading');
            }
        });
    }

    var loadButton = document.getElementById('load-projects');
    var projectContainer = document.getElementById('json-projects');
    var jsonStatus = document.getElementById('json-status');
    if (loadButton && projectContainer) {
        function loadProjects() {
            loadButton.disabled = true;
            loadButton.classList.add('is-loading');
            if (jsonStatus) jsonStatus.textContent = 'Loading projects…';
            $.getJSON('api/projects.php')
                .done(function (data) {
                    if (!data.success || !Array.isArray(data.projects)) {
                        if (jsonStatus) jsonStatus.textContent = 'Projects could not be loaded.';
                        return;
                    }
                    projectContainer.innerHTML = '';
                    if (data.projects.length === 0) {
                        projectContainer.innerHTML = '<div class="empty-state"><strong>No projects yet</strong><span>New work will appear here soon.</span></div>';
                    } else {
                        data.projects.forEach(function (project) {
                            var card = document.createElement('div');
                            card.className = 'project-card';
                            var article = document.createElement('article');
                            if (project.image_path) {
                                var image = document.createElement('img');
                                image.src = project.image_path;
                                image.alt = project.title || 'Project image';
                                image.className = 'project-image';
                                article.appendChild(image);
                            }
                            var badge = document.createElement('span');
                            badge.className = 'badge';
                            badge.textContent = project.category || 'Project';
                            article.appendChild(badge);
                            var title = document.createElement('h3');
                            title.textContent = project.title || 'Untitled project';
                            article.appendChild(title);
                            var description = document.createElement('p');
                            description.textContent = project.description || '';
                            article.appendChild(description);
                            if (/^https?:\/\//i.test(project.github_url || '')) {
                                var link = document.createElement('a');
                                link.href = project.github_url;
                                link.target = '_blank';
                                link.rel = 'noopener noreferrer';
                                link.textContent = 'View on GitHub';
                                article.appendChild(link);
                            }
                            card.appendChild(article);
                            projectContainer.appendChild(card);
                        });
                    }
                    if (jsonStatus) jsonStatus.textContent = 'Projects refreshed successfully.';
                })
                .fail(function () {
                    if (jsonStatus) jsonStatus.innerHTML = 'Projects could not be loaded. <button class="link-button" type="button">Try again</button>';
                    var retry = jsonStatus && jsonStatus.querySelector('button');
                    if (retry) retry.addEventListener('click', loadProjects);
                })
                .always(function () {
                    loadButton.disabled = false;
                    loadButton.classList.remove('is-loading');
                });
        }
        loadButton.addEventListener('click', loadProjects);
    }
}());
