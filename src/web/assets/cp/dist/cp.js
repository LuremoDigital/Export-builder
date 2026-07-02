(function () {
    function initEditorTabs() {
        const tabRoots = Array.from(document.querySelectorAll('[data-editor-tabs]'));

        tabRoots.forEach(function (root) {
            const triggers = Array.from(root.querySelectorAll('[data-editor-tab-trigger]'));
            const hiddenInput = document.querySelector('#editorTab');

            function setActiveTab(tabName) {
                const availableTabs = triggers.map((trigger) => trigger.dataset.editorTabTrigger);
                const nextTab = availableTabs.includes(tabName) ? tabName : (availableTabs[0] || 'setup');

                triggers.forEach(function (trigger) {
                    const isActive = trigger.dataset.editorTabTrigger === nextTab;
                    trigger.classList.toggle('is-active', isActive);
                    trigger.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                document.querySelectorAll('[data-editor-tab-panel]').forEach(function (panel) {
                    const isActive = panel.dataset.editorTabPanel === nextTab;
                    panel.classList.toggle('hidden', !isActive);
                });

                if (hiddenInput) {
                    hiddenInput.value = nextTab;
                }
            }

            setActiveTab(hiddenInput?.value || 'setup');

            triggers.forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    setActiveTab(trigger.dataset.editorTabTrigger || 'setup');
                });
            });
        });
    }

    function initSettingsConditionals() {
        const root = document.querySelector('[data-deb-settings]');
        if (!root) {
            return;
        }

        const scheduleEnabled = root.querySelector('#scheduleEnabled');
        const scheduleFrequency = root.querySelector('#scheduleFrequency');
        const emailRecipients = root.querySelector('#emailRecipients');
        const webhookUrl = root.querySelector('#webhookUrl');
        const remoteVolumeUid = root.querySelector('#remoteVolumeUid');

        function getState() {
            const frequency = scheduleFrequency ? scheduleFrequency.value : 'daily';

            return {
                scheduleEnabled: !!scheduleEnabled?.checked,
                scheduleIsWeekly: frequency === 'weekly',
                scheduleUsesHour: frequency !== 'hourly',
                hasEmailRecipients: !!emailRecipients?.value.trim(),
                hasWebhookUrl: !!webhookUrl?.value.trim(),
                hasRemoteVolume: !!remoteVolumeUid?.value.trim()
            };
        }

        function update() {
            const state = getState();

            root.querySelectorAll('[data-settings-conditional]').forEach(function (element) {
                const requirements = (element.dataset.settingsConditional || '')
                    .split(',')
                    .map(function (value) {
                        return value.trim();
                    })
                    .filter(Boolean);

                const isVisible = requirements.every(function (requirement) {
                    return !!state[requirement];
                });

                element.classList.toggle('hidden', !isVisible);
            });
        }

        [scheduleEnabled, scheduleFrequency, emailRecipients, webhookUrl, remoteVolumeUid].forEach(function (field) {
            if (!field) {
                return;
            }

            field.addEventListener('change', update);
            if (field.tagName === 'TEXTAREA' || field.tagName === 'INPUT') {
                field.addEventListener('input', update);
            }
        });

        update();
    }

    function validateXmlElementName(value) {
        const name = String(value || '').trim();

        if (!name) {
            return 'Enter an XML element name.';
        }

        if (!/^[A-Za-z_]/.test(name)) {
            return 'XML element names must start with a letter or underscore.';
        }

        if (!/^[A-Za-z_][A-Za-z0-9_\-.]*$/.test(name)) {
            return 'Use letters, numbers, underscores, hyphens, or periods. Spaces are not allowed.';
        }

        if (/^xml/i.test(name)) {
            return 'XML element names cannot use the reserved xml or xmlns names.';
        }

        return null;
    }

    function initXmlFormatSettings() {
        const formatSelect = document.querySelector('#format');
        if (!formatSelect) {
            return;
        }

        const xmlSettings = document.querySelector('[data-xml-settings]');
        const xmlFieldHint = document.querySelector('[data-xml-field-hint]');

        function updateVisibility() {
            const isXml = formatSelect.value === 'xml';

            if (xmlSettings) {
                xmlSettings.classList.toggle('hidden', !isXml);
            }

            if (xmlFieldHint) {
                xmlFieldHint.classList.toggle('hidden', !isXml);
            }
        }

        formatSelect.addEventListener('change', updateVisibility);
        updateVisibility();

        // Lightweight pre-submit feedback for the two XML name fields. The
        // server-side validation in TemplateService stays authoritative.
        ['#xmlRootElement', '#xmlRowElement'].forEach(function (selector) {
            const input = xmlSettings?.querySelector(selector);
            if (!input) {
                return;
            }

            const error = document.createElement('p');
            error.className = 'deb-xml-name-error hidden';
            input.insertAdjacentElement('afterend', error);

            input.addEventListener('input', function () {
                const message = validateXmlElementName(input.value);
                error.textContent = message || '';
                error.classList.toggle('hidden', !message);
                input.classList.toggle('error', !!message);
            });
        });
    }

    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function compareGroups(a, b) {
        if (a === 'Matrix' && b !== 'Matrix') {
            return 1;
        }

        if (b === 'Matrix' && a !== 'Matrix') {
            return -1;
        }

        return a.localeCompare(b);
    }

    function renderPresets(root) {
        const presets = Array.isArray(root._payload?.presets) ? root._payload.presets : [];
        const target = root.querySelector('[data-preset-list]');
        if (!target) {
            return;
        }

        if (!presets.length) {
            target.classList.add('hidden');
            target.innerHTML = '';
            return;
        }

        target.classList.remove('hidden');
        target.innerHTML = presets.map(function (preset, index) {
            return '<button type="button" class="btn small" data-apply-preset="' + String(index) + '">' + escapeHtml(preset.label) + '</button>';
        }).join('');
    }

    function getSelectedRows(root) {
        return Array.from(root.querySelectorAll('[data-selected-row]'));
    }

    function getSelectedPaths(root) {
        return getSelectedRows(root).map((row) => row.dataset.fieldPath);
    }

    function clearDragState(root) {
        getSelectedRows(root).forEach(function (row) {
            row.classList.remove('is-dragging');
            row.classList.remove('is-drop-target');
        });
    }

    function renumberRows(root) {
        getSelectedRows(root).forEach((row, index) => {
            row.querySelector('[data-name="fieldPath"]').name = 'fields[' + index + '][fieldPath]';
            row.querySelector('[data-name="sortOrder"]').name = 'fields[' + index + '][sortOrder]';
            row.querySelector('[data-name="sortOrder"]').value = String(index + 1);
            row.querySelector('[data-name="columnLabel"]').name = 'fields[' + index + '][columnLabel]';
        });

        const emptyState = root.querySelector('[data-selected-empty]');
        if (emptyState) {
            emptyState.classList.toggle('hidden', getSelectedRows(root).length > 0);
        }
    }

    function createSelectedRow(field) {
        const row = document.createElement('div');
        row.className = 'deb-selected-row';
        row.dataset.selectedRow = 'true';
        row.dataset.fieldPath = field.path;
        row.draggable = true;
        row.innerHTML = [
            '<div class="deb-selected-row__main">',
            '<input type="hidden" data-name="fieldPath" value="' + escapeHtml(field.path) + '">',
            '<input type="hidden" data-name="sortOrder" value="0">',
            '<div class="deb-selected-path-wrap">',
            '<button type="button" class="deb-drag-handle" data-drag-handle draggable="true" aria-label="Drag to reorder" title="Drag to reorder">Drag</button>',
            '<div class="deb-selected-path">' + escapeHtml(field.path) + '</div>',
            '</div>',
            '<label class="deb-selected-label" draggable="true">Export column title</label>',
            '<input class="text fullwidth" type="text" data-name="columnLabel" value="' + escapeHtml(field.label) + '" placeholder="Custom column title">',
            '</div>',
            '<div class="deb-selected-row__actions">',
            '<button type="button" class="btn small" data-remove-field>Remove</button>',
            '</div>'
        ].join('');

        return row;
    }

    function renderAvailableFields(root) {
        const payload = root._payload || { fields: [] };
        const target = root.querySelector('[data-available-fields]');
        const searchTerm = (root.querySelector('[data-field-search]')?.value || '').trim().toLowerCase();
        const selected = new Set(getSelectedPaths(root));
        const groups = {};

        (payload.fields || []).forEach((field) => {
            const haystack = (field.label + ' ' + field.path + ' ' + field.group).toLowerCase();
            if (searchTerm && !haystack.includes(searchTerm)) {
                return;
            }

            groups[field.group] = groups[field.group] || [];
            groups[field.group].push(field);
        });

        const groupNames = Object.keys(groups).sort(compareGroups);

        // Track which groups are expanded across re-renders. Default to the
        // first group open and the rest collapsed so the list isn't a wall of
        // every field at once; an active search expands every matching group.
        // loadPayload() resets this to null on element-type change so the
        // first group of the new set opens instead of everything staying shut.
        if (!root._expandedGroups) {
            root._expandedGroups = new Set(groupNames.slice(0, 1));
        }

        const html = groupNames.map((group) => {
            const isExpanded = !!searchTerm || root._expandedGroups.has(group);
            const fields = groups[group].map((field) => {
                const isSelected = selected.has(field.path);
                return [
                    '<button type="button" class="deb-available-field' + (isSelected ? ' is-selected' : '') + '"',
                    ' data-add-field',
                    ' data-path="' + escapeHtml(field.path) + '"',
                    ' data-label="' + escapeHtml(field.label) + '"',
                    ' data-group="' + escapeHtml(field.group) + '"',
                    isSelected ? ' disabled' : '',
                    '>',
                    '<span class="deb-available-field__label">' + escapeHtml(field.label) + '</span>',
                    '<code>' + escapeHtml(field.path) + '</code>',
                    '</button>'
                ].join('');
            }).join('');

            return [
                '<section class="deb-available-group' + (isExpanded ? '' : ' is-collapsed') + '" data-group="' + escapeHtml(group) + '">',
                '<button type="button" class="deb-available-group__toggle" data-group-toggle="' + escapeHtml(group) + '" aria-expanded="' + (isExpanded ? 'true' : 'false') + '">',
                '<h3>' + escapeHtml(group) + '</h3>',
                '<span class="deb-available-group__count">' + groups[group].length + '</span>',
                '<span class="deb-available-group__chevron" aria-hidden="true"></span>',
                '</button>',
                '<div class="deb-available-group__list">' + fields + '</div>',
                '</section>'
            ].join('');
        }).join('');

        target.innerHTML = html || '<p class="light">No matching fields for this element type.</p>';
    }

    function syncSelectOptions(select, options, preferredValue) {
        if (!select) {
            return;
        }

        const currentValue = preferredValue ?? select.value;
        const normalizedOptions = Array.isArray(options) ? options : [];
        const hasCurrentValue = normalizedOptions.some((option) => String(option.value ?? '') === String(currentValue ?? ''));
        const nextValue = hasCurrentValue ? String(currentValue ?? '') : String(normalizedOptions[0]?.value ?? '');

        select.innerHTML = normalizedOptions.map((option) => {
            const value = String(option.value ?? '');
            const label = String(option.label ?? value);

            return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
        }).join('');

        select.value = nextValue;
    }

    function syncMultiSelectOptions(select, options) {
        if (!select) {
            return;
        }

        const selected = new Set(Array.from(select.selectedOptions || []).map((option) => String(option.value || '')));
        const normalizedOptions = Array.isArray(options) ? options : [];
        select.innerHTML = normalizedOptions.map((option) => {
            const value = String(option.value ?? '');
            const label = String(option.label ?? value);
            const isSelected = selected.has(value);

            return '<option value="' + escapeHtml(value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
    }

    function optionValues(options) {
        return new Set((Array.isArray(options) ? options : []).map((option) => String(option.value ?? option.handle ?? '')));
    }

    function syncFilterOptions(root) {
        const payload = root._payload || {};
        const sectionSelect = document.querySelector(root.dataset.sectionSelect || '');
        const siteSelect = document.querySelector(root.dataset.siteFilterTarget || '')?.querySelector('select');
        const formSelect = document.querySelector(root.dataset.formSelect || '');
        const statusSelect = document.querySelector(root.dataset.statusFilterTarget || '')?.querySelector('select');

        syncSelectOptions(sectionSelect, payload.sections || [], sectionSelect?.value);
        syncSelectOptions(siteSelect, payload.sites || [], siteSelect?.value);
        syncSelectOptions(formSelect, payload.forms || [], formSelect?.value);
        syncMultiSelectOptions(statusSelect, payload.statuses || []);
        syncAdvancedFilterOptions(root);
    }

    function updateFilterVisibility(root) {
        const payload = root._payload || {};
        const sectionRow = document.querySelector(root.dataset.sectionFilterTarget || '');
        const siteRow = document.querySelector(root.dataset.siteFilterTarget || '');
        const formRow = document.querySelector(root.dataset.formFilterTarget || '');
        const statusRow = document.querySelector(root.dataset.statusFilterTarget || '');
        const keywordRow = document.querySelector(root.dataset.keywordFilterTarget || '');
        const fieldConditionRow = document.querySelector(root.dataset.fieldConditionFilterTarget || '');
        const relationRow = document.querySelector(root.dataset.relationFilterTarget || '');
        const advancedCard = document.querySelector(root.dataset.advancedFilterCard || '');
        const populatedToggle = root.querySelector(root.dataset.populatedToggle || '');

        if (sectionRow) {
            sectionRow.classList.toggle('hidden', !payload.supportsSectionFilter);
        }

        if (siteRow) {
            siteRow.classList.toggle('hidden', !payload.supportsSiteFilter);
        }

        if (formRow) {
            formRow.classList.toggle('hidden', !payload.supportsFormFilter);
        }

        if (statusRow) {
            statusRow.classList.toggle('hidden', !payload.supportsStatusFilter);
        }

        if (keywordRow) {
            keywordRow.classList.toggle('hidden', !payload.supportsKeywordFilter);
        }

        if (fieldConditionRow) {
            fieldConditionRow.classList.toggle('hidden', !payload.supportsFieldConditionFilter);
        }

        if (relationRow) {
            relationRow.classList.toggle('hidden', !payload.supportsRelationFilter);
        }

        if (advancedCard) {
            advancedCard.classList.toggle('hidden', !payload.supportsFieldConditionFilter && !payload.supportsRelationFilter);
        }

        if (populatedToggle) {
            populatedToggle.checked = !!payload.onlyPopulated;
            populatedToggle.closest('label')?.classList.toggle('hidden', !payload.supportsPopulatedFilter);
            if (!payload.supportsPopulatedFilter) {
                populatedToggle.checked = false;
            }
        }
    }

    function setFilterLoading(root, isLoading) {
        const card = document.querySelector(root.dataset.advancedFilterCard || '');
        const loading = card?.querySelector('[data-filter-loading]');
        const error = card?.querySelector('[data-filter-error]');

        root.classList.toggle('is-loading', isLoading);
        if (loading) {
            loading.classList.toggle('hidden', !isLoading);
        }
        if (error && isLoading) {
            error.classList.add('hidden');
        }

        if (card) {
            card.querySelectorAll('input, select, button').forEach(function (field) {
                if (field.hasAttribute('data-filter-retry')) {
                    return;
                }

                field.disabled = isLoading;
            });
        }
    }

    function showFilterError(root) {
        const card = document.querySelector(root.dataset.advancedFilterCard || '');
        card?.querySelector('[data-filter-error]')?.classList.remove('hidden');
    }

    function createOptionHtml(value, label, selected, attrs) {
        return '<option value="' + escapeHtml(value) + '"' + (selected ? ' selected' : '') + (attrs || '') + '>' + escapeHtml(label) + '</option>';
    }

    function fieldOptionsHtml(fields, currentValue) {
        const handles = optionValues(fields);
        let html = createOptionHtml('', 'Select field', !currentValue);
        (Array.isArray(fields) ? fields : []).forEach(function (field) {
            html += createOptionHtml(
                String(field.handle || ''),
                String(field.label || field.handle || ''),
                String(field.handle || '') === String(currentValue || ''),
                ' data-operators="' + escapeHtml(JSON.stringify(field.operators || [])) + '"'
            );
        });

        if (currentValue && !handles.has(String(currentValue))) {
            html += createOptionHtml(String(currentValue), String(currentValue) + ' (missing)', true, ' data-stale="true"');
        }

        return html;
    }

    function relationOptionsHtml(fields, currentValue) {
        const handles = optionValues(fields);
        let html = createOptionHtml('', 'Select relation field', !currentValue);
        (Array.isArray(fields) ? fields : []).forEach(function (field) {
            html += createOptionHtml(
                String(field.handle || ''),
                String(field.label || field.handle || ''),
                String(field.handle || '') === String(currentValue || '')
            );
        });

        if (currentValue && !handles.has(String(currentValue))) {
            html += createOptionHtml(String(currentValue), String(currentValue) + ' (missing)', true, ' data-stale="true"');
        }

        return html;
    }

    function operatorOptionsForField(fieldSelect) {
        const selectedOption = fieldSelect?.selectedOptions?.[0];
        return parseJson(selectedOption?.dataset.operators || '[]', []);
    }

    function syncOperatorSelect(row) {
        const fieldSelect = row.querySelector('[data-filter-field]');
        const operatorSelect = row.querySelector('[data-filter-operator]');
        const valueInput = row.querySelector('[data-filter-value]');
        if (!operatorSelect) {
            return;
        }

        const current = operatorSelect.value;
        const operators = operatorOptionsForField(fieldSelect);
        const values = optionValues(operators);
        const nextValue = values.has(current) ? current : String(operators[0]?.value || '');
        operatorSelect.disabled = operators.length === 0;
        operatorSelect.innerHTML = operators.map(function (operator) {
            return createOptionHtml(String(operator.value || ''), String(operator.label || operator.value || ''), String(operator.value || '') === nextValue);
        }).join('');
        operatorSelect.value = nextValue;

        if (valueInput) {
            valueInput.classList.toggle('hidden', nextValue === 'notEmpty');
            valueInput.disabled = nextValue === 'notEmpty' || operators.length === 0;
        }
    }

    function syncAdvancedFilterOptions(root) {
        const payload = root._payload || {};
        const card = document.querySelector(root.dataset.advancedFilterCard || '');
        if (!card) {
            return;
        }

        const filterableFields = payload.filterableFields || [];
        const relationFields = payload.relationFields || [];

        card.querySelectorAll('[data-field-condition-row]').forEach(function (row) {
            const select = row.querySelector('[data-filter-field]');
            if (select) {
                select.innerHTML = fieldOptionsHtml(filterableFields, select.value);
            }
            syncOperatorSelect(row);
        });

        card.querySelectorAll('[data-relation-row]').forEach(function (row) {
            const select = row.querySelector('[data-relation-field]');
            if (select) {
                select.innerHTML = relationOptionsHtml(relationFields, select.value);
            }
        });

        card.querySelector('[data-field-condition-empty]')?.classList.toggle('hidden', filterableFields.length > 0);
        card.querySelector('[data-relation-filter-empty]')?.classList.toggle('hidden', relationFields.length > 0);
    }

    function renumberAdvancedFilters(root) {
        const card = document.querySelector(root.dataset.advancedFilterCard || '');
        if (!card) {
            return;
        }

        card.querySelectorAll('[data-field-condition-row]').forEach(function (row, index) {
            row.querySelector('[data-filter-field]').name = 'filters[fieldConditions][' + index + '][field]';
            row.querySelector('[data-filter-operator]').name = 'filters[fieldConditions][' + index + '][operator]';
            row.querySelector('[data-filter-value]').name = 'filters[fieldConditions][' + index + '][value]';
        });

        card.querySelectorAll('[data-relation-row]').forEach(function (row, index) {
            row.querySelector('[data-relation-field]').name = 'filters[relations][' + index + '][field]';
            row.querySelector('[data-relation-targets]').name = 'filters[relations][' + index + '][targetIds]';
        });
    }

    function createFieldConditionRow(root) {
        const row = document.createElement('div');
        row.className = 'deb-filter-row';
        row.dataset.fieldConditionRow = 'true';
        row.innerHTML = [
            '<select class="select fullwidth" data-filter-field></select>',
            '<select class="select fullwidth" data-filter-operator></select>',
            '<input class="text fullwidth" type="text" data-filter-value placeholder="Value">',
            '<button type="button" class="btn small" data-remove-filter-row>Remove</button>'
        ].join('');

        row.querySelector('[data-filter-field]').innerHTML = fieldOptionsHtml(root._payload?.filterableFields || [], '');
        syncOperatorSelect(row);

        return row;
    }

    function createRelationFilterRow(root) {
        const row = document.createElement('div');
        row.className = 'deb-filter-row';
        row.dataset.relationRow = 'true';
        row.innerHTML = [
            '<select class="select fullwidth" data-relation-field></select>',
            '<input class="text fullwidth" type="text" data-relation-targets placeholder="Target element IDs">',
            '<button type="button" class="btn small" data-remove-filter-row>Remove</button>'
        ].join('');

        row.querySelector('[data-relation-field]').innerHTML = relationOptionsHtml(root._payload?.relationFields || [], '');

        return row;
    }

    function setAdvancedBodyVisible(root, isVisible) {
        const card = document.querySelector(root.dataset.advancedFilterCard || '');
        const body = card?.querySelector('[data-advanced-filter-body]');
        const toggle = card?.querySelector('[data-advanced-filter-toggle]');
        if (!body || !toggle) {
            return;
        }

        body.classList.toggle('hidden', !isVisible);
        toggle.textContent = isVisible ? 'Hide' : 'Show';
        toggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
    }

    function loadPayload(root, elementType) {
        const url = new URL(root.dataset.fieldsUrl, window.location.origin);
        url.searchParams.set('elementType', elementType);
        const sectionSelect = document.querySelector(root.dataset.sectionSelect || '');
        if (sectionSelect && sectionSelect.value) {
            url.searchParams.set('sectionUid', sectionSelect.value);
        }
        const formSelect = document.querySelector(root.dataset.formSelect || '');
        if (formSelect && formSelect.value) {
            url.searchParams.set('formId', formSelect.value);
        }
        const populatedToggle = root.querySelector(root.dataset.populatedToggle || '');
        if (populatedToggle && populatedToggle.checked) {
            url.searchParams.set('onlyPopulated', '1');
        }

        setFilterLoading(root, true);
        fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Field discovery failed.');
                }

                return response.json();
            })
            .then((payload) => {
                root._payload = payload;
                // New element type means a new set of field groups; reset the
                // expanded-group state so renderAvailableFields re-seeds the
                // first group open instead of leaving everything collapsed.
                root._expandedGroups = null;
                syncFilterOptions(root);
                renderAvailableFields(root);
                updateFilterVisibility(root);
                renderPresets(root);
                setFilterLoading(root, false);
            })
            .catch(() => {
                setFilterLoading(root, false);
                showFilterError(root);
            });
    }

    function initPicker(root) {
        root._payload = parseJson(root.dataset.initialPayload, { fields: [] });
        let draggedRow = null;
        renumberRows(root);
        renderAvailableFields(root);
        updateFilterVisibility(root);
        syncAdvancedFilterOptions(root);
        renumberAdvancedFilters(root);
        renderPresets(root);

        const elementSelect = document.querySelector(root.dataset.elementSelect || '');
        if (elementSelect) {
            elementSelect.addEventListener('change', function () {
                loadPayload(root, this.value);
            });
        }

        const sectionSelect = document.querySelector(root.dataset.sectionSelect || '');
        if (sectionSelect) {
            sectionSelect.addEventListener('change', function () {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
            });
        }

        const formSelect = document.querySelector(root.dataset.formSelect || '');
        if (formSelect) {
            formSelect.addEventListener('change', function () {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
            });
        }

        const populatedToggle = root.querySelector(root.dataset.populatedToggle || '');
        if (populatedToggle) {
            populatedToggle.addEventListener('change', function () {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
            });
        }

        const search = root.querySelector('[data-field-search]');
        if (search) {
            search.addEventListener('input', function () {
                renderAvailableFields(root);
            });
        }

        root.addEventListener('click', function (event) {
            const target = event.target.closest('button');
            if (!target) {
                return;
            }

            if (target.hasAttribute('data-group-toggle')) {
                const name = target.getAttribute('data-group-toggle');
                if (root._expandedGroups && root._expandedGroups.has(name)) {
                    root._expandedGroups.delete(name);
                } else {
                    (root._expandedGroups = root._expandedGroups || new Set()).add(name);
                }
                renderAvailableFields(root);
                return;
            }

            if (target.hasAttribute('data-add-field')) {
                const field = {
                    path: target.dataset.path,
                    label: target.dataset.label
                };

                if (getSelectedPaths(root).includes(field.path)) {
                    return;
                }

                root.querySelector('[data-selected-fields]').appendChild(createSelectedRow(field));
                renumberRows(root);
                renderAvailableFields(root);
                return;
            }

            if (target.hasAttribute('data-apply-preset')) {
                const preset = root._payload?.presets?.[Number(target.dataset.applyPreset)];
                const selectedFields = root.querySelector('[data-selected-fields]');
                if (!preset || !selectedFields) {
                    return;
                }

                selectedFields.innerHTML = '';
                preset.fields.forEach(function (field) {
                    selectedFields.appendChild(createSelectedRow(field));
                });
                renumberRows(root);
                renderAvailableFields(root);
                return;
            }

            const row = target.closest('[data-selected-row]');
            if (!row) {
                return;
            }

            if (target.hasAttribute('data-remove-field')) {
                row.remove();
            }

            renumberRows(root);
            renderAvailableFields(root);
        });

        const advancedCard = document.querySelector(root.dataset.advancedFilterCard || '');
        advancedCard?.addEventListener('click', function (event) {
            const target = event.target.closest('button');
            if (!target) {
                return;
            }

            if (target.hasAttribute('data-advanced-filter-toggle')) {
                const body = advancedCard.querySelector('[data-advanced-filter-body]');
                setAdvancedBodyVisible(root, body?.classList.contains('hidden'));
                return;
            }

            if (target.hasAttribute('data-filter-retry')) {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
                return;
            }

            if (target.hasAttribute('data-add-field-condition')) {
                advancedCard.querySelector('[data-field-condition-rows]')?.appendChild(createFieldConditionRow(root));
                setAdvancedBodyVisible(root, true);
                renumberAdvancedFilters(root);
                return;
            }

            if (target.hasAttribute('data-add-relation-filter')) {
                advancedCard.querySelector('[data-relation-filter-rows]')?.appendChild(createRelationFilterRow(root));
                setAdvancedBodyVisible(root, true);
                renumberAdvancedFilters(root);
                return;
            }

            if (target.hasAttribute('data-remove-filter-row')) {
                target.closest('.deb-filter-row')?.remove();
                renumberAdvancedFilters(root);
            }
        });

        advancedCard?.addEventListener('change', function (event) {
            const row = event.target.closest('[data-field-condition-row]');
            if (row && event.target.hasAttribute('data-filter-field')) {
                syncOperatorSelect(row);
            }

            if (row && event.target.hasAttribute('data-filter-operator')) {
                syncOperatorSelect(row);
            }
        });

        root.addEventListener('dragstart', function (event) {
            const row = event.target.closest('[data-selected-row]');
            if (!row) {
                return;
            }

            draggedRow = row;
            row.classList.add('is-dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', row.dataset.fieldPath || '');
            }
        });

        root.addEventListener('dragover', function (event) {
            const row = event.target.closest('[data-selected-row]');
            if (!draggedRow || !row || row === draggedRow) {
                return;
            }

            event.preventDefault();
            clearDragState(root);
            draggedRow.classList.add('is-dragging');
            row.classList.add('is-drop-target');
        });

        root.addEventListener('drop', function (event) {
            const row = event.target.closest('[data-selected-row]');
            if (!draggedRow || !row || row === draggedRow) {
                return;
            }

            event.preventDefault();

            const bounds = row.getBoundingClientRect();
            const insertAfter = event.clientY > bounds.top + (bounds.height / 2);

            if (insertAfter) {
                row.parentNode.insertBefore(draggedRow, row.nextElementSibling);
            } else {
                row.parentNode.insertBefore(draggedRow, row);
            }

            clearDragState(root);
            renumberRows(root);
            renderAvailableFields(root);
        });

        root.addEventListener('dragend', function () {
            draggedRow = null;
            clearDragState(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initEditorTabs();
        initSettingsConditionals();
        initXmlFormatSettings();
        document.querySelectorAll('[data-deb-field-picker]').forEach(initPicker);
    });
}());
