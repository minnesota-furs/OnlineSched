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

// The day selector lives directly in the block content area - no need to open
// the sidebar just to change Thursday to Friday. The <dt> / <dd> structure
// matches the frontend so rows look right while editing.
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
            el('dt', { className: 'os-hours__day-label' },
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

// The hours and optional note are inline-editable RichText fields so the row
// looks exactly like the frontend while editing. Formatting options (line break,
// italics) belong in the Inspector sidebar - they are metadata about the row,
// not its content.
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
    },
    edit: ({ attributes, setAttributes, isSelected }) => {
        const italics = Array.isArray(attributes.italics) ? attributes.italics : [];
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
                        value: attributes.hours,
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
                )
            ),
            // Inline hours field - click to edit directly like any text block.
            el(RichText, {
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
