// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Import needed libraries
import { createRouter, createWebHashHistory } from 'vue-router';
import notFound from '../components/NotFound';
import shoppingCartBtn from '../components/ShoppingCartBtn.vue';
import { useStore } from 'vuex'

const store = useStore()
// All available routes
const routes = [
    {
        path: '/',
        redirect: {
            name: 'shopping-cart-btn',
        }
    },
    {
        path: '/shoppingcart/edit',
        component: shoppingCartBtn,
        name: 'shopping-cart-btn',
    },
    {
        path: '/:catchAll(.*)',
        component: notFound
    },
];
const currenturl = window.location.pathname;
const base = currenturl;

// Creating router
const router = createRouter({
    history: createWebHashHistory(),
    routes,
    base
});

router.beforeEach((to, from, next) => {
    // Find a translation for the title.
    if (to.meta && to.meta.title && store.state.strings[to.meta.title]) {
        document.title = store.state.strings[to.meta.title];
    }
    next();
});

export default router