document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('panell');
    const sectionLinks = Array.from(document.querySelectorAll('[data-dashboard-section]'));

    if (!panel || sectionLinks.length === 0) return;

    const getSectionFromUrl = () => {
        const section = new URL(window.location.href).searchParams.get('section');
        return section || '';
    };

    const setActiveLink = (section) => {
        sectionLinks.forEach((link) => {
            const isActive = link.getAttribute('data-dashboard-section') === section;
            link.classList.toggle('active', isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    const scrollToHash = (hash) => {
        if (!hash) return;

        const target = document.getElementById(hash.slice(1));
        if (!target) return;

        window.requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    };

    const initializeLoadedSection = () => {
        panel.querySelectorAll('.collapse-toggle').forEach((collapseButton) => {
            const targetId = collapseButton.getAttribute('data-collapse');
            const content = targetId ? document.getElementById(targetId) : null;
            const card = collapseButton.closest('.admin-collapsible, .collapsible-card');
            if (!card || !content || collapseButton.dataset.bound === 'true') return;

            collapseButton.dataset.bound = 'true';
            const storageKey = `admin-collapse:${targetId}`;
            try {
                const storedState = window.localStorage.getItem(storageKey);
                if (storedState === 'open') card.classList.remove('is-collapsed');
                if (storedState === 'closed') card.classList.add('is-collapsed');
            } catch (error) {
                // El col·lapse continua funcionant si localStorage no està disponible.
            }

            const syncLabel = () => {
                collapseButton.textContent = card.classList.contains('is-collapsed') ? 'Mostrar' : 'Amagar';
            };

            syncLabel();
            collapseButton.addEventListener('click', () => {
                const willOpen = card.classList.contains('is-collapsed');
                card.classList.toggle('is-collapsed');
                syncLabel();
                try {
                    window.localStorage.setItem(storageKey, willOpen ? 'open' : 'closed');
                } catch (error) {
                    // El canvi visual no depèn de la persistència local.
                }
            });
        });
    };

    const expandLoadedSection = () => {
        const loadedSection = panel.querySelector(':scope > section.admin-collapsible, :scope > article.collapsible-card');
        if (!loadedSection) return;

        loadedSection.classList.remove('is-collapsed');
        const collapseButton = loadedSection.querySelector(':scope > .admin-panel__header .collapse-toggle');
        if (collapseButton) collapseButton.textContent = 'Amagar';
    };

    const applyUserFilters = () => {
        panel.querySelectorAll('[data-user-filter]').forEach((filterBar) => {
            const table = document.getElementById(filterBar.getAttribute('data-user-filter') || '');
            if (!table) return;

            const query = (filterBar.querySelector('[data-user-search]')?.value || '').trim().toLocaleLowerCase('ca');
            const status = filterBar.querySelector('[data-status-filter]')?.value || 'all';
            const activeClasses = Array.from(filterBar.querySelectorAll('.admin-filters__chip.is-active'))
                .map((chip) => chip.getAttribute('data-value') || '')
                .filter((value) => value !== 'all')
                .map((value) => value.trim().toLocaleLowerCase('ca'));
            const countTarget = document.getElementById(filterBar.getAttribute('data-count-target') || '');
            const countLabel = filterBar.getAttribute('data-count-label') || 'resultats';
            let visibleCount = 0;

            table.querySelectorAll('tbody tr[data-user-row]').forEach((row) => {
                const rowClass = (row.getAttribute('data-class') || '').trim().toLocaleLowerCase('ca');
                const rowStatus = row.getAttribute('data-status') || '';
                const rowSearch = (row.getAttribute('data-search') || '').trim().toLocaleLowerCase('ca');
                const classMatches = activeClasses.length === 0 || activeClasses.includes(rowClass);
                const statusMatches = status === 'all' || status === rowStatus;
                const searchMatches = query === '' || rowSearch.includes(query);
                const visible = classMatches && statusMatches && searchMatches;
                const editorRow = row.nextElementSibling;

                row.hidden = !visible;
                if (editorRow?.classList.contains('student-editor-row')) {
                    editorRow.hidden = !visible;
                    if (!visible) editorRow.classList.remove('open');
                }
                if (visible) visibleCount++;
            });

            if (countTarget) countTarget.textContent = `${visibleCount} ${countLabel}`;
        });
    };

    const sortUserTable = (table, header) => {
        const index = Array.from(header.parentNode.children).indexOf(header);
        const direction = header.classList.contains('sort-asc') ? 'desc' : 'asc';
        const body = table.querySelector('tbody');
        if (!body) return;

        table.querySelectorAll('th[data-sort-type]').forEach((item) => {
            item.classList.remove('sort-asc', 'sort-desc');
            item.removeAttribute('aria-sort');
        });
        header.classList.add(`sort-${direction}`);
        header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');

        const rows = Array.from(body.querySelectorAll('tr[data-user-row]'));
        const editors = new Map(rows.map((row) => [row, row.nextElementSibling]));
        const sortType = header.getAttribute('data-sort-type') || 'text';

        rows.sort((first, second) => {
            const firstValue = (first.children[index]?.getAttribute('data-sort-value') || first.children[index]?.textContent || '').trim();
            const secondValue = (second.children[index]?.getAttribute('data-sort-value') || second.children[index]?.textContent || '').trim();
            if (sortType === 'number') {
                const result = (Number.parseFloat(firstValue.replace(',', '.')) || 0) - (Number.parseFloat(secondValue.replace(',', '.')) || 0);
                return direction === 'asc' ? result : -result;
            }
            return direction === 'asc'
                ? firstValue.localeCompare(secondValue, 'ca', { numeric: true, sensitivity: 'base' })
                : secondValue.localeCompare(firstValue, 'ca', { numeric: true, sensitivity: 'base' });
        });

        const fragment = document.createDocumentFragment();
        rows.forEach((row) => {
            fragment.appendChild(row);
            const editor = editors.get(row);
            if (editor?.classList.contains('student-editor-row')) fragment.appendChild(editor);
        });
        body.appendChild(fragment);
    };

    const initializeUserSectionControls = () => {
        if (!panel.querySelector('#usuaris')) return;

        if (panel.dataset.userControlsBound !== 'true') {
            panel.dataset.userControlsBound = 'true';
            panel.addEventListener('click', (event) => {
                const chip = event.target.closest('[data-user-filter] .admin-filters__chip');
                if (chip) {
                    const filterBar = chip.closest('[data-user-filter]');
                    const chips = Array.from(filterBar.querySelectorAll('.admin-filters__chip'));
                    if (chip.getAttribute('data-value') === 'all') {
                        chips.forEach((item) => item.classList.toggle('is-active', item === chip));
                    } else {
                        chip.classList.toggle('is-active');
                        const hasActive = chips.some((item) => item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active'));
                        chips.filter((item) => item.getAttribute('data-value') === 'all').forEach((item) => item.classList.toggle('is-active', !hasActive));
                    }
                    applyUserFilters();
                    return;
                }

                const header = event.target.closest('th[data-sort-type]');
                if (header) {
                    const table = header.closest('table[data-sortable-table]');
                    if (table) sortUserTable(table, header);
                    return;
                }

                const avatarTrigger = event.target.closest('.user-avatar-trigger');
                const lightbox = document.getElementById('avatar-lightbox');
                const lightboxImage = document.getElementById('avatar-lightbox-img');
                const lightboxCaption = document.getElementById('avatar-lightbox-caption');
                if (avatarTrigger && lightbox && lightboxImage) {
                    lightboxImage.src = avatarTrigger.getAttribute('data-avatar-src') || '';
                    if (lightboxCaption) lightboxCaption.textContent = avatarTrigger.getAttribute('data-avatar-name') || '';
                    lightbox.hidden = false;
                    document.body.style.overflow = 'hidden';
                }
            });

            panel.addEventListener('input', (event) => {
                if (event.target.matches('[data-user-search]')) applyUserFilters();
            });
            panel.addEventListener('change', (event) => {
                if (event.target.matches('[data-status-filter]')) applyUserFilters();
            });
            panel.addEventListener('submit', (event) => {
                return;
                const form = event.target.closest('form[data-confirm]');
                if (form && !window.confirm(form.getAttribute('data-confirm') || 'Confirmes aquesta acció?')) {
                    event.preventDefault();
                }
            });
        }

        panel.querySelectorAll('#usuaris [data-target]').forEach((editButton) => {
            if (editButton.dataset.userEditorBound === 'true') return;

            editButton.dataset.userEditorBound = 'true';
            editButton.addEventListener('click', (event) => {
                event.preventDefault();
                const target = document.getElementById(editButton.getAttribute('data-target') || '');
                if (target) target.classList.toggle('open');
            });
        });

        applyUserFilters();
    };

    const loadSection = async (section, hash = '', updateHistory = true) => {
        const requestUrl = new URL(window.location.href);
        requestUrl.searchParams.set('section', section);
        requestUrl.hash = hash;
        panel.setAttribute('aria-busy', 'true');
        panel.classList.add('is-loading');

        try {
            const response = await fetch(requestUrl.toString(), {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'fetch',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`La secció no s'ha pogut carregar (${response.status}).`);
            }

            const html = await response.text();
            panel.innerHTML = html;
            initializeLoadedSection();
            expandLoadedSection();
            setActiveLink(section);

            if (updateHistory) {
                window.history.pushState({ section }, '', requestUrl.toString());
            }

            scrollToHash(hash);
            window.dispatchEvent(new CustomEvent('admin:section-loaded', {
                detail: { section, panel },
            }));
        } catch (error) {
            panel.innerHTML = '<div class="flash-message error">No s\'ha pogut carregar aquesta secció. Torna-ho a provar.</div>';
            console.error(error);
        } finally {
            panel.removeAttribute('aria-busy');
            panel.classList.remove('is-loading');
        }
    };

    sectionLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const section = link.getAttribute('data-dashboard-section');
            if (!section) return;

            event.preventDefault();
            const targetUrl = new URL(link.getAttribute('href') || window.location.href, window.location.href);
            loadSection(section, targetUrl.hash);
        });
    });

    window.addEventListener('popstate', () => {
        const section = getSectionFromUrl();
        if (section) {
            loadSection(section, window.location.hash, false);
            return;
        }

        window.location.reload();
    });

    setActiveLink(getSectionFromUrl() || 'resum');
});
