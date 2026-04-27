/**
 * OnlineSched Vanilla Tab System
 */

export function initTabs() {
    const tabContainers = document.querySelectorAll('.os-tabs');

    tabContainers.forEach(container => {
        const tabs = container.querySelectorAll('[data-os-tab]');

        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                const item = tab.parentElement;
                const isAlreadyActive = item.classList.contains('os-tabs__item--active');
                
                const targetId = tab.getAttribute('data-os-tab');
                const paneId = tab.getAttribute('data-os-pane') || targetId;
                const targetPane = document.getElementById(paneId);

                if (!targetPane) return;

                // Ensure schedule container is visible
                const scheduleContainer = document.getElementById('schedule');
                if (scheduleContainer && scheduleContainer.style.display === 'none') {
                    scheduleContainer.style.display = '';
                }

                if (!isAlreadyActive) {
                    // Deactivate all tabs in this container
                    tabs.forEach(t => {
                        t.parentElement.classList.remove('os-tabs__item--active');
                    });

                    // Deactivate all panes in the same tab-content
                    // Note: multiple tabs can target the same pane (e.g., Programming and Essentials)
                    const tabContent = targetPane.parentElement;
                    if (tabContent && tabContent.classList.contains('os-tab-content')) {
                        const panes = tabContent.querySelectorAll('.os-tab-pane');
                        panes.forEach(p => {
                            p.classList.remove('os-tab-pane--active');
                        });
                    }

                    // Activate current tab and pane
                    item.classList.add('os-tabs__item--active');
                    targetPane.classList.add('os-tab-pane--active');

                    // Dispatch custom event
                    const event = new CustomEvent('os:tab:shown', {
                        detail: {
                            tab: tab,
                            target: targetPane,
                            hash: `#${targetId}`
                        },
                        bubbles: true
                    });
                    tab.dispatchEvent(event);
                }
            });
        });
    });

    // Handle initial hash on page load for tab switching
    handleInitialHash();
}

function handleInitialHash() {
    const hash = window.location.hash;
    if (!hash) return;

    let targetId = hash.substring(1);
    
    // Support legacy singular #hour and other variations
    if (targetId === 'hour') targetId = 'hours';
    
    const tab = document.querySelector(`[data-os-tab="${targetId}"]`);
    if (tab) {
        // We use a small timeout to ensure all listeners are attached 
        // and the DOM is fully ready to be manipulated.
        setTimeout(() => {
            tab.click();
        }, 10);
    }
}
