import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

// Raccourci dev : ?jwt=<token> stocke le jeton puis nettoie l'URL.
const qs = new URLSearchParams(window.location.search);
const jwtParam = qs.get('jwt');
if (jwtParam) {
    localStorage.setItem('jwt', jwtParam);
    qs.delete('jwt');
    const clean = window.location.pathname + (qs.size ? '?' + qs : '');
    history.replaceState(null, '', clean);
}

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
