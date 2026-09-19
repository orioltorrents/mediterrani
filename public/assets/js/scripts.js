document.addEventListener('DOMContentLoaded', () => {
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
        countEl.textContent = visible + ' alumnes';
    };

    const normalizeFilterValue = (value) => {
        return (value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('ca');
    };

    const geoMapEl = document.querySelector('[data-geo-map]');
    let geoMap = null;

    const initGeoMap = () => {
        if (!geoMapEl || geoMap || !window.L) return;

        let points = [];
        try {
            points = JSON.parse(geoMapEl.getAttribute('data-geo-points') || '[]');
        } catch (error) {
            points = [];
        }

        geoMap = window.L.map(geoMapEl, {
            scrollWheelZoom: false,
        });

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(geoMap);

        const bounds = [];
        points.forEach((point) => {
            const lat = Number(point.lat);
            const lng = Number(point.lng);
            const total = Number(point.total) || 0;
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

            const radius = Math.max(8, Math.min(22, 8 + Math.sqrt(total) * 2));
            const marker = window.L.circleMarker([lat, lng], {
                color: '#1d4ed8',
                fillColor: '#3b82f6',
                fillOpacity: 0.55,
                radius,
                weight: 2,
            }).addTo(geoMap);

            const popup = document.createElement('div');
            const country = document.createElement('strong');
            const region = document.createElement('span');
            const visits = document.createElement('span');
            const countryCode = String(point.country_code || '');
            const regionName = String(point.region || 'Desconegut');

            country.textContent = countryCode;
            region.textContent = regionName;
            visits.textContent = `${total} visites`;
            popup.append(country, document.createElement('br'), region, document.createElement('br'), visits);

            marker.bindPopup(popup);
            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) {
            geoMap.fitBounds(bounds, { padding: [32, 32], maxZoom: 4 });
        } else {
            geoMap.setView([20, 0], 2);
        }
    };

    const refreshGeoMap = () => {
        if (!geoMapEl) return;
        initGeoMap();
        if (!geoMap) return;

        window.requestAnimationFrame(() => {
            geoMap.invalidateSize();
        });
    };

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Confirmes aquesta acció?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-target]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const targetId = button.getAttribute('data-target');
            if (!targetId) return;
            let row = document.getElementById(targetId);
            if (!row) row = button.closest('tr')?.nextElementSibling;
            if (!row) return;
            const parentRow = button.closest('tr');
            if (parentRow?.hidden) return;

            let collapsible = row.parentElement?.closest('.admin-collapsible, .collapsible-card');
            while (collapsible) {
                collapsible.classList.remove('is-collapsed');
                const collapseButton = collapsible.querySelector(':scope > .admin-panel__header .collapse-toggle, :scope > .admin-team-class__header .collapse-toggle');
                if (collapseButton) collapseButton.textContent = 'Amagar';
                collapsible = collapsible.parentElement?.closest('.admin-collapsible, .collapsible-card');
            }

            const isOpen = row.classList.contains('open');
            document.querySelectorAll('.student-editor-row.open').forEach((r) => r.classList.remove('open'));
            if (!isOpen) row.classList.add('open');
            if (!isOpen) {
                window.setTimeout(() => row.scrollIntoView({ behavior: 'smooth', block: 'center' }), 40);
            }
        });
    });

    const openCollapsibleForHash = (hash) => {
        if (!hash || hash === '#') return;

        const target = document.getElementById(hash.slice(1));
        if (!target) return;

        const collapsibleCards = Array.from(document.querySelectorAll('.admin-collapsible, .collapsible-card'))
            .filter((card) => card.contains(target));

        collapsibleCards.forEach((collapsibleCard) => {
            collapsibleCard.classList.remove('is-collapsed');
            const collapseBtn = collapsibleCard.querySelector('.collapse-toggle');
            if (collapseBtn) collapseBtn.textContent = 'Amagar';
        });

        window.setTimeout(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 40);
    };

    document.querySelectorAll('[data-nav-group-toggle]').forEach((toggle) => {
        const targetId = toggle.getAttribute('data-nav-group-toggle');
        const submenu = targetId ? document.getElementById(targetId) : null;
        const group = toggle.closest('[data-nav-group]');
        if (!submenu || !group) return;

        const submenuLinks = Array.from(submenu.querySelectorAll('a[href^="#"]'));
        const submenuHasHash = () => submenuLinks.some((link) => link.getAttribute('href') === window.location.hash);
        const setOpen = (isOpen) => {
            group.classList.toggle('is-open', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
            submenu.hidden = false;
            submenu.style.maxHeight = isOpen ? submenu.scrollHeight + 'px' : '0px';
        };

        submenu.hidden = false;
        setOpen(submenuHasHash());

        toggle.addEventListener('click', () => {
            setOpen(!group.classList.contains('is-open'));
        });

        submenuLinks.forEach((link) => {
            link.addEventListener('click', () => {
                setOpen(true);
                openCollapsibleForHash(link.getAttribute('href'));
            });
        });

        window.addEventListener('hashchange', () => {
            if (submenuHasHash()) {
                setOpen(true);
            }
        });
    });

    document.querySelectorAll('.collapse-toggle').forEach((collapseBtn) => {
        const targetId = collapseBtn.getAttribute('data-collapse');
        const collapsibleContent = targetId ? document.getElementById(targetId) : null;
        const collapsibleCard = collapseBtn.closest('.admin-collapsible, .collapsible-card');
        if (!collapsibleCard || !collapsibleContent) return;
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
                // localStorage may be unavailable in private or restricted contexts.
            }
        }

        const syncButtonLabel = () => {
            collapseBtn.textContent = collapsibleCard.classList.contains('is-collapsed') ? 'Mostrar' : 'Amagar';
        };

        syncButtonLabel();

        collapseBtn.addEventListener('click', () => {
            const willOpen = collapsibleCard.classList.contains('is-collapsed');
            collapsibleCard.classList.toggle('is-collapsed');
            syncButtonLabel();

            if (storageKey) {
                try {
                    window.localStorage.setItem(storageKey, willOpen ? 'open' : 'closed');
                } catch (error) {
                    // Keep the collapsible functional if persistence is unavailable.
                }
            }

            if (willOpen && targetId === 'visites-content') {
                window.setTimeout(refreshGeoMap, 420);
            }
        });
    });

    openCollapsibleForHash(window.location.hash);
    window.addEventListener('hashchange', () => openCollapsibleForHash(window.location.hash));

    const backToTop = document.querySelector('.admin-back-to-top');
    if (backToTop) {
        const updateBackToTop = () => {
            backToTop.classList.toggle('is-visible', window.scrollY > 520);
        };

        updateBackToTop();
        window.addEventListener('scroll', updateBackToTop, { passive: true });
        backToTop.addEventListener('click', (event) => {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    document.querySelectorAll('[data-sortable-table]').forEach((tbl) => {
        const thead = tbl.querySelector('thead');
        if (!thead) return;

        thead.addEventListener('click', (e) => {
            const th = e.target.closest('th[data-sort-type]');
            if (!th) return;

            const idx = Array.from(th.parentNode.children).indexOf(th);
            const tbody = tbl.querySelector('tbody');
            if (!tbody) return;

            const dir = th.classList.contains('sort-asc') ? 'desc' : 'asc';
            th.closest('tr').querySelectorAll('th').forEach((h) => {
                h.classList.remove('sort-asc', 'sort-desc');
                h.removeAttribute('aria-sort');
            });
            th.classList.add('sort-' + dir);
            th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');

            const dataRows = Array.from(tbody.querySelectorAll('tr[data-class]'));
            const sortType = th.getAttribute('data-sort-type') || 'text';
            const editorMap = new Map();

            dataRows.forEach((r) => {
                const next = r.nextElementSibling;
                if (next && next.classList.contains('student-editor-row')) editorMap.set(r, next);
            });

            dataRows.sort((a, b) => {
                const va = (a.children[idx]?.getAttribute('data-sort-value') || a.children[idx]?.textContent || '').trim();
                const vb = (b.children[idx]?.getAttribute('data-sort-value') || b.children[idx]?.textContent || '').trim();

                if (sortType === 'number') {
                    const na = Number.parseFloat(va.replace(',', '.')) || 0;
                    const nb = Number.parseFloat(vb.replace(',', '.')) || 0;
                    return dir === 'asc' ? na - nb : nb - na;
                }

                return dir === 'asc'
                    ? va.localeCompare(vb, 'ca', { numeric: true, sensitivity: 'base' })
                    : vb.localeCompare(va, 'ca', { numeric: true, sensitivity: 'base' });
            });

            const frag = document.createDocumentFragment();
            dataRows.forEach((r) => {
                frag.appendChild(r);
                const ed = editorMap.get(r);
                if (ed) frag.appendChild(ed);
            });
            tbody.appendChild(frag);
        });
    });

    document.querySelectorAll('[data-filter-table]').forEach((bar) => {
        const table = document.getElementById(bar.getAttribute('data-filter-table') || '');
        if (!table) return;

        const yearSelect = bar.querySelector('[data-year-filter]');
        const yearChips = Array.from(bar.querySelectorAll('[data-year-filter-chip]'));
        const classChipList = bar.querySelector('[data-class-chip-list]');
        const classChips = Array.from(bar.querySelectorAll('[data-class-filter-chip]'));

        const selectedYearValue = () => {
            return yearSelect ? yearSelect.value : 'all';
        };

        const resetClassChips = () => {
            classChips.forEach((chip) => {
                chip.classList.toggle('is-active', chip.getAttribute('data-value') === 'all');
            });
        };

        const syncYearChips = () => {
            const selectedYear = selectedYearValue();
            yearChips.forEach((chip) => {
                chip.classList.toggle('is-active', chip.getAttribute('data-value') === selectedYear);
            });
        };

        const updateClassChips = () => {
            if (!classChipList || classChips.length === 0) return;

            const selectedYear = selectedYearValue();
            const showClassChips = selectedYear !== '' && selectedYear !== 'all';
            classChipList.hidden = !showClassChips;

            classChips.forEach((chip) => {
                const chipYear = chip.getAttribute('data-class-year') || '';
                const isAllChip = chip.getAttribute('data-value') === 'all';
                chip.hidden = !showClassChips || (!isAllChip && chipYear !== selectedYear);
                if (chip.hidden && !isAllChip) chip.classList.remove('is-active');
            });

            const hasActiveClass = classChips.some((chip) => {
                return !chip.hidden && chip.getAttribute('data-value') !== 'all' && chip.classList.contains('is-active');
            });
            const allClassChip = classChips.find((chip) => chip.getAttribute('data-value') === 'all');
            if (allClassChip) allClassChip.classList.toggle('is-active', !hasActiveClass);
        };

        const applyFilters = () => {
            syncYearChips();
            updateClassChips();

            const chips = classChips.length > 0
                ? classChips.filter((chip) => !chip.hidden)
                : Array.from(bar.querySelectorAll('.admin-filters__chip, .filter-chip'));
            const activeClasses = chips
                .filter((item) => item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active'))
                .map((item) => normalizeFilterValue(item.getAttribute('data-value')));
            const showAllClasses = activeClasses.length === 0;
            const selectedYear = selectedYearValue();
            const showAllYears = selectedYear === '' || selectedYear === 'all';

            table.querySelectorAll('tbody tr[data-class]').forEach((row) => {
                const rowClass = normalizeFilterValue(row.getAttribute('data-class'));
                const rowYear = row.getAttribute('data-academic-year') || '';
                const isClassVisible = showAllClasses || activeClasses.includes(rowClass);
                const isYearVisible = showAllYears || rowYear === selectedYear;
                const isVisible = isClassVisible && isYearVisible;
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

        bar.addEventListener('click', (event) => {
            const chip = event.target.closest('.admin-filters__chip, .filter-chip');
            if (!chip || !bar.contains(chip)) return;

            if (chip.matches('[data-year-filter-chip]')) {
                const value = chip.getAttribute('data-value') || 'all';
                if (yearSelect) yearSelect.value = value;
                resetClassChips();
                applyFilters();
                return;
            }

            const chips = classChips.length > 0
                ? classChips.filter((item) => !item.hidden)
                : Array.from(bar.querySelectorAll('.admin-filters__chip, .filter-chip'));
            const value = chip.getAttribute('data-value');

            if (value === 'all') {
                chips.forEach((item) => item.classList.toggle('is-active', item === chip));
            } else {
                chip.classList.toggle('is-active');
                const hasActiveClass = chips.some((item) => {
                    return item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active');
                });
                const allChip = chips.find((item) => item.getAttribute('data-value') === 'all');
                if (allChip) allChip.classList.toggle('is-active', !hasActiveClass);
            }

            applyFilters();
        });

        if (yearSelect) {
            yearSelect.addEventListener('change', () => {
                resetClassChips();
                applyFilters();
            });
        }

        applyFilters();
    });

    document.querySelectorAll('[data-user-filter]').forEach((bar) => {
        const table = document.getElementById(bar.getAttribute('data-user-filter') || '');
        if (!table) return;

        const searchInput = bar.querySelector('[data-user-search]');
        const statusSelect = bar.querySelector('[data-status-filter]');
        const chips = Array.from(bar.querySelectorAll('.admin-filters__chip'));
        const countTarget = document.getElementById(bar.getAttribute('data-count-target') || '');
        const countLabel = bar.getAttribute('data-count-label') || 'resultats';

        const applyUserFilters = () => {
            const query = normalizeFilterValue(searchInput?.value || '');
            const status = statusSelect?.value || 'all';
            const activeClasses = chips
                .filter((chip) => chip.getAttribute('data-value') !== 'all' && chip.classList.contains('is-active'))
                .map((chip) => normalizeFilterValue(chip.getAttribute('data-value') || ''));
            const showAllClasses = activeClasses.length === 0;
            let visible = 0;

            table.querySelectorAll('tbody tr[data-user-row]').forEach((row) => {
                const rowClass = normalizeFilterValue(row.getAttribute('data-class') || '');
                const rowStatus = row.getAttribute('data-status') || '';
                const rowSearch = normalizeFilterValue(row.getAttribute('data-search') || '');
                const classMatches = showAllClasses || activeClasses.includes(rowClass);
                const statusMatches = status === 'all' || status === rowStatus;
                const searchMatches = query === '' || rowSearch.includes(query);
                const isVisible = classMatches && statusMatches && searchMatches;
                const editorRow = row.nextElementSibling;

                row.hidden = !isVisible;
                if (editorRow?.classList.contains('student-editor-row')) {
                    editorRow.hidden = !isVisible;
                    if (!isVisible) editorRow.classList.remove('open');
                }

                if (isVisible) visible++;
            });

            if (countTarget) {
                countTarget.textContent = visible + ' ' + countLabel;
            }
        };

        bar.addEventListener('click', (event) => {
            const chip = event.target.closest('.admin-filters__chip');
            if (!chip || !bar.contains(chip)) return;

            if (chip.getAttribute('data-value') === 'all') {
                chips.forEach((item) => item.classList.toggle('is-active', item === chip));
            } else {
                chip.classList.toggle('is-active');
                const hasActiveClass = chips.some((item) => item.getAttribute('data-value') !== 'all' && item.classList.contains('is-active'));
                chips.forEach((item) => {
                    if (item.getAttribute('data-value') === 'all') item.classList.toggle('is-active', !hasActiveClass);
                });
            }

            applyUserFilters();
        });

        searchInput?.addEventListener('input', applyUserFilters);
        statusSelect?.addEventListener('change', applyUserFilters);
        applyUserFilters();
    });

    document.querySelectorAll('[data-team-filters]').forEach((filters) => {
        const yearSelect = filters.querySelector('[data-team-year-filter]');
        const projectSelect = filters.querySelector('[data-team-project-filter]');
        const projectOptions = Array.from(filters.querySelectorAll('[data-team-project-option]'));
        const countEl = filters.querySelector('[data-team-filter-count]');
        const rows = Array.from(document.querySelectorAll('[data-team-row]'));
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

                if (isVisible && option.value === projectSelect.value) {
                    selectedProjectStillVisible = true;
                }
            });

            if (!selectedProjectStillVisible) {
                projectSelect.value = 'all';
            }
        };

        const applyTeamFilters = () => {
            updateProjectOptions();

            const selectedYear = yearSelect.value || 'all';
            const selectedProject = projectSelect.value || 'all';
            const showAllYears = selectedYear === 'all' || selectedYear === '';
            const showAllProjects = selectedProject === 'all' || selectedProject === '';
            let visibleCount = 0;

            rows.forEach((row) => {
                const yearMatches = showAllYears || row.getAttribute('data-team-year') === selectedYear;
                const projectMatches = showAllProjects || row.getAttribute('data-team-project') === selectedProject;
                const isVisible = yearMatches && projectMatches;
                row.hidden = !isVisible;
                if (isVisible) visibleCount++;
            });

            if (countEl) {
                countEl.textContent = visibleCount + ' equips';
            }
        };

        const storedYear = window.localStorage.getItem(yearStorageKey);
        if (storedYear) {
            yearSelect.value = storedYear;
        }

        updateProjectOptions();

        const storedProject = window.localStorage.getItem(projectStorageKey);
        if (storedProject) {
            projectSelect.value = storedProject;
        }

        applyTeamFilters();

        yearSelect.addEventListener('change', () => {
            window.localStorage.setItem(yearStorageKey, yearSelect.value);
            projectSelect.value = 'all';
            window.localStorage.setItem(projectStorageKey, projectSelect.value);
            applyTeamFilters();
        });

        projectSelect.addEventListener('change', () => {
            window.localStorage.setItem(projectStorageKey, projectSelect.value);
            applyTeamFilters();
        });
    });

    document.querySelectorAll('[data-role-filter]').forEach((select) => {
        const projectSelect = document.querySelector('[data-project-role-filter]');
        const groups = Array.from(document.querySelectorAll('[data-role-group]'));
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
                const groupName = normalize(group.getAttribute('data-role-name'));
                const roleMatches = showAllRoles || groupName === normalizedRoleValue;
                const rows = Array.from(group.querySelectorAll('[data-role-member-row]'));
                let visibleRows = 0;

                rows.forEach((row) => {
                    const projectMatches = showAllProjects || row.getAttribute('data-project-key') === selectedProject;
                    const isVisible = roleMatches && projectMatches;
                    row.hidden = !isVisible;
                    if (isVisible) visibleRows++;
                });

                group.hidden = !roleMatches || visibleRows === 0;
            });
        };

        const storedValue = window.localStorage.getItem(storageKey);
        if (storedValue) {
            select.value = storedValue;
        }

        const storedProjectValue = window.localStorage.getItem(projectStorageKey);
        if (projectSelect && storedProjectValue) {
            projectSelect.value = storedProjectValue;
        }

        applyFilter();

        select.addEventListener('change', () => {
            window.localStorage.setItem(storageKey, select.value);
            applyFilter();
        });

        if (projectSelect) {
            projectSelect.addEventListener('change', () => {
                window.localStorage.setItem(projectStorageKey, projectSelect.value);
                applyFilter();
            });
        }
    });

    // ── lightbox d'avatars d'alumnes ──
    const lightbox = document.getElementById('avatar-lightbox');
    const lightboxImg = document.getElementById('avatar-lightbox-img');
    const lightboxCaption = document.getElementById('avatar-lightbox-caption');
    const lightboxClose = document.getElementById('avatar-lightbox-close');
    const lightboxOverlay = document.getElementById('avatar-lightbox-overlay');

    if (lightbox && lightboxImg) {
        const closeLightbox = () => {
            lightbox.hidden = true;
            lightboxImg.src = '';
            lightboxCaption.textContent = '';
            document.body.style.overflow = '';
        };

        document.querySelectorAll('.user-avatar-trigger').forEach((trigger) => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const src = trigger.getAttribute('data-avatar-src') || '';
                const name = trigger.getAttribute('data-avatar-name') || '';

                if (src !== '') {
                    lightboxImg.src = src;
                    lightboxCaption.textContent = name;
                    lightbox.hidden = false;
                    document.body.style.overflow = 'hidden';
                }
            });
        });

        if (lightboxClose) {
            lightboxClose.addEventListener('click', closeLightbox);
        }

        if (lightboxOverlay) {
            lightboxOverlay.addEventListener('click', closeLightbox);
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            }
        });
    }
});
