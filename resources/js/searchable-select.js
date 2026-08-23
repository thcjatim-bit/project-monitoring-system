export function filterSearchableOptions(options, query = '') {
    const normalizedQuery = String(query).trim().toLocaleLowerCase();

    return Array.from(options ?? []).filter((option) => {
        if (!normalizedQuery) {
            return true;
        }

        const searchableText = [option.label, option.searchText]
            .filter((value) => value !== undefined && value !== null)
            .join(' ')
            .toLocaleLowerCase();

        return searchableText.includes(normalizedQuery);
    });
}

function optionElements(root) {
    return Array.from(root.querySelectorAll('[data-ui-select-option]'));
}

function visibleOptionElements(root) {
    return optionElements(root).filter((option) => !option.hidden && !option.disabled && option.dataset.disabled !== 'true');
}

function setActiveOption(root, option) {
    optionElements(root).forEach((candidate) => {
        candidate.classList.toggle('is-active', candidate === option);
    });

    option?.focus();
}

function updateSelectedState(root, value) {
    const selectedOption = optionElements(root).find(
        (option) => option.dataset.value === String(value),
    );
    const valueInput = root.querySelector('[data-ui-select-value]');
    const label = root.querySelector('[data-ui-select-label]');
    const placeholder = root.dataset.placeholder || 'Pilih';

    if (valueInput) {
        valueInput.value = selectedOption?.dataset.value || '';
    }
    const nativeSelect = root.previousElementSibling?.matches?.('[data-ui-select-native]')
        ? root.previousElementSibling
        : null;
    if (nativeSelect) {
        nativeSelect.value = selectedOption?.dataset.value || '';
    }

    if (label) {
        label.textContent = selectedOption?.dataset.label || placeholder;
        label.classList.toggle('is-placeholder', !selectedOption);
    }

    optionElements(root).forEach((option) => {
        const selected = option === selectedOption;
        option.classList.toggle('is-selected', selected);
        option.setAttribute('aria-selected', selected ? 'true' : 'false');
    });

    root.querySelector('[data-ui-select-clear]')?.toggleAttribute(
        'hidden',
        !selectedOption || root.dataset.clearable !== 'true',
    );
}

function filterOptions(root, query) {
    const options = optionElements(root).map((option) => ({
        label: option.dataset.label || '',
        searchText: option.dataset.searchText || '',
    }));
    const matches = new Set(filterSearchableOptions(options, query));

    optionElements(root).forEach((option, index) => {
        option.hidden = !matches.has(options[index]);
    });

    const empty = root.querySelector('[data-ui-select-empty]');
    if (empty) {
        empty.hidden = matches.size > 0;
    }
}

function closeSelect(root) {
    root.dataset.open = 'false';
    root.querySelector('[data-ui-select-popup]')?.setAttribute('hidden', 'hidden');
    root.querySelector('[data-ui-select-trigger]')?.setAttribute('aria-expanded', 'false');
}

function openSelect(root) {
    if (root.dataset.disabled === 'true') {
        return;
    }

    root.dataset.open = 'true';
    root.querySelector('[data-ui-select-popup]')?.removeAttribute('hidden');
    root.querySelector('[data-ui-select-trigger]')?.setAttribute('aria-expanded', 'true');

    const search = root.querySelector('[data-ui-select-search]');
    if (search) {
        search.focus();
        return;
    }

    const selected = optionElements(root).find((option) => option.classList.contains('is-selected'));
    setActiveOption(root, selected || visibleOptionElements(root)[0]);
}

function chooseOption(root, option) {
    if (!option || option.hidden || option.disabled || option.dataset.disabled === 'true') {
        return;
    }

    updateSelectedState(root, option.dataset.value || '');
    closeSelect(root);
    root.querySelector('[data-ui-select-trigger]')?.focus();
    root.querySelector('[data-ui-select-value]')?.dispatchEvent(new root.ownerDocument.defaultView.Event('change', { bubbles: true }));
}

function bindOption(root, option) {
    if (option.dataset.uiSelectOptionBound === 'true') {
        return;
    }

    option.dataset.uiSelectOptionBound = 'true';
    option.addEventListener('click', () => chooseOption(root, option));
    option.addEventListener('keydown', (event) => {
        const visible = visibleOptionElements(root);
        const index = visible.indexOf(option);

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const nextIndex = event.key === 'ArrowDown' ? index + 1 : index - 1;
            setActiveOption(root, visible[Math.max(0, Math.min(nextIndex, visible.length - 1))]);
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            chooseOption(root, option);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeSelect(root);
            root.querySelector('[data-ui-select-trigger]')?.focus();
        }
    });
}

/**
 * Replace the options in an enhanced or not-yet-enhanced searchable select.
 * Dynamic form rows use the same component contract as server-rendered selects.
 *
 * @param {HTMLElement} root
 * @param {Array<{value: string|number, label: string, searchText?: string, disabled?: boolean}>} options
 */
