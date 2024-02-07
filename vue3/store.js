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
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Import needed libraries
import { createStore } from 'vuex';
import moodleAjax from 'core/ajax';
import moodleStorage from 'core/localstorage';
import Notification from 'core/notification';
import $ from 'jquery';

// Defining store for application
const store = createStore({
  state() {
      return {
          strings: {},
          view: 'default',
          store: [],
      };
  },
  mutations: {
      // Mutations are synchronous.
      setLearningGoalID(state, id) {
          state.learningGoalID = id;
      },
      setContextID(state, id) {
          state.contextID = id;
      },
      setStrings(state, strings) {
          state.strings = strings;
      },
  },
  actions: {
      // Actions are asynchronous.
      async loadLang(context) {
          const lang = $('html').attr('lang').replace(/-/g, '_');
          context.commit('setLang', lang);
      },
      async loadComponentStrings(context) {
          const lang = $('html').attr('lang').replace(/-/g, '_');
          const cacheKey = 'local_adele/strings/' + lang;
          const cachedStrings = moodleStorage.get(cacheKey);
          if (cachedStrings) {
              context.commit('setStrings', JSON.parse(cachedStrings));
          } else {
              const request = {
                  methodname: 'core_get_component_strings',
                  args: {
                      'component': 'local_adele',
                      lang,
                  },
              };
              const loadedStrings = await moodleAjax.call([request])[0];
              let strings = {};
              loadedStrings.forEach((s) => {
                  strings[s.stringid] = s.string;
              });
              context.commit('setStrings', strings);
              moodleStorage.set(cacheKey, JSON.stringify(strings));
          }
      },
      async fetchBookingItem(context, itemid) {
        const result = await ajax('local_shopping_cart_get_item', 
        {itemid: itemid});
        return result;
    },
  }
});

export { store };

/**
* Single ajax call to Moodle.
*/
export async function ajax(method, args) {
  const request = {
      methodname: method,
      args: Object.assign(args),
  };

  try {
      return await moodleAjax.call([request])[0];
  } catch (e) {
      Notification.exception(e);
      throw e;
  }
}