import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer(
  (page) =>
    createInertiaApp({
      page,
      render: renderToString,
      title: (title) => `${title} - ${appName}`,
      resolve: resolvePage,
      setup: ({ App, props, plugin }) =>
        createSSRApp({ render: () => h(App, props) })
          .use(plugin)
          .use(ZiggyVue, {
            ...page.props.ziggy,
            location: new URL(page.props.ziggy.location),
          }),
    }),
  { cluster: true },
);

function resolvePage(name) {
  const pages = import.meta.glob('./pages/**/*.vue');

  return resolvePageComponent(`./pages/${name}.vue`, pages);
}
