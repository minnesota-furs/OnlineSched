/**
 * Solo Event Block - Editor components (Native JS style).
 * 
 * @package OnlineSched
 */

const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, SearchControl, Spinner, Placeholder } = wp.components;
const { createElement: el, useState, useEffect } = wp.element;
const { __ } = wp.i18n;
const apiFetch = wp.apiFetch;
const ServerSideRender = wp.serverSideRender;

registerBlockType('onlinesched/solo-event', {
    title: __('OnlineSched: Single Event', 'onlinesched'),
    icon: 'calendar-alt',
    category: 'widgets',
    description: __('Embed one schedule event as a dynamic card.', 'onlinesched'),
    attributes: {
        eventId: {
            type: 'integer',
            default: 0
        },
        fullWidth: {
            type: 'boolean',
            default: false
        }
    },
    supports: {
        align: ['wide', 'full'],
        html: false
    },
    edit: SoloEventEdit,
    save: () => null, // Server-side render
});

function SoloEventEdit({ attributes, setAttributes }) {
    const { eventId, fullWidth } = attributes;
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [isSearching, setIsSearching] = useState(false);

    const blockProps = useBlockProps({
        className: `os-solo-event-block-editor ${fullWidth ? 'is-full-width' : ''}`
    });

    // Handle search
    useEffect(() => {
        if (searchTerm.length < 3) {
            setSearchResults([]);
            return;
        }

        const controller = new AbortController();
        setIsSearching(true);

        apiFetch({
            path: `/onlinesched/v1/event-search?s=${encodeURIComponent(searchTerm)}`,
            signal: controller.signal,
        })
            .then((results) => {
                setSearchResults(results);
                setIsSearching(false);
            })
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    console.error('Search failed:', error);
                    setIsSearching(false);
                }
            });

        return () => controller.abort();
    }, [searchTerm]);

    const onSelectEvent = (id) => {
        setAttributes({ eventId: parseInt(id, 10) });
    };

    const toggleFullWidth = (val) => {
        setAttributes({ fullWidth: val });
    };

    // Render components
    const controls = el(InspectorControls, {},
        el(PanelBody, { title: __('Display Settings', 'onlinesched'), initialOpen: true },
            el(wp.components.ToggleControl, {
                label: __('Full Width', 'onlinesched'),
                help: __('Make the card span the full width of the container.', 'onlinesched'),
                checked: fullWidth,
                onChange: toggleFullWidth,
            })
        ),
        el(PanelBody, { title: __('Event Selection', 'onlinesched'), initialOpen: true },
            el(SearchControl, {
                label: __('Search Events', 'onlinesched'),
                value: searchTerm,
                onChange: setSearchTerm,
            }),
            isSearching && el(Spinner),
            !isSearching && searchResults.length > 0 && el(SelectControl, {
                label: __('Select an Event', 'onlinesched'),
                value: eventId,
                options: [
                    { label: __('Select an event...', 'onlinesched'), value: 0 },
                    ...searchResults.map((evt) => ({
                        label: `${evt.title} (${evt.date})`,
                        value: evt.id,
                    })),
                ],
                onChange: onSelectEvent,
            })
        )
    );

    const content = eventId 
        ? el(ServerSideRender, {
            block: 'onlinesched/solo-event',
            attributes: attributes,
          })
        : el(Placeholder, {
            icon: 'calendar-alt',
            label: __('OnlineSched: Single Event', 'onlinesched'),
            instructions: __('Select an event in the sidebar to display it as an interactive card.', 'onlinesched'),
          },
          el(SearchControl, {
            label: __('Search Events', 'onlinesched'),
            value: searchTerm,
            onChange: setSearchTerm,
          })
        );

    return el('div', blockProps, controls, content);
}
