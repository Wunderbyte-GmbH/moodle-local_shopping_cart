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

/*
 * @package    local_shopping_cart
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {showNotification} from 'local_shopping_cart/notifications';
import ModalForm from 'core_form/modalform';
import {get_string as getString} from 'core/str';

const SELECTORS = {
    CASHOUTBUTTON: 'div.shopping-cart-cashout-button',
    CASHTRANSFERBUTTON: 'div.shopping-cart-cashtransfer-button',
};

// Little hack to get strings at top-level although getString is asynchronous.
let cashoutsuccess = 'success';
let cashtransfersuccess = 'success';
(async() => {
    cashoutsuccess = await getString('cashoutsuccess', 'local_shopping_cart');
    cashtransfersuccess = await getString('cashtransfersuccess', 'local_shopping_cart');
})();

export const init = () => {
    // eslint-disable-next-line no-console
    console.log('run init');

    // Cashout functionality.
    const cashoutbutton = document.querySelector(SELECTORS.CASHOUTBUTTON);
    if (cashoutbutton) {
        cashoutbutton.addEventListener('click', e => {

            // eslint-disable-next-line no-console
            console.log(e.target);

            cashoutModal(cashoutbutton);
        });
    }

    // Cash transfer functionality.
    const cashtransferbutton = document.querySelector(SELECTORS.CASHTRANSFERBUTTON);
    if (cashtransferbutton) {
        cashtransferbutton.addEventListener('click', e => {

            // eslint-disable-next-line no-console
            console.log(e.target);

            cashtransferModal(cashtransferbutton);
        });
    }

};

/**
 * Show cashout modal.
 * @param {htmlElement} button
 */
export function cashoutModal(button) {

    // eslint-disable-next-line no-console
    console.log('cashoutModal');

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_cashout",
        // Add as many arguments as you need, they will be passed to the form:
        args: {},
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('cashout', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        // eslint-disable-next-line no-console
        console.log('cashoutModal response: ', response);

        showNotification(cashoutsuccess, 'info');
    });

    // Show the form.
    modalForm.show();

}

/**
 * Show cash transfer modal.
 * @param {htmlElement} button
 */
export function cashtransferModal(button) {

    // eslint-disable-next-line no-console
    console.log('cashtransferModal');

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_cashtransfer",
        // Add as many arguments as you need, they will be passed to the form:
        args: {},
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('cashtransfer', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: button
    });

    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        // eslint-disable-next-line no-console
        console.log('cashtransferModal response: ', response);
        showNotification(cashtransfersuccess, 'info');
    });

    // Show the form.
    modalForm.show();

}
