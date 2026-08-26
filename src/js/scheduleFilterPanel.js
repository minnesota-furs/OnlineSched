/* Checkbox filter panels that stand in for the schedule's single-select menus. */

const $ = (selector, root = document) => root.querySelector(selector);

// Each panel mirrors one menu, so option membership, ordering and the greying
// of zero-match options stay owned by the code that already does them.
const PANELS = [];
let scrim = null;

// The sheet covers the list on a phone, so a tap outside it must not also
// reach the schedule row underneath.
function syncScrim() {
    if (!scrim) return;
    scrim.hidden = !PANELS.some((panel) => !panel.list.hidden);
}

function optionRows(select) {
    return Array.from(select.options).map((option) => ({
        value: option.value,
        label: option.textContent.trim(),
        disabled: option.disabled,
    }));
}

function buildPanel(select, { label, modeValues, onChange, summarize }) {
    const wrapper = document.createElement('div');
    wrapper.className = 'os-filter-panel';
    wrapper.dataset.filterPanel = select.id;

    const listId = `${select.id}-panel-list`;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'os-form-control os-filter-panel-button';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', listId);

    const buttonLabel = document.createElement('span');
    buttonLabel.className = 'os-filter-panel-button-label';
    button.append(buttonLabel);

    const list = document.createElement('div');
    list.className = 'os-filter-panel-list';
    list.id = listId;
    list.hidden = true;
    list.setAttribute('role', 'group');
    list.setAttribute('aria-label', label);

    const header = document.createElement('div');
    header.className = 'os-filter-panel-header';
    const title = document.createElement('span');
    title.className = 'os-filter-panel-title';
    title.textContent = label;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'os-btn os-btn--default os-btn--sm os-filter-panel-close';
    close.textContent = 'Close';
    close.setAttribute('aria-label', `Close ${label} filter`);

    const rows = document.createElement('div');
    rows.className = 'os-filter-panel-rows';

    header.append(title, close);
    list.append(header, rows);
    wrapper.append(button, list);
    select.parentNode.insertBefore(wrapper, select);

    // The menu stays the single-value entry point for deep links and chips, so
    // it is taken out of sight and out of the tab order rather than removed.
    select.classList.add('os-filter-panel-source');
    select.setAttribute('aria-hidden', 'true');
    select.setAttribute('tabindex', '-1');

    const panel = { select, wrapper, button, buttonLabel, list, rows, modeValues, onChange, summarize };
    PANELS.push(panel);

    button.addEventListener('click', () => {
        const opening = list.hidden;
        closeAllPanels();
        list.hidden = !opening;
        button.setAttribute('aria-expanded', String(opening));
        syncScrim();
    });

    close.addEventListener('click', () => closeAllPanels({ restoreFocus: true }));

    renderRows(panel);
    return panel;
}

function renderRows(panel) {
    const { rows, select, modeValues } = panel;
    rows.replaceChildren();

    optionRows(select).forEach((row) => {
        const label = document.createElement('label');
        label.className = 'os-filter-panel-row';
        if (modeValues.includes(row.value)) label.classList.add('os-filter-panel-mode');

        const box = document.createElement('input');
        box.type = 'checkbox';
        box.value = row.value;
        box.disabled = row.disabled;

        const text = document.createElement('span');
        text.textContent = row.label;

        box.addEventListener('change', () => panel.onChange(row.value, box.checked));

        label.append(box, text);
        rows.append(label);
    });
}

// Availability is recomputed on every sort, so the rows follow the menu that
// already carries it rather than deciding a second time.
export function refreshFilterPanels(state) {
    PANELS.forEach((panel) => {
        const rows = optionRows(panel.select);
        const boxes = Array.from(panel.rows.querySelectorAll('input[type="checkbox"]'));
        if (boxes.length !== rows.length) renderRows(panel);

        Array.from(panel.rows.querySelectorAll('input[type="checkbox"]')).forEach((box) => {
            const row = rows.find((candidate) => candidate.value === box.value);
            if (!row) return;
            box.disabled = row.disabled;
            box.checked = panel.isChecked(row.value, state);
        });
        panel.buttonLabel.textContent = panel.summarize(state);
        panel.button.classList.toggle('is-active', panel.isNarrowed(state));
    });
}

// Escape and the close control hand focus back to the button that opened the
// panel; an outside click must not, the person is reaching for something else.
export function closeAllPanels({ restoreFocus = false } = {}) {
    const open = PANELS.find((panel) => !panel.list.hidden);
    PANELS.forEach((panel) => {
        panel.list.hidden = true;
        panel.button.setAttribute('aria-expanded', 'false');
    });
    syncScrim();
    if (restoreFocus && open) open.button.focus();
}

export function createFilterPanels(config) {
    config.forEach((entry) => {
        const select = $(entry.selector);
        if (!select) return;
        const panel = buildPanel(select, entry);
        panel.isChecked = entry.isChecked;
        panel.isNarrowed = entry.isNarrowed;
    });

    if (PANELS.length && !scrim) {
        scrim = document.createElement('div');
        scrim.className = 'os-filter-panel-scrim';
        scrim.hidden = true;
        scrim.setAttribute('aria-hidden', 'true');
        scrim.addEventListener('click', () => closeAllPanels());
        document.body.append(scrim);
    }

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target?.closest('.os-filter-panel')) closeAllPanels();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAllPanels({ restoreFocus: true });
    });
}
