import './bootstrap';
import 'bootstrap';
import { createIcons, icons } from 'lucide';

const renderLucideIcons = () => {
    if (typeof document === 'undefined') {
        return;
    }

    createIcons({
        icons,
        attrs: {
            'stroke-width': 1.75,
        },
    });
};

document.addEventListener('DOMContentLoaded', renderLucideIcons);
document.addEventListener('livewire:navigated', renderLucideIcons);
window.addEventListener('load', renderLucideIcons);

window.renderLucideIcons = renderLucideIcons;
