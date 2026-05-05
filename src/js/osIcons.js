/**
 * Icon Utility for Phase 10.5
 */
export function updateIconClasses(icon, state) {
    if (!icon) return;
    
    const config = window.OnlineSchedPublic || {};
    const inactive = (config.iconFavInactive || 'far fa-star').split(' ').filter(Boolean);
    const active = (config.iconFavActive || 'fas fa-star').split(' ').filter(Boolean);
    
    if (state) {
        icon.classList.remove(...inactive);
        icon.classList.add(...active);
    } else {
        icon.classList.remove(...active);
        icon.classList.add(...inactive);
    }
}