export function refreshSearchableSelectOptions(root, options = []) {
    const optionsContainer = root?.querySelector('[data-ui-select-options]');
    if (!optionsContainer) {
        return;
    }

    const empty = optionsContainer.querySelector('[data-ui-select-empty]');
    optionsContainer.querySelectorAll('[data-ui-select-option]').forEach((option) => option.remove());
    const nativeSelect = root.previousElementSibling?.matches?.('[data-ui-select-native]')
        ? root.previousElementSibling
        : null;
    if (nativeSelect) {
        nativeSelect.replaceChildren();
        const placeholder = root.dataset.placeholder || 'Pilih';
        const emptyOption = root.ownerDocument.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = placeholder;
        nativeSelect.append(emptyOption);
    }

    for (const option of options) {
        const button = root.ownerDocument.createElement('button');
        button.type = 'button';
        button.className = 'ui-select__option';
        button.setAttribute('role', 'option');
        button.setAttribute('data-ui-select-option', '');
        button.dataset.value = String(option.value ?? '');
        button.dataset.label = String(option.label ?? option.value ?? '');
        button.dataset.searchText = String(option.searchText ?? option.label ?? option.value ?? '');
        button.dataset.disabled = option.disabled ? 'true' : 'false';
        button.disabled = Boolean(option.disabled);
        button.setAttribute('aria-disabled', option.disabled ? 'true' : 'false');
        button.setAttribute('aria-selected', 'false');
        button.textContent = button.dataset.label;
        optionsContainer.insertBefore(button, empty);
        if (nativeSelect) {
            const nativeOption = root.ownerDocument.createElement('option');
            nativeOption.value = button.dataset.value;
            nativeOption.textContent = button.dataset.label;
            nativeOption.disabled = Boolean(option.disabled);
            nativeSelect.append(nativeOption);
        }
        if (root.dataset.uiSelectBound === 'true') {
            bindOption(root, button);
        }
    }

    const selectedValue = root.querySelector('[data-ui-select-value]')?.value || '';
    updateSelectedState(root, selectedValue);
    if (root.dataset.uiSelectBound === 'true') {
        filterOptions(root, root.querySelector('[data-ui-select-search]')?.value || '');
    }
}

/** @param {HTMLElement} root */
export function setSearchableSelectValue(root, value, { emitChange = false } = {}) {
    if (!root) {
        return;
    }

    updateSelectedState(root, value ?? '');
    if (emitChange) {
        root.querySelector('[data-ui-select-value]')?.dispatchEvent(new root.ownerDocument.defaultView.Event('change', { bubbles: true }));
    }
}

function bindSearchableSelect(root, document) {
    if (root.dataset.uiSelectBound === 'true') {
        return;
    }

    const trigger = root.querySelector('[data-ui-select-trigger]');
    const popup = root.querySelector('[data-ui-select-popup]');
    if (!trigger || !popup) {
        return;
    }

    root.dataset.uiSelectBound = 'true';
    const valueInput = root.querySelector('[data-ui-select-value]');
    const nativeSelect = root.previousElementSibling?.matches?.('[data-ui-select-native]')
        ? root.previousElementSibling
        : null;
    root.dataset.defaultValue = valueInput?.value || '';
    root.hidden = false;
    if (nativeSelect) {
        nativeSelect.hidden = true;
        nativeSelect.disabled = true;
    }
    if (valueInput) {
        valueInput.disabled = root.dataset.disabled === 'true';
    }
    updateSelectedState(root, valueInput?.value || '');

    trigger.addEventListener('click', () => {
        root.dataset.open === 'true' ? closeSelect(root) : openSelect(root);
    });

    trigger.addEventListener('keydown', (event) => {
        if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            openSelect(root);
        }
    });

    root.querySelector('[data-ui-select-clear]')?.addEventListener('click', () => {
        updateSelectedState(root, '');
        closeSelect(root);
        trigger.focus();
    });

    optionElements(root).forEach((option) => bindOption(root, option));

    root.querySelector('[data-ui-select-search]')?.addEventListener('input', (event) => {
        filterOptions(root, event.currentTarget.value);
        setActiveOption(root, visibleOptionElements(root)[0]);
    });

    root.querySelector('[data-ui-select-search]')?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeSelect(root);
            trigger.focus();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveOption(root, visibleOptionElements(root)[0]);
        }
    });

    root.closest('form')?.addEventListener('reset', () => {
        document.defaultView?.setTimeout(() => {
            updateSelectedState(root, root.dataset.defaultValue || '');
            closeSelect(root);
        });
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closeSelect(root);
        }
    });
}

export function initializeSearchableSelects(document = globalThis.document) {
    if (!document?.querySelectorAll) {
        return;
    }

    const bind = () => document.querySelectorAll('[data-ui-select]').forEach(
        (root) => bindSearchableSelect(root, document),
    );

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind, { once: true });
        return;
    }

    bind();
}
