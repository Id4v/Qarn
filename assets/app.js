/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the reprise_entry_script_tags('app') Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = toggle.parentElement?.querySelector('input');

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const shouldReveal = input.type === 'password';
        input.type = shouldReveal ? 'text' : 'password';
        toggle.setAttribute('aria-label', shouldReveal ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
});
