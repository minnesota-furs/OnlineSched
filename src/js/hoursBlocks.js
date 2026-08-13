/* global OnlineSchedHoursBlocks */

const { registerBlockType } = wp.blocks;
const { CheckboxControl, PanelBody, SelectControl, TextControl, TextareaControl } = wp.components;
const { InnerBlocks, InspectorControls, RichText, useBlockProps } = wp.blockEditor;
const { createElement: el } = wp.element;
const { __ } = wp.i18n;

// Day choices passed from PHP via wp_localize_script so they respect the
// os_hours_day_choices filter without duplicating the list in JS.
const dayChoices = (window.OnlineSchedHoursBlocks?.dayChoices || [
    'Thursday', 'Friday', 'Saturday', 'Sunday', 'Monday',
]).map((day) => ({ label: day, value: day }));

// Default block templates so new blocks open with one example row each.
const timeTemplate = [['onlinesched/hours-time', { hours: '10am - 6pm' }]];

// Keep the editor preview aligned with the PHP renderer.
const clockParts = (value) => {
    const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(typeof value === 'string' ? value.trim() : '');
    return match ? { hour: Number(match[1]), minute: Number(match[2]) } : null;
};

const formatClock = ({ hour, minute }) => {
    if (minute === 0 && hour === 12) return __('Noon', 'onlinesched');
    if (minute === 0 && hour === 0) return __('Midnight', 'onlinesched');
    const suffix = hour < 12 ? 'am' : 'pm';
    const display = hour % 12 === 0 ? 12 : hour % 12;
    return display + (minute === 0 ? '' : ':' + String(minute).padStart(2, '0')) + suffix;
};

const formatRange = (start, end, allDay) => {
    if (allDay) return __('24 hours', 'onlinesched');
    const from = clockParts(start);
    const to = clockParts(end);
    if (!from || !to) return '';
    if (start.trim() === end.trim()) return __('24 hours', 'onlinesched');
    return formatClock(from) + ' - ' + formatClock(to);
};
const dayTemplate = [['onlinesched/hours-day', { day: 'Friday' }, timeTemplate]];

// Purely structural. Its only job in the editor is to hold departments in a
// two-column grid preview.
registerBlockType('onlinesched/hours-of-operations', {
    title: __('Hours of Operations', 'onlinesched'),
    icon: 'clock',
    category: 'widgets',
    supports: { html: false },
    edit: () => el(
        'div', useBlockProps({ className: 'os-hours os-hours--editor' }),
        el('div', { className: 'os-hours__row' },
            el(InnerBlocks, {
                allowedBlocks: ['onlinesched/hours-department'],
                template: [['onlinesched/hours-department', {}, dayTemplate]],
                templateLock: false,
            })
        )
    ),
    save: () => el(InnerBlocks.Content),
});

// WYSIWYG: department name and room/location are both inline RichText so they render
// in the editor exactly as they appear on the frontend.
registerBlockType('onlinesched/hours-department', {
    title: __('Hours: Department', 'onlinesched'),
    icon: 'building',
    category: 'widgets',
    parent: ['onlinesched/hours-of-operations'],
    supports: { html: false },
    attributes: {
        department: { type: 'string', default: '' },
        location:   { type: 'string', default: '' },
    },
    edit: ({ attributes, setAttributes }) =>
        el('section', useBlockProps({ className: 'os-hours__dept' }),
            el(InspectorControls, {},
                el(PanelBody, { title: __('Department Details', 'onlinesched'), initialOpen: true },
                    el(TextControl, {
                        label: __('Department name', 'onlinesched'),
                        value: attributes.department,
                        onChange: (department) => setAttributes({ department }),
                    }),
                    el(TextareaControl, {
                        label: __('Room or location', 'onlinesched'),
                        help: __('Shown directly under the department name.', 'onlinesched'),
                        value: attributes.location,
                        onChange: (location) => setAttributes({ location }),
                    })
                )
            ),
            el(RichText, {
                tagName:        'h3',
                className:      'os-hours__name',
                placeholder:    __('Department - e.g. Registration', 'onlinesched'),
                value:          attributes.department,
                allowedFormats: [],
                onChange:       (department) => setAttributes({ department }),
            }),
            el(RichText, {
                tagName:        'div',
                className:      'os-hours__location',
                placeholder:    __('Room or location - e.g. 2nd Floor, Lobby', 'onlinesched'),
                value:          attributes.location,
                allowedFormats: ['core/bold', 'core/italic', 'core/link'],
                keepPlaceholderOnFocus: true,
                onChange:       (location) => setAttributes({ location }),
            }),
            // The dl wrapper mirrors the rendered output so the day rows look
            // identical to the frontend dt/dd layout while editing.
            el('dl', { className: 'os-hours__days' },
                el(InnerBlocks, {
                    allowedBlocks: ['onlinesched/hours-day'],
                    template:      dayTemplate,
                    templateLock:  false,
                })
            )
        ),
    save: () => el(InnerBlocks.Content),
});

