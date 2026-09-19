document.addEventListener('DOMContentLoaded', () => {
    const geoMaps = new WeakMap();
    let globalControlsBound = false;

    const normalizeFilterValue = (value) => {
        return (value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('ca');
    };

    const closeHiddenEditorRows = (table) => {
        table.querySelectorAll('.student-editor-row.open').forEach((row) => {
            const previous = row.previousElementSibling;
            if (!previous || previous.hidden) {
                row.classList.remove('open');
            }
        });
    };

    const updateStudentCount = (table) => {
        const countEl = document.getElementById('alumnes-count');
        if (!countEl) return;

        const visible = table.querySelectorAll('tbody tr[data-class]:not([hidden])').length;
        countEl.textContent = `${visible} alumnes`;
    };

    const getSectionRoot = (element) => {
        return element.closest('#panell, .admin-layout__content, section, article') || document;
    };

    const openCollapsibleAncestors = (element) => {
        let collapsible = element.parentElement?.closest('.admin-collapsible, .collapsible-card');
        while (collapsible) {
            collapsible.classList.remove('is-collapsed');
            const collapseButton = collapsible.querySelector(':scope > .admin-panel__header .collapse-toggle, :scope > .admin-team-class__header .collapse-toggle, .collapse-toggle');
            if (collapseButton) collapseButton.textContent = 'Amagar';
            collapsible = collapsible.parentElement?.closest('.admin-collapsible, .collapsible-card');
        }
    };

    const refreshGeoMaps = (root = document) => {
        root.querySelectorAll('[data-geo-map]').forEach((mapEl) => {
            const map = geoMaps.get(mapEl);
            if (!map) return;

            window.requestAnimationFrame(() => {
                map.invalidateSize();
            });
        });
    };

    const initGeoMaps = (root = document) => {
        if (!window.L) return;

        root.querySelectorAll('[data-geo-map]').forEach((mapEl) => {
            if (geoMaps.has(mapEl)) {
                refreshGeoMaps(root);
                return;
            }

            let points = [];
            try {
                points = JSON.parse(mapEl.getAttribute('data-geo-points') || '[]');
            } catch (error) {
                points = [];
            }

            const map = window.L.map(mapEl, {
                scrollWheelZoom: false,
            });
            geoMaps.set(mapEl, map);

            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(map);

            const bounds = [];
            points.forEach((point) => {
                const lat = Number(point.lat);
                const lng = Number(point.lng);
                const total = Number(point.total) || 0;
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                const marker = window.L.circleMarker([lat, lng], {
                    color: '#1d4ed8',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.55,
                    radius: Math.max(8, Math.min(22, 8 + Math.sqrt(total) * 2)),
                    weight: 2,
                }).addTo(map);

                const popup = document.createElement('div');
                const country = document.createElement('strong');
                const region = document.createElement('span');
                const visits = document.createElement('span');

                country.textContent = String(point.country_code || '');
                region.textContent = String(point.region || 'Desconegut');
                visits.textContent = `${total} visites`;
                popup.append(country, document.createElement('br'), region, document.createElement('br'), visits);

                marker.bindPopup(popup);
                bounds.push([lat, lng]);
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [32, 32], maxZoom: 4 });
            } else {
                map.setView([20, 0], 2);
            }
        });

        refreshGeoMaps(root);
    };

    const openCollapsibleForHash = (hash, root = document) => {
        if (!hash || hash === '#') return;

        const target = root.querySelector(hash);
        if (!target) return;

        const collapsibleCards = Array.from(root.querySelectorAll('.admin-collapsible, .collapsible-card'))
            .filter((card) => card.contains(target));

        collapsibleCards.forEach((collapsibleCard) => {
            collapsibleCard.classList.remove('is-collapsed');
            const collapseButton = collapsibleCard.querySelector('.collapse-toggle');
            if (collapseButton) collapseButton.textContent = 'Amagar';
        });

        window.setTimeout(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            refreshGeoMaps(root);
        }, 40);
    };

    const initConfirmForms = () => {
        if (document.body.dataset.confirmFormsBound === 'true') return;

        document.body.dataset.confirmFormsBound = 'true';
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm]');
            if (!form || event.defaultPrevented) return;

            const message = form.getAttribute('data-confirm') || 'Confirmes aquesta accio?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    };

    const initEditorToggles = (root = document) => {
        root.querySelectorAll('[data-target]').forEach((button) => {
            if (button.dataset.editorToggleBound === 'true') return;

            button.dataset.editorToggleBound = 'true';
            button.addEventListener('click', (event) => {
                event.preventDefault();

                const targetId = button.getAttribute('data-target');
                if (!targetId) return;

                let row = document.getElementById(targetId);
                if (!row) row = button.closest('tr')?.nextElementSibling;
                if (!row) return;

                const parentRow = button.closest('tr');
                if (parentRow?.hidden) return;

                openCollapsibleAncestors(row);

                const isOpen = row.classList.contains('open');
                getSectionRoot(button).querySelectorAll('.student-editor-row.open').forEach((openRow) => {
                    openRow.classList.remove('open');
                });
                if (!isOpen) {
                    row.classList.add('open');
                    window.setTimeout(() => row.scrollIntoView({ behavior: 'smooth', block: 'center' }), 40);
                }
            });
        });
    };

    const initCollapseToggles = (root = document) => {
        root.querySelectorAll('.collapse-toggle').forEach((collapseButton) => {
            const targetId = collapseButton.getAttribute('data-collapse');
            const collapsibleContent = targetId ? document.getElementById(targetId) : null;
            const collapsibleCard = collapseButton.closest('.admin-collapsible, .collapsible-card');
            if (!collapsibleCard || !collapsibleContent || collapseButton.dataset.bound === 'true' || collapseButton.dataset.adminCollapseBound === 'true') return;

            collapseButton.dataset.adminCollapseBound = 'true';
            const storageKey = targetId ? `admin-collapse:${targetId}` : null;

            if (storageKey) {
                try {
                    const storedState = window.localStorage.getItem(storageKey);
                    if (storedState === 'open') {
                        collapsibleCard.classList.remove('is-collapsed');
                    } else if (storedState === 'closed') {
                        collapsibleCard.classList.add('is-collapsed');
                    }
                } catch (error) {
                    // The visual control works even when localStorage is blocked.
                }
            }

            const syncButtonLabel = () => {
                collapseButton.textContent = collapsibleCard.classList.contains('is-collapsed') ? 'Mostrar' : 'Amagar';
            };

            syncButtonLabel();
            collapseButton.addEventListener('click', () => {
                const willOpen = collapsibleCard.classList.contains('is-collapsed');
                collapsibleCard.classList.toggle('is-collapsed');
                syncButtonLabel();

                if (storageKey) {
                    try {
                        window.localStorage.setItem(storageKey, willOpen ? 'open' : 'closed');
                    } catch (error) {
                        // The collapsed state is optional persistence.
                    }
                }

                if (willOpen) {
                    window.setTimeout(() => refreshGeoMaps(collapsibleCard), 420);
                }
            });
        });
    };

    const sortTable = (table, header) => {
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

        const rowSelector = body.querySelector('tr[data-user-row]') ? 'tr[data-user-row]' : 'tr[data-class]';
        const rows = Array.from(body.querySelectorAll(rowSelector));
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

    const initSortableTables = (root = document) => {
        root.querySelectorAll('[data-sortable-table]').forEach((table) => {
            const thead = table.querySelector('thead');
            if (!thead || thead.dataset.sortableBound === 'true') return;

            thead.dataset.sortableBound = 'true';
            thead.addEventListener('click', (event) => {
                const header = event.target.closest('th[data-sort-type]');
                if (!header || !thead.contains(header)) return;

                sortTable(table, header);
            });
        });
    };

    const applyGenericFilters = (bar, table) => {
        const yearSelect = bar.querySelector('[data-year-filter]');
        const yearChips = Array.from(bar.querySelectorAll('[data-year-filter-chip]'));
        const classChipList = bar.querySelector('[data-class-chip-list]');
        const classChips = Array.from(bar.querySelectorAll('[data-class-filter-chip]'));
        const selectedYear = yearSelect ? yearSelect.value : 'all';
        const showAllYears = selectedYear === '' || selectedYear === 'all';

        yearChips.forEach((chip) => {
            chip.classList.toggle('is-active', chip.getAttribute('data-value') === selectedYear);
        });

        if (classChipList && classChips.length > 0) {
            const showClassChips = !showAllYears;
            classChipList.hidden = !showClassChips;
            classChips.forEach((chip) => {
                const chipYear = chip.getAttribute('data-class-year') || '';
                const isAllChip = chip.getAttribute('data-value') === 'all';
                chip.hidden = !showClassChips || (!isAllChip && chipYear !== selectedYear);
                if (chip.hidden && !isAllChip) chip.classList.remove('is-active');
            });

            const hasActiveClass = classChips.some((chip) => !chip.hidden && chip.getAttribute('data-value') !== 'all' && chip.classList.contains('is-active'));
            const allClassChip = classChips.find((chip) => chip.getAttribute('data-value') === 'all');
            if (allClassChip) allClassChip.classList.toggle('is-active', !hasActiveClass);
        }

        const chips = classChips.length > 0
            ? classChips.filter((chip) => !chip.hidden)
            : Array.from(bar.querySelectorAll('.admin-filters__chip, .filter-chip'));
        const activeClasses = chips
            .filter((item) => item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active'))
            .map((item) => normalizeFilterValue(item.getAttribute('data-value')));
        const showAllClasses = activeClasses.length === 0;

        table.querySelectorAll('tbody tr[data-class]').forEach((row) => {
            const rowClass = normalizeFilterValue(row.getAttribute('data-class'));
            const rowYear = row.getAttribute('data-academic-year') || '';
            const isVisible = (showAllClasses || activeClasses.includes(rowClass)) && (showAllYears || rowYear === selectedYear);
            row.hidden = !isVisible;

            const editorRow = row.nextElementSibling;
            if (editorRow?.classList.contains('student-editor-row')) {
                editorRow.hidden = !isVisible;
                if (!isVisible) editorRow.classList.remove('open');
            }
        });

        closeHiddenEditorRows(table);
        updateStudentCount(table);
    };

    const initGenericFilters = (root = document) => {
        root.querySelectorAll('[data-filter-table]').forEach((bar) => {
            const table = document.getElementById(bar.getAttribute('data-filter-table') || '');
            if (!table) return;

            const classChips = Array.from(bar.querySelectorAll('[data-class-filter-chip]'));
            const resetClassChips = () => {
                classChips.forEach((chip) => {
                    chip.classList.toggle('is-active', chip.getAttribute('data-value') === 'all');
                });
            };

            if (bar.dataset.filterTableBound !== 'true') {
                bar.dataset.filterTableBound = 'true';
                bar.addEventListener('click', (event) => {
                    const chip = event.target.closest('.admin-filters__chip, .filter-chip');
                    if (!chip || !bar.contains(chip)) return;

                    if (chip.matches('[data-year-filter-chip]')) {
                        const yearSelect = bar.querySelector('[data-year-filter]');
                        if (yearSelect) yearSelect.value = chip.getAttribute('data-value') || 'all';
                        resetClassChips();
                        applyGenericFilters(bar, table);
                        return;
                    }

                    const availableChips = classChips.length > 0
                        ? classChips.filter((item) => !item.hidden)
                        : Array.from(bar.querySelectorAll('.admin-filters__chip, .filter-chip'));
                    const value = chip.getAttribute('data-value');

                    if (value === 'all') {
                        availableChips.forEach((item) => item.classList.toggle('is-active', item === chip));
                    } else {
                        chip.classList.toggle('is-active');
                        const hasActiveClass = availableChips.some((item) => item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active'));
                        const allChip = availableChips.find((item) => item.getAttribute('data-value') === 'all');
                        if (allChip) allChip.classList.toggle('is-active', !hasActiveClass);
                    }

                    applyGenericFilters(bar, table);
                });

                const yearSelect = bar.querySelector('[data-year-filter]');
                if (yearSelect) {
                    yearSelect.addEventListener('change', () => {
                        resetClassChips();
                        applyGenericFilters(bar, table);
                    });
                }
            }

            applyGenericFilters(bar, table);
        });
    };

    const applyUserFilters = (bar, table) => {
        const query = normalizeFilterValue(bar.querySelector('[data-user-search]')?.value || '');
        const status = bar.querySelector('[data-status-filter]')?.value || 'all';
        const activeClasses = Array.from(bar.querySelectorAll('.admin-filters__chip.is-active'))
            .map((chip) => chip.getAttribute('data-value') || '')
            .filter((value) => value !== 'all')
            .map(normalizeFilterValue);
        const showAllClasses = activeClasses.length === 0;
        const countTarget = document.getElementById(bar.getAttribute('data-count-target') || '');
        const countLabel = bar.getAttribute('data-count-label') || 'resultats';
        let visibleCount = 0;

        table.querySelectorAll('tbody tr[data-user-row]').forEach((row) => {
            const rowClass = normalizeFilterValue(row.getAttribute('data-class') || '');
            const rowStatus = row.getAttribute('data-status') || '';
            const rowSearch = normalizeFilterValue(row.getAttribute('data-search') || '');
            const isVisible = (showAllClasses || activeClasses.includes(rowClass))
                && (status === 'all' || status === rowStatus)
                && (query === '' || rowSearch.includes(query));
            const editorRow = row.nextElementSibling;

            row.hidden = !isVisible;
            if (editorRow?.classList.contains('student-editor-row')) {
                editorRow.hidden = !isVisible;
                if (!isVisible) editorRow.classList.remove('open');
            }
            if (isVisible) visibleCount++;
        });

        if (countTarget) countTarget.textContent = `${visibleCount} ${countLabel}`;
    };

    const initUserFilters = (root = document) => {
        root.querySelectorAll('[data-user-filter]').forEach((bar) => {
            const table = document.getElementById(bar.getAttribute('data-user-filter') || '');
            if (!table) return;

            if (bar.dataset.userFilterBound !== 'true') {
                bar.dataset.userFilterBound = 'true';
                bar.addEventListener('click', (event) => {
                    const chip = event.target.closest('.admin-filters__chip');
                    if (!chip || !bar.contains(chip)) return;

                    const chips = Array.from(bar.querySelectorAll('.admin-filters__chip'));
                    if (chip.getAttribute('data-value') === 'all') {
                        chips.forEach((item) => item.classList.toggle('is-active', item === chip));
                    } else {
                        chip.classList.toggle('is-active');
                        const hasActiveClass = chips.some((item) => item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active'));
                        chips.forEach((item) => {
                            if (item.getAttribute('data-value') === 'all') item.classList.toggle('is-active', !hasActiveClass);
                        });
                    }

                    applyUserFilters(bar, table);
                });

                bar.querySelector('[data-user-search]')?.addEventListener('input', () => applyUserFilters(bar, table));
                bar.querySelector('[data-status-filter]')?.addEventListener('change', () => applyUserFilters(bar, table));
            }

            applyUserFilters(bar, table);
        });
    };

    const initTeamFilters = (root = document) => {
        root.querySelectorAll('[data-team-filters]').forEach((filters) => {
            const yearSelect = filters.querySelector('[data-team-year-filter]');
            const projectSelect = filters.querySelector('[data-team-project-filter]');
            const projectOptions = Array.from(filters.querySelectorAll('[data-team-project-option]'));
            const countEl = filters.querySelector('[data-team-filter-count]');
            const rows = Array.from(getSectionRoot(filters).querySelectorAll('[data-team-row]'));
            if (!yearSelect || !projectSelect || rows.length === 0) return;

            const yearStorageKey = 'admin-team-year-filter';
            const projectStorageKey = 'admin-team-project-filter';

            const updateProjectOptions = () => {
                const selectedYear = yearSelect.value || 'all';
                const showAllYears = selectedYear === 'all' || selectedYear === '';
                let selectedProjectStillVisible = false;

                projectOptions.forEach((option) => {
                    const optionYear = option.getAttribute('data-team-year') || 'all';
                    const isVisible = optionYear === 'all' || showAllYears || optionYear === selectedYear;
                    option.hidden = !isVisible;
                    option.disabled = !isVisible;
                    if (isVisible && option.value === projectSelect.value) selectedProjectStillVisible = true;
                });

                if (!selectedProjectStillVisible) projectSelect.value = 'all';
            };

            const applyTeamFilters = () => {
                updateProjectOptions();

                const selectedYear = yearSelect.value || 'all';
                const selectedProject = projectSelect.value || 'all';
                const showAllYears = selectedYear === 'all' || selectedYear === '';
                const showAllProjects = selectedProject === 'all' || selectedProject === '';
                let visibleCount = 0;

                rows.forEach((row) => {
                    const isVisible = (showAllYears || row.getAttribute('data-team-year') === selectedYear)
                        && (showAllProjects || row.getAttribute('data-team-project') === selectedProject);
                    row.hidden = !isVisible;
                    if (isVisible) visibleCount++;
                });

                if (countEl) countEl.textContent = `${visibleCount} equips`;
            };

            if (filters.dataset.teamFiltersBound !== 'true') {
                filters.dataset.teamFiltersBound = 'true';

                try {
                    const storedYear = window.localStorage.getItem(yearStorageKey);
                    if (storedYear) yearSelect.value = storedYear;
                    updateProjectOptions();
                    const storedProject = window.localStorage.getItem(projectStorageKey);
                    if (storedProject) projectSelect.value = storedProject;
                } catch (error) {
                    updateProjectOptions();
                }

                yearSelect.addEventListener('change', () => {
                    try {
                        window.localStorage.setItem(yearStorageKey, yearSelect.value);
                        window.localStorage.setItem(projectStorageKey, 'all');
                    } catch (error) {
                        // Filter persistence is optional.
                    }
                    projectSelect.value = 'all';
                    applyTeamFilters();
                });

                projectSelect.addEventListener('change', () => {
                    try {
                        window.localStorage.setItem(projectStorageKey, projectSelect.value);
                    } catch (error) {
                        // Filter persistence is optional.
                    }
                    applyTeamFilters();
                });
            }

            applyTeamFilters();
        });
    };

    const initRoleFilters = (root = document) => {
        root.querySelectorAll('[data-role-filter]').forEach((select) => {
            const scope = getSectionRoot(select);
            const projectSelect = scope.querySelector('[data-project-role-filter]') || document.querySelector('[data-project-role-filter]');
            const groups = Array.from(scope.querySelectorAll('[data-role-group]'));
            if (groups.length === 0) return;

            const storageKey = 'admin-role-filter';
            const projectStorageKey = 'admin-project-role-filter';
            const normalize = (value) => normalizeFilterValue(value).replace(/\s+/g, ' ');

            const applyFilter = () => {
                const normalizedRoleValue = normalize(select.value);
                const selectedProject = projectSelect ? projectSelect.value : 'all';
                const showAllRoles = normalizedRoleValue === '' || normalizedRoleValue === 'all';
                const showAllProjects = selectedProject === '' || selectedProject === 'all';

                groups.forEach((group) => {
                    const roleMatches = showAllRoles || normalize(group.getAttribute('data-role-name')) === normalizedRoleValue;
                    let visibleRows = 0;

                    group.querySelectorAll('[data-role-member-row]').forEach((row) => {
                        const projectMatches = showAllProjects || row.getAttribute('data-project-key') === selectedProject;
                        const isVisible = roleMatches && projectMatches;
                        row.hidden = !isVisible;
                        if (isVisible) visibleRows++;
                    });

                    group.hidden = !roleMatches || visibleRows === 0;
                });
            };

            if (select.dataset.roleFilterBound !== 'true') {
                select.dataset.roleFilterBound = 'true';

                try {
                    const storedValue = window.localStorage.getItem(storageKey);
                    if (storedValue) select.value = storedValue;
                    const storedProjectValue = window.localStorage.getItem(projectStorageKey);
                    if (projectSelect && storedProjectValue) projectSelect.value = storedProjectValue;
                } catch (error) {
                    // Filter persistence is optional.
                }

                select.addEventListener('change', () => {
                    try {
                        window.localStorage.setItem(storageKey, select.value);
                    } catch (error) {
                        // Filter persistence is optional.
                    }
                    applyFilter();
                });

                if (projectSelect && projectSelect.dataset.projectRoleFilterBound !== 'true') {
                    projectSelect.dataset.projectRoleFilterBound = 'true';
                    projectSelect.addEventListener('change', () => {
                        try {
                            window.localStorage.setItem(projectStorageKey, projectSelect.value);
                        } catch (error) {
                            // Filter persistence is optional.
                        }
                        applyFilter();
                    });
                }
            }

            applyFilter();
        });
    };

    const initEvidenceEntryForms = (root = document) => {
        root.querySelectorAll('[data-evidence-entry-form]').forEach((form) => {
            if (form.dataset.evidenceEntryBound === 'true') return;

            const editionSelect = form.querySelector('[data-evidence-project-year]');
            const objectiveSelect = form.querySelector('[data-evidence-objective]');
            const categorySelect = form.querySelector('[data-evidence-category]');
            const typeSelect = form.querySelector('[data-evidence-type]');
            if (!editionSelect || !objectiveSelect || !categorySelect || !typeSelect) return;

            const filterOptions = (source, target, matches, emptyLabel) => {
                const sourceValue = source.value;
                let visibleCount = 0;

                Array.from(target.options).forEach((option, index) => {
                    if (index === 0) return;
                    const isVisible = sourceValue !== '' && matches(option, sourceValue);
                    option.hidden = !isVisible;
                    option.disabled = !isVisible;
                    if (isVisible) visibleCount++;
                });

                const selectedOption = target.options[target.selectedIndex];
                if (!selectedOption || selectedOption.disabled) target.value = '';
                target.disabled = sourceValue === '' || visibleCount === 0;
                target.options[0].textContent = sourceValue === ''
                    ? emptyLabel
                    : (visibleCount > 0 ? 'Selecciona una opció' : 'No hi ha opcions disponibles');
            };

            const syncObjectives = () => filterOptions(
                editionSelect,
                objectiveSelect,
                (option, editionId) => (option.getAttribute('data-editions') || '').split(',').includes(editionId),
                'Selecciona primer una edició'
            );
            const syncTypes = () => filterOptions(
                categorySelect,
                typeSelect,
                (option, categoryId) => option.getAttribute('data-category-id') === categoryId,
                'Selecciona primer una categoria'
            );

            form.dataset.evidenceEntryBound = 'true';
            editionSelect.addEventListener('change', syncObjectives);
            categorySelect.addEventListener('change', syncTypes);
            syncObjectives();
            syncTypes();
        });
    };

    const initLightbox = () => {
        if (document.body.dataset.avatarLightboxBound === 'true') return;

        const lightbox = document.getElementById('avatar-lightbox');
        const lightboxImage = document.getElementById('avatar-lightbox-img');
        const lightboxCaption = document.getElementById('avatar-lightbox-caption');
        const lightboxClose = document.getElementById('avatar-lightbox-close');
        const lightboxOverlay = document.getElementById('avatar-lightbox-overlay');
        if (!lightbox || !lightboxImage) return;

        document.body.dataset.avatarLightboxBound = 'true';
        const closeLightbox = () => {
            lightbox.hidden = true;
            lightboxImage.src = '';
            if (lightboxCaption) lightboxCaption.textContent = '';
            document.body.style.overflow = '';
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('.user-avatar-trigger');
            if (!trigger) return;

            event.preventDefault();
            const src = trigger.getAttribute('data-avatar-src') || '';
            if (src === '') return;

            lightboxImage.src = src;
            if (lightboxCaption) lightboxCaption.textContent = trigger.getAttribute('data-avatar-name') || '';
            lightbox.hidden = false;
            document.body.style.overflow = 'hidden';
        });

        lightboxClose?.addEventListener('click', closeLightbox);
        lightboxOverlay?.addEventListener('click', closeLightbox);
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !lightbox.hidden) closeLightbox();
        });
    };

    const initNavGroups = () => {
        document.querySelectorAll('[data-nav-group-toggle]').forEach((toggle) => {
            if (toggle.dataset.navGroupBound === 'true') return;

            const targetId = toggle.getAttribute('data-nav-group-toggle');
            const submenu = targetId ? document.getElementById(targetId) : null;
            const group = toggle.closest('[data-nav-group]');
            if (!submenu || !group) return;

            toggle.dataset.navGroupBound = 'true';
            const submenuLinks = Array.from(submenu.querySelectorAll('a[href*="#"]'));
            const submenuHasHash = () => submenuLinks.some((link) => new URL(link.href, window.location.href).hash === window.location.hash);
            const setOpen = (isOpen) => {
                group.classList.toggle('is-open', isOpen);
                toggle.setAttribute('aria-expanded', String(isOpen));
                submenu.hidden = false;
                submenu.style.maxHeight = isOpen ? `${submenu.scrollHeight}px` : '0px';
            };

            submenu.hidden = false;
            setOpen(submenuHasHash());

            toggle.addEventListener('click', () => {
                setOpen(!group.classList.contains('is-open'));
            });

            submenuLinks.forEach((link) => {
                link.addEventListener('click', () => {
                    setOpen(true);
                    openCollapsibleForHash(new URL(link.href, window.location.href).hash);
                });
            });

            window.addEventListener('hashchange', () => {
                if (submenuHasHash()) setOpen(true);
            });
        });
    };

    const initBackToTop = () => {
        const backToTop = document.querySelector('.admin-back-to-top');
        if (!backToTop || backToTop.dataset.backToTopBound === 'true') return;

        backToTop.dataset.backToTopBound = 'true';
        const updateBackToTop = () => {
            backToTop.classList.toggle('is-visible', window.scrollY > 520);
        };

        updateBackToTop();
        window.addEventListener('scroll', updateBackToTop, { passive: true });
        backToTop.addEventListener('click', (event) => {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    };

    const initGlobalControls = () => {
        if (globalControlsBound) return;
        globalControlsBound = true;

        initConfirmForms();
        initLightbox();
        initNavGroups();
        initBackToTop();
        window.addEventListener('hashchange', () => openCollapsibleForHash(window.location.hash));
    };

    const initAdminControls = (root = document) => {
        initGlobalControls();
        initCollapseToggles(root);
        initEditorToggles(root);
        initSortableTables(root);
        initGenericFilters(root);
        initUserFilters(root);
        initTeamFilters(root);
        initRoleFilters(root);
        initEvidenceEntryForms(root);
        initGeoMaps(root);
        openCollapsibleForHash(window.location.hash, root);
    };

    initAdminControls(document);

    window.addEventListener('admin:section-loaded', (event) => {
        initAdminControls(event.detail?.panel || document);
    });
});