// The day selector sits in the block content rather than the sidebar, and the
// markup matches the frontend so rows look right while editing.
registerBlockType('onlinesched/hours-day', {
    title: __('Hours: Day', 'onlinesched'),
    icon: 'calendar-alt',
    category: 'widgets',
    parent: ['onlinesched/hours-department'],
    supports: { html: false },
    attributes: {
        day: { type: 'string', default: 'Friday' },
    },
    edit: ({ attributes, setAttributes }) =>
        el('div', useBlockProps({ className: 'os-hours__day-row' }),
            el('dt', { className: 'os-hours__day-label os-hours__day-label--editing' },
                // SelectControl inline so the day is always visible and editable
                // without opening any panel.
                el(SelectControl, {
                    value:                 attributes.day,
                    options:               dayChoices,
                    onChange:              (day) => setAttributes({ day }),
                    __nextHasNoMarginBottom: true,
                    className:             'os-hours__day-select',
                })
            ),
            el('dd', { className: 'os-hours__day-times' },
                el(InnerBlocks, {
                    allowedBlocks: ['onlinesched/hours-time'],
                    template:      timeTemplate,
                    templateLock:  false,
                })
            )
        ),
    save: () => el(InnerBlocks.Content),
});

// Inline RichText so the row matches the frontend while editing; formatting
// controls live in the Inspector, being metadata rather than content.
registerBlockType('onlinesched/hours-time', {
    title: __('Hours: Time', 'onlinesched'),
    icon: 'clock',
    category: 'widgets',
    parent: ['onlinesched/hours-day'],
    supports: { html: false },
    attributes: {
        hours:     { type: 'string',  default: '' },
        smallText: { type: 'string',  default: '' },
        addBreak:  { type: 'boolean', default: false },
        italics:   { type: 'array',   default: [] },
        start:     { type: 'string',  default: '' },
        end:       { type: 'string',  default: '' },
        allDay:    { type: 'boolean', default: false },
        closed:    { type: 'boolean', default: false },
    },
    edit: ({ attributes, setAttributes, isSelected }) => {
        const italics = Array.isArray(attributes.italics) ? attributes.italics : [];
        const generated = formatRange(attributes.start, attributes.end, attributes.allDay);
        const toggleItalic = (value, enabled) => {
            const next = enabled
                ? [...new Set(italics.concat(value))]
                : italics.filter((item) => item !== value);
            setAttributes({ italics: next });
        };

        const smallClass = [
            'os-hours__time-small',
            attributes.addBreak     ? 'os-hours__time-small--break'  : '',
            italics.includes('Small') ? 'os-hours__time-small--italic' : '',
            !attributes.smallText && !isSelected ? 'os-hours__time-small--empty' : '',
        ].filter(Boolean).join(' ');

        return el('div', useBlockProps({ className: 'os-hours__time os-hours__time--editing' }),
            // Formatting options belong in the sidebar, not cluttering the content area.
            el(InspectorControls, {},
                el(PanelBody, { title: __('Formatting', 'onlinesched'), initialOpen: true },
                    el(TextControl, {
                        label: __('Hours', 'onlinesched'),
                        value: generated || attributes.hours,
                        disabled: !!generated,
                        help: generated
                            ? __('Built from Opens and Closes below. Clear those to type the line yourself.', 'onlinesched')
                            : undefined,
                        onChange: (hours) => setAttributes({ hours }),
                    }),
                    el(TextControl, {
                        label: __('Optional note', 'onlinesched'),
                        value: attributes.smallText,
                        onChange: (smallText) => setAttributes({ smallText }),
                    }),
                    el(CheckboxControl, {
                        label:    __('Note on its own line', 'onlinesched'),
                        checked:  attributes.addBreak,
                        onChange: (addBreak) => setAttributes({ addBreak }),
                    }),
                    el(CheckboxControl, {
                        label:    __('Italicize hours', 'onlinesched'),
                        checked:  italics.includes('Hours'),
                        onChange: (checked) => toggleItalic('Hours', checked),
                    }),
                    el(CheckboxControl, {
                        label:    __('Italicize note', 'onlinesched'),
                        checked:  italics.includes('Small'),
                        onChange: (checked) => toggleItalic('Small', checked),
                    })
                ),
                el(PanelBody, { title: __('Open now', 'onlinesched'), initialOpen: true },
                    el('p', { className: 'os-hours__help' },
                        __('Fill these in so the app can show an Open now badge. Leave them empty and the line still displays, it just never counts as open.', 'onlinesched')),
                    el(CheckboxControl, {
                        label:    __('Open 24 hours', 'onlinesched'),
                        checked:  !!attributes.allDay,
                        onChange: (allDay) => setAttributes({ allDay }),
                    }),
                    !attributes.allDay && el(TextControl, {
                        label: __('Opens', 'onlinesched'),
                        type: 'time',
                        value: attributes.start,
                        onChange: (start) => setAttributes({ start }),
                    }),
                    !attributes.allDay && el(TextControl, {
                        label: __('Closes', 'onlinesched'),
                        type: 'time',
                        value: attributes.end,
                        onChange: (end) => setAttributes({ end }),
                    }),
                    el(CheckboxControl, {
                        label:    __('This is a closed period', 'onlinesched'),
                        help:     __('For lines like a lunch break. The times still show, but Open now treats the space as shut.', 'onlinesched'),
                        checked:  attributes.closed,
                        onChange: (closed) => setAttributes({ closed }),
                    })
                )
            ),
            // Inline hours field - click to edit directly like any text block.
            generated
                ? el('span', {
                    className: 'os-hours__time-val os-hours__time-val--generated'
                        + (italics.includes('Hours') ? ' os-hours__time-val--italic' : ''),
                    title: __('Set under Open now in the sidebar.', 'onlinesched'),
                }, generated)
                : el(RichText, {
                    tagName:        'span',
                    className:      'os-hours__time-val' + (italics.includes('Hours') ? ' os-hours__time-val--italic' : ''),
                    value:          attributes.hours,
                    onChange:       (hours) => setAttributes({ hours }),
                    placeholder:    __('5pm - 9pm', 'onlinesched'),
                    allowedFormats: [],
                    keepPlaceholderOnFocus: true,
                }),
            // Optional note appears when selected or when it has content, keeping the
            // normal editing view close to the frontend output.
            el(RichText, {
                tagName:        'small',
                className:      smallClass,
                value:          attributes.smallText,
                onChange:       (smallText) => setAttributes({ smallText }),
                placeholder:    __('(optional note)', 'onlinesched'),
                allowedFormats: [],
                keepPlaceholderOnFocus: true,
            })
        );
    },
    // Leaf block - no inner content to preserve. PHP render_callback handles all output.
    save: () => null,
});
